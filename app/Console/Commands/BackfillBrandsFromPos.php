<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariantMapping;
use App\Models\SyncedPosItem;
use App\Services\Brands\PosBrandResolver;
use Illuminate\Console\Command;

class BackfillBrandsFromPos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brands:backfill-from-pos
                            {--dry-run : Preview brand assignments without writing to the database}
                            {--overwrite : Overwrite existing product brands when POS item has a different brand}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill and link eCommerce product brands from synced POS items';

    /**
     * Execute the console command.
     */
    public function handle(PosBrandResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        if ($dryRun) {
            $this->info('Running in --dry-run mode. No database changes will be persisted.');
        }

        $cache = [];
        $brandsBefore = Brand::count();
        $updatedSimple = 0;
        $updatedVariantParents = 0;
        $skippedExisting = 0;
        $missingPosBrand = 0;

        // 1. Simple POS-linked products
        $simpleProducts = Product::whereNotNull('pos_item_id')->get();
        foreach ($simpleProducts as $product) {
            if (! $overwrite && ! empty($product->brand_id)) {
                $skippedExisting++;
                continue;
            }

            $synced = SyncedPosItem::where('pos_item_id', $product->pos_item_id)->first();
            if (! $synced || (empty($synced->pos_brand_id) && empty($synced->pos_brand_name))) {
                $missingPosBrand++;
                continue;
            }

            if ($dryRun) {
                $updatedSimple++;
                continue;
            }

            $brandId = $resolver->resolve(
                $synced->pos_brand_id ? (int) $synced->pos_brand_id : null,
                $synced->pos_brand_name,
                $cache,
                true
            );

            if ($brandId && (int) $product->brand_id !== (int) $brandId) {
                $product->update(['brand_id' => $brandId]);
                $updatedSimple++;
            }
        }

        // 2. Variant parent products linked via ProductVariantMapping
        $mappings = ProductVariantMapping::with(['product', 'syncedPosItem'])
            ->whereNotNull('pos_item_id')
            ->get();

        foreach ($mappings as $mapping) {
            $parent = $mapping->product;
            $synced = $mapping->syncedPosItem;
            if (! $parent || ! $synced) {
                continue;
            }

            if (! $overwrite && ! empty($parent->brand_id)) {
                $skippedExisting++;
                continue;
            }

            if (empty($synced->pos_brand_id) && empty($synced->pos_brand_name)) {
                continue;
            }

            if ($dryRun) {
                $updatedVariantParents++;
                continue;
            }

            $brandId = $resolver->resolve(
                $synced->pos_brand_id ? (int) $synced->pos_brand_id : null,
                $synced->pos_brand_name,
                $cache,
                true
            );

            if ($brandId && (int) $parent->brand_id !== (int) $brandId) {
                $parent->update(['brand_id' => $brandId]);
                $updatedVariantParents++;
            }
        }

        $brandsCreated = max(0, Brand::count() - $brandsBefore);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Mode', $dryRun ? 'DRY RUN' : ($overwrite ? 'LIVE (Overwrite)' : 'LIVE (Fill Empty)')],
                ['Brands Created', $brandsCreated],
                ['Simple Products Updated', $updatedSimple],
                ['Variant Parent Products Updated', $updatedVariantParents],
                ['Skipped (Already Had Brand)', $skippedExisting],
                ['Skipped (No POS Brand Metadata)', $missingPosBrand],
            ]
        );

        return self::SUCCESS;
    }
}

