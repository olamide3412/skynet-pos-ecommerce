<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SyncedPosItem;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;

class UpdateCostPriceFromCsvCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:update-cost-from-csv
                            {file : Path to the raw CSV file (relative to project root or absolute path)}
                            {--name-col= : Optional explicit CSV column header for the Item Name}
                            {--cost-col= : Optional explicit CSV column header for the Cost/Buying Price}
                            {--only-missing : Only update products where cost_price is currently NULL or 0}
                            {--dry-run : Preview matches and updates without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update product cost_price from a raw CSV file by matching item name, skipping any items not found';

    /**
     * Normalize a string for case-insensitive, whitespace-tolerant matching.
     */
    protected function normalizeName(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    /**
     * Normalize a CSV header key to snake_case alphanumeric.
     */
    protected function normalizeHeader(string $header): string
    {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $clean = strtolower(trim($clean));
        $clean = str_replace([' ', '-', '/'], '_', $clean);
        $clean = preg_replace('/[^a-z0-9_]/', '', $clean);

        return trim(preg_replace('/_+/', '_', $clean), '_');
    }

    /**
     * Parse a raw price string (e.g. "₦1,450.00" or "1450") into a float.
     */
    protected function parsePrice(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', str_replace(',', '', trim($raw)));
        if ($cleaned === '' || !is_numeric($cleaned)) {
            return null;
        }

        return round((float) $cleaned, 2);
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fileArg = $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $onlyMissing = (bool) $this->option('only-missing');

        // Resolve file path (check as-is, then relative to base_path, then storage_path)
        $candidates = [
            $fileArg,
            base_path($fileArg),
            storage_path('app/' . ltrim($fileArg, '/\\')),
            public_path(ltrim($fileArg, '/\\')),
        ];

        $filePath = null;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate) && is_file($candidate)) {
                $filePath = realpath($candidate) ?: $candidate;
                break;
            }
        }

        if (!$filePath) {
            $this->error("CSV file not found: {$fileArg}");
            $this->line('Tip: Upload the CSV to your project root (e.g. "raw_items.csv") and run:');
            $this->line('  php artisan products:update-cost-from-csv raw_items.csv');
            return self::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->error("Could not open CSV file for reading: {$filePath}");
            return self::FAILURE;
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerRow = fgetcsv($handle);
        if (!$headerRow || empty(array_filter($headerRow, fn ($v) => trim((string) $v) !== ''))) {
            fclose($handle);
            $this->error('The CSV file is empty or missing a valid header row.');
            return self::FAILURE;
        }

        $headerMap = [];
        foreach ($headerRow as $idx => $colName) {
            $norm = $this->normalizeHeader((string) $colName);
            if ($norm !== '' && !isset($headerMap[$norm])) {
                $headerMap[$norm] = $idx;
            }
        }

        // Resolve Item Name column index
        $nameColIdx = null;
        $customNameCol = $this->option('name-col');
        if ($customNameCol) {
            $normCustomName = $this->normalizeHeader($customNameCol);
            $nameColIdx = $headerMap[$normCustomName] ?? null;
        } else {
            $nameCandidates = [
                'name',
                'item_name',
                'product_name',
                'pos_item_name',
                'synced_pos_item_name',
                'item',
                'product',
                'title',
                'item_description',
                'description',
            ];
            foreach ($nameCandidates as $candidate) {
                if (isset($headerMap[$candidate])) {
                    $nameColIdx = $headerMap[$candidate];
                    break;
                }
            }
        }

        // Resolve Cost / Buying Price column index
        $costColIdx = null;
        $customCostCol = $this->option('cost-col');
        if ($customCostCol) {
            $normCustomCost = $this->normalizeHeader($customCostCol);
            $costColIdx = $headerMap[$normCustomCost] ?? null;
        } else {
            $costCandidates = [
                'cost_price',
                'cost_price_ngn',
                'buying_price',
                'buying_price_ngn',
                'cost',
                'unit_cost',
                'purchase_price',
                'costprice',
                'buyingprice',
                'avg_cost',
                'average_cost',
                'last_cost',
            ];
            foreach ($costCandidates as $candidate) {
                if (isset($headerMap[$candidate])) {
                    $costColIdx = $headerMap[$candidate];
                    break;
                }
            }
        }

        if ($nameColIdx === null || $costColIdx === null) {
            fclose($handle);
            $this->error('Could not automatically detect the Item Name or Cost Price column in the CSV header.');
            $this->line('Detected CSV columns: ' . implode(', ', array_map(fn ($h) => '"' . trim((string) $h) . '"', $headerRow)));
            $this->line('Please specify the column names explicitly using --name-col and --cost-col, for example:');
            $this->line('  php artisan products:update-cost-from-csv "' . $fileArg . '" --name-col="Item Name" --cost-col="Buying Price"');
            return self::FAILURE;
        }

        $detectedNameHeader = trim((string) $headerRow[$nameColIdx]);
        $detectedCostHeader = trim((string) $headerRow[$costColIdx]);

        $this->info("Reading CSV: {$filePath}");
        $this->line("  • Item Name Column : [{$nameColIdx}] \"{$detectedNameHeader}\"");
        $this->line("  • Cost Price Column: [{$costColIdx}] \"{$detectedCostHeader}\"");
        if ($dryRun) {
            $this->warn('  • Mode             : DRY RUN (no database changes will be saved)');
        }
        if ($onlyMissing) {
            $this->line('  • Filter           : --only-missing (only products with NULL or 0 cost_price)');
        }
        $this->newLine();

        // Pre-index all products in memory by normalized name & pos_item_name_snapshot for fast O(1) lookup
        $productsByName = [];
        $allProducts = Product::select(['id', 'name', 'pos_item_name_snapshot', 'cost_price', 'pos_item_id'])->get();

        foreach ($allProducts as $product) {
            $normName = $this->normalizeName($product->name);
            if ($normName !== '') {
                $productsByName[$normName][$product->id] = $product;
            }

            $normSnapshot = $this->normalizeName($product->pos_item_name_snapshot);
            if ($normSnapshot !== '' && $normSnapshot !== $normName) {
                $productsByName[$normSnapshot][$product->id] = $product;
            }
        }

        $totalRows = 0;
        $matchedRows = 0;
        $updatedProductsCount = 0;
        $unchangedProductsCount = 0;
        $skippedNotFoundCount = 0;
        $skippedInvalidCostCount = 0;
        $notFoundSamples = [];

        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely blank lines
            if (empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $totalRows++;
            $rawName = isset($row[$nameColIdx]) ? trim((string) $row[$nameColIdx]) : '';
            $rawCost = isset($row[$costColIdx]) ? trim((string) $row[$costColIdx]) : '';

            if ($rawName === '') {
                $skippedNotFoundCount++;
                continue;
            }

            $costPrice = $this->parsePrice($rawCost);
            if ($costPrice === null || $costPrice <= 0) {
                $skippedInvalidCostCount++;
                continue;
            }

            $normSearch = $this->normalizeName($rawName);
            $matchedProducts = $productsByName[$normSearch] ?? null;

            if (empty($matchedProducts)) {
                $skippedNotFoundCount++;
                if (count($notFoundSamples) < 15) {
                    $notFoundSamples[] = $rawName;
                }
                continue;
            }

            $matchedRows++;

            foreach ($matchedProducts as $product) {
                $currentCost = $product->cost_price !== null ? round((float) $product->cost_price, 2) : 0.0;

                if ($onlyMissing && $currentCost > 0) {
                    $unchangedProductsCount++;
                    continue;
                }

                if (abs($currentCost - $costPrice) < 0.001 && $product->cost_price !== null) {
                    $unchangedProductsCount++;
                    continue;
                }

                if (!$dryRun) {
                    $product->update(['cost_price' => $costPrice]);

                    // Also backfill SyncedPosItem buying_price if linked and currently missing
                    if ($product->pos_item_id) {
                        SyncedPosItem::where('pos_item_id', $product->pos_item_id)
                            ->where(function ($q) {
                                $q->whereNull('buying_price')->orWhere('buying_price', '<=', 0);
                            })
                            ->update(['buying_price' => $costPrice]);
                    }
                }

                $product->cost_price = $costPrice;
                $updatedProductsCount++;
            }
        }

        fclose($handle);

        if (!$dryRun && $updatedProductsCount > 0) {
            try {
                ActivityLogger::product("Updated cost price for {$updatedProductsCount} product(s) via CLI CSV command", [
                    'file'                => basename($filePath),
                    'total_rows'          => $totalRows,
                    'matched_rows'        => $matchedRows,
                    'updated_products'    => $updatedProductsCount,
                    'skipped_not_found'   => $skippedNotFoundCount,
                    'skipped_invalid_cost'=> $skippedInvalidCostCount,
                ]);
            } catch (\Throwable $e) {
                // Ignore logger error in CLI if no auth user context
            }
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total CSV Rows Read', number_format($totalRows)],
                ['Matched CSV Rows', number_format($matchedRows)],
                [$dryRun ? 'Products Would Be Updated (Dry Run)' : 'Products Updated with Cost Price', number_format($updatedProductsCount)],
                ['Products Already Matching / Skipped', number_format($unchangedProductsCount)],
                ['Skipped (Item Name Not Found in DB)', number_format($skippedNotFoundCount)],
                ['Skipped (Empty or Zero Cost Price)', number_format($skippedInvalidCostCount)],
            ]
        );

        if (!empty($notFoundSamples)) {
            $this->newLine();
            $this->comment('Sample of skipped items (not found in database):');
            foreach ($notFoundSamples as $sample) {
                $this->line("  - {$sample}");
            }
            if ($skippedNotFoundCount > count($notFoundSamples)) {
                $remaining = $skippedNotFoundCount - count($notFoundSamples);
                $this->line("  ... and {$remaining} more.");
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run completed. Re-run without --dry-run to save changes.'
            : "Done! Successfully updated cost_price on {$updatedProductsCount} product(s)."
        );

        return self::SUCCESS;
    }
}

