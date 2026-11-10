<?php

namespace App\Services\Ai;

use App\Models\StoreSetting;

class ProductDescriptionGenerator
{
    public const DEFAULT_SHORT_SYSTEM_INSTRUCTION = <<<EOT
You are an expert e-commerce copywriter.
Write a short description of the product with current trend tags and not more than 2 sentences.

Strict Constraints:
1. Maximum 2 sentences. Persuasive, engaging, and highlights product value.
2. Naturally include current trend tags / search keywords.
3. Do NOT include or mention any price, currency, or numerical price tag (prices fluctuate and change over time).
4. Return strictly PLAIN TEXT.
5. Absolutely NO markdown (no asterisks, no bullets, no bold tags).
6. Absolutely NO HTML tags or quotation marks wrapping the text.
EOT;

    public const DEFAULT_FULL_SYSTEM_INSTRUCTION = <<<EOT
A product description and product details for ecommerce upload.
example:
<p>24K Pure Gold opens with sweet praline and creamy vanilla. Rose and strawberry give it a soft floral and fruity scent, while amber and musk add warmth as it settles. It suits everyday wear, evenings out, and special occasions.</p>
<h3><strong>Product Details:</strong></h3>
<ul>
<li><strong>Brand:</strong> Lonkoom</li>
<li><strong>Fragrance type:</strong> Eau de Parfum</li>
<li><strong>Size:</strong> 100 ml</li>
<li><strong>For:</strong> Women</li>
<li><strong>Fragrance family:</strong> Floral and fruity</li>
<li><strong>Top notes:</strong> Praline and vanilla</li>
<li><strong>Heart notes:</strong> Rose and strawberry</li>
<li><strong>Base notes:</strong> Musk, amber, and vanilla</li>
</ul>

Strict Rules:
1. Start with a 2-3 sentence product description inside a <p> tag (do NOT include a "Description:" heading).
2. Follow with <h3><strong>Product Details:</strong></h3> and a <ul> bullet list.
3. Every <li> item under Product Details: MUST have the label in <strong> tags WITH a colon followed by the value (e.g. <li><strong>Brand:</strong> Surwin</li>).
4. Dynamically select attribute labels under Product Details: that fit the specific product and category (e.g. Fragrance notes for perfumes, Beverage type/Flavor/Size for drinks, Model/Material for stationery or electronics, Key ingredients/Skin type for beauty). Never include irrelevant labels like "Beverage type" or "Fragrance family" if the product is not a beverage or fragrance.
5. Do NOT include product price.
EOT;

    public const DEFAULT_RELATED_SYSTEM_INSTRUCTION = <<<EOT
You are an expert e-commerce merchandising and cross-selling AI assistant.
Your task is to analyze a Target Product and select the most logical, complementary, and enticing Related Products from a candidate catalog list.

Selection Rules:
1. Pick complementary accessories, logical add-ons, or close alternatives that a customer would realistically buy alongside or instead of the target product.
2. Prioritize items that genuinely complement or belong to the product's category and usage context.
3. Select up to {limit} products from the candidates list.
4. Return ONLY a valid JSON array of objects with the selected product "id" (integer) and a brief 1-phrase "reason" (string).
Format:
[
  {"id": 12, "reason": "Complementary accessory"},
  {"id": 34, "reason": "Frequently bought together"}
]
Strictly return valid JSON. Do not write markdown, code blocks, or explanations outside the JSON array.
EOT;

    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Get effective system instruction for short description.
     */
    public function getShortSystemInstruction(): string
    {
        $custom = StoreSetting::get('ai_prompt_short_description');
        return !empty(trim((string)$custom)) ? trim($custom) : self::DEFAULT_SHORT_SYSTEM_INSTRUCTION;
    }

    /**
     * Get effective system instruction for full description.
     */
    public function getFullSystemInstruction(): string
    {
        $custom = StoreSetting::get('ai_prompt_full_description');
        return !empty(trim((string)$custom)) ? trim($custom) : self::DEFAULT_FULL_SYSTEM_INSTRUCTION;
    }

    /**
     * Get effective system instruction for related products suggestions.
     */
    public function getRelatedSystemInstruction(int $limit = 5): string
    {
        $custom = StoreSetting::get('ai_prompt_related_products');
        $template = !empty(trim((string)$custom)) ? trim($custom) : self::DEFAULT_RELATED_SYSTEM_INSTRUCTION;
        return str_replace('{limit}', (string)$limit, $template);
    }

    /**
     * Generate a concise, conversion-optimized short description in plain text.
     */
    public function generateShortDescription(array $data): string
    {
        $provider = $this->aiManager->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("AI is currently disabled or no valid API key is configured. Please check Store Settings.");
        }

        $productName = $data['product_name'] ?? 'Product';
        $category = $data['category_name'] ?? 'General';
        $brand = $data['brand_name'] ?? '';
        $keywords = $data['keywords'] ?? '';
        $tone = $data['tone'] ?? 'conversion-focused and punchy';

        $systemInstruction = $this->getShortSystemInstruction();

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}";
        if (!empty($brand)) {
            $userPrompt .= "\nBrand: {$brand}";
        }
        $userPrompt .= "\nTone: {$tone}";
        if (!empty($keywords)) {
            $userPrompt .= "\nKey Highlights to Emphasize: {$keywords}";
        }

        $text = $provider->chat([
            ['role' => 'user', 'content' => $userPrompt]
        ], [
            'system_instruction' => $systemInstruction,
            'temperature'        => 0.7,
            'max_tokens'         => 200,
        ]);

        return $this->cleanPlainText($text);
    }

    /**
     * Generate a rich, structured full product description formatted in semantic HTML for Quill.
     */
    public function generateFullDescription(array $data): string
    {
        $provider = $this->aiManager->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("AI is currently disabled or no valid API key is configured. Please check Store Settings.");
        }

        $productName = $data['product_name'] ?? 'Product';
        $category = $data['category_name'] ?? 'General';
        $brand = $data['brand_name'] ?? '';
        $keywords = $data['keywords'] ?? '';

        $systemInstruction = $this->getFullSystemInstruction();

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}";
        if (!empty($brand)) {
            $userPrompt .= "\nBrand: {$brand}";
        }
        if (!empty($keywords)) {
            $userPrompt .= "\nKey Highlights: {$keywords}";
        }
        $userPrompt .= "\n\nStrictly follow the HTML structure from the system instruction example: a <p> description paragraph (no 'Description:' label), followed by <h3><strong>Product Details:</strong></h3> and a <ul> list where every item is formatted as <li><strong>Label:</strong> Value</li> using attributes tailored specifically to this product type. Do not include price.";

        $html = $provider->chat([
            ['role' => 'user', 'content' => $userPrompt]
        ], [
            'system_instruction' => $systemInstruction,
            'temperature'        => 0.7,
            'max_tokens'         => 1200,
        ]);

        return $this->cleanHtml($html);
    }

    protected function cleanPlainText(string $text): string
    {
        $cleaned = trim($text);
        // Strip markdown backticks, fences, quotes
        $cleaned = preg_replace('/^["\']|["\']$/', '', $cleaned);
        $cleaned = str_replace(['**', '__', '`'], '', $cleaned);
        return strip_tags(trim($cleaned));
    }

    protected function cleanHtml(string $html): string
    {
        $cleaned = trim($html);

        // 1. Remove markdown code blocks if provider added them
        $cleaned = preg_replace('/^```(?:html|text|markdown)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        // 2. If the AI returned plain text, simple <p>/<br> lines, or "Product Details" without a <ul>/<ol> list,
        //    convert into <p> description + <h3><strong>Product Details:</strong></h3> + <ul><li><strong>Label:</strong> Value</li></ul>
        if (
            !preg_match('/<(?:h[1-6]|ul|ol|li|div|table)\b/i', $cleaned) ||
            (preg_match('/Product\s+details/i', $cleaned) && !preg_match('/<(?:ul|ol|li)\b/i', $cleaned))
        ) {
            $text = preg_replace('/<\/(?:p|h[1-6]|div)>|<br\s*\/?>/i', "\n", $cleaned);
            $text = strip_tags($text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace(['**', '__', '`'], '', $text);

            $lines = preg_split('/\r\n|\r|\n/', trim($text));
            $htmlParts = [];
            $inDetails = false;
            $detailItems = [];

            foreach ($lines as $line) {
                $trimmedLine = trim($line);
                $trimmedLine = preg_replace('/^[-•*]\s*/u', '', $trimmedLine);
                if ($trimmedLine === '' || preg_match('/^(?:Product\s+)?Description\s*:?$/i', $trimmedLine)) {
                    continue;
                }

                if (preg_match('/^Product\s+details\s*:?$/i', $trimmedLine)) {
                    $inDetails = true;
                    continue;
                }

                if (!$inDetails) {
                    $trimmedLine = preg_replace('/^(?:Product\s+)?Description\s*:\s*/i', '', $trimmedLine);
                    $htmlParts[] = '<p>' . htmlspecialchars($trimmedLine, ENT_NOQUOTES, 'UTF-8') . '</p>';
                } else {
                    if (str_contains($trimmedLine, ':')) {
                        [$label, $val] = explode(':', $trimmedLine, 2);
                        $detailItems[] = '<li><strong>' . htmlspecialchars(trim($label), ENT_NOQUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars(trim($val), ENT_NOQUOTES, 'UTF-8') . '</li>';
                    } else {
                        $detailItems[] = '<li>' . htmlspecialchars($trimmedLine, ENT_NOQUOTES, 'UTF-8') . '</li>';
                    }
                }
            }

            if (!empty($detailItems)) {
                $htmlParts[] = '<h3><strong>Product Details:</strong></h3>';
                $htmlParts[] = '<ul>' . implode('', $detailItems) . '</ul>';
            }

            return implode('', $htmlParts);
        }

        // 3. Iteratively strip empty tags, whitespace-only tags, and headings with no write-up
        do {
            $before = $cleaned;

            // Strip empty headings: e.g. <h3></h3>, <h3>&nbsp;</h3>, <h3><br></h3>
            $cleaned = preg_replace('/<h([1-6])[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/h\1>/iu', '', $cleaned);

            // Strip empty paragraphs, lists, list items
            $cleaned = preg_replace('/<p[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>/iu', '', $cleaned);
            $cleaned = preg_replace('/<li[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/li>/iu', '', $cleaned);
            $cleaned = preg_replace('/<ul[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/ul>/iu', '', $cleaned);
            $cleaned = preg_replace('/<ol[^>]*>(?:\s|&nbsp;|<br\s*\/?>)*<\/ol>/iu', '', $cleaned);

            // Strip headings immediately followed by another heading (orphan headings without write-up)
            $cleaned = preg_replace('/<h([1-6])[^>]*>(?:(?!<\/h\1>).)*?<\/h\1>\s*(?=<h[1-6][^>]*>)/iu', '', $cleaned);
        } while ($before !== $cleaned);

        // 4. Strip any orphan heading at the very end of the content with no text following it
        $cleaned = preg_replace('/<h([1-6])[^>]*>(?:(?!<\/h\1>).)*?<\/h\1>\s*$/iu', '', $cleaned);

        // 5. Strip any "Description:" or "Product Description:" heading/label (storefront already displays "Product Description")
        $cleaned = preg_replace('/<(?:p|h[1-6])[^>]*>\s*(?:<(?:strong|b)>)?\s*(?:Product\s+)?Description\s*:?\s*(?:<\/(?:strong|b)>)?\s*<\/(?:p|h[1-6])>\s*/iu', '', $cleaned);
        $cleaned = preg_replace('/<p([^>]*)>\s*(?:<(?:strong|b)>)?\s*(?:Product\s+)?Description\s*:\s*(?:<\/(?:strong|b)>)?\s*:?\s*/iu', '<p$1>', $cleaned);

        // 6. Normalize "Product details" / "Product Details:" heading to bold Heading 3: <h3><strong>Product Details:</strong></h3>
        $cleaned = preg_replace('/<(?:p|h[1-6])[^>]*>\s*(?:<(?:strong|b)>)?\s*Product\s+details\s*:?\s*(?:<\/(?:strong|b)>)?\s*:?\s*<\/(?:p|h[1-6])>/iu', '<h3><strong>Product Details:</strong></h3>', $cleaned);

        // 7. Ensure bold labels inside <li> items always include a colon (e.g. <li><strong>Brand</strong> Surwin</li> -> <li><strong>Brand:</strong> Surwin</li>)
        $cleaned = preg_replace('/<li>\s*<strong>([^<:]+?):?<\/strong>\s*:?\s+(?=\S)/iu', '<li><strong>$1:</strong> ', $cleaned);
        $cleaned = preg_replace('/<li>\s*([^<:\n]{1,35}):\s+/u', '<li><strong>$1:</strong> ', $cleaned);

        // 8. Collapse whitespace/newlines between block tags so Quill does not insert extra empty lines or crash List blot build
        $cleaned = preg_replace('/>\s+</', '><', $cleaned);

        return trim($cleaned);
    }

    /**
     * Generate conversion-focused, search-optimized SEO metadata (Title, Description, Keywords).
     */
    public function generateSeoMetadata(array $data): array
    {
        $provider = $this->aiManager->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("AI is currently disabled or no valid API key is configured. Please check Store Settings.");
        }

        $productName = $data['product_name'] ?? 'Product';
        $category = $data['category_name'] ?? 'General';
        $brand = $data['brand_name'] ?? '';
        $price = !empty($data['price']) ? '₦' . number_format((float)$data['price'], 2) : 'Affordable';
        $keywords = $data['keywords'] ?? '';

        $systemInstruction = <<<EOT
You are an expert E-Commerce SEO Specialist.
Generate optimized search engine metadata for an online retail product.
Return strictly valid, raw JSON (no markdown formatting, no ```json code blocks, no trailing comments).
JSON Structure:
{
  "meta_title": "Concise, compelling SEO title under 60 characters with high search intent",
  "meta_description": "High-converting meta description between 140 and 155 characters summarizing key value and inviting clicks",
  "meta_keywords": "5 to 8 targeted comma-separated search terms"
}
Strict Rules:
1. meta_title MUST be 60 characters or fewer.
2. meta_description MUST be 160 characters or fewer (ideal: 140-155 characters).
3. Do NOT include markdown syntax or backticks in the response.
EOT;

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}";
        if (!empty($brand)) {
            $userPrompt .= "\nBrand: {$brand}";
        }
        $userPrompt .= "\nPrice: {$price}";
        if (!empty($keywords)) {
            $userPrompt .= "\nKey Highlights to Include: {$keywords}";
        }

        $rawResponse = $provider->chat([
            ['role' => 'user', 'content' => $userPrompt]
        ], [
            'system_instruction' => $systemInstruction,
            'temperature'        => 0.5,
            'max_tokens'         => 300,
        ]);

        return $this->parseSeoJson($rawResponse, $productName, $keywords);
    }

    /**
     * Parse raw AI output into clean, reliable SEO metadata.
     */
    protected function parseSeoJson(string $raw, string $fallbackName, string $fallbackKeywords): array
    {
        // Strip markdown backticks if present
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (is_array($decoded) && !empty($decoded['meta_title'])) {
            return [
                'meta_title'       => mb_substr(strip_tags((string)($decoded['meta_title'] ?? $fallbackName)), 0, 70),
                'meta_description' => mb_substr(strip_tags((string)($decoded['meta_description'] ?? '')), 0, 160),
                'meta_keywords'    => mb_substr(strip_tags((string)($decoded['meta_keywords'] ?? '')), 0, 255),
            ];
        }

        // Fallback regex parser if AI provided partial or malformed JSON
        $metaTitle = '';
        $metaDesc = '';
        $metaKeys = '';

        if (preg_match('/"meta_title"\s*:\s*"([^"]+)"/i', $raw, $m)) {
            $metaTitle = $m[1];
        }
        if (preg_match('/"meta_description"\s*:\s*"([^"]+)"/i', $raw, $m)) {
            $metaDesc = $m[1];
        }
        if (preg_match('/"meta_keywords"\s*:\s*"([^"]+)"/i', $raw, $m)) {
            $metaKeys = $m[1];
        }

        return [
            'meta_title'       => mb_substr(strip_tags($metaTitle ?: "Buy {$fallbackName} Online | Best Deals"), 0, 70),
            'meta_description' => mb_substr(strip_tags($metaDesc ?: "Shop {$fallbackName} online at best prices. Fast nationwide delivery and authentic quality guaranteed."), 0, 160),
            'meta_keywords'    => mb_substr(strip_tags($metaKeys ?: ($fallbackKeywords ?: "{$fallbackName}, buy online, online store")), 0, 255),
        ];
    }

    /**
     * Given a target product and candidate catalog products, use AI to select the top related items.
     *
     * @param array $targetProduct ['name', 'category_name', 'price', 'description']
     * @param array $candidates list of ['id', 'name', 'category_name', 'price']
     * @param int $limit max number of related items to pick (default 5)
     * @return array list of items like [['id' => 12, 'reason' => 'Complementary accessory']]
     */
    public function suggestRelatedProducts(array $targetProduct, array $candidates, int $limit = 5): array
    {
        if (empty($candidates)) {
            return [];
        }

        $provider = $this->aiManager->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("AI is currently disabled or no valid API key is configured. Please check Store Settings.");
        }

        $productName = $targetProduct['name'] ?? 'Target Product';
        $categoryName = $targetProduct['category_name'] ?? 'General';
        $description = mb_substr(strip_tags((string)($targetProduct['description'] ?? '')), 0, 300);

        $candidateSummaries = [];
        $validCandidateIds = [];
        foreach ($candidates as $cand) {
            $id = (int) $cand['id'];
            $validCandidateIds[$id] = true;
            $candidateSummaries[] = [
                'id'       => $id,
                'name'     => $cand['name'],
                'category' => $cand['category_name'] ?? 'General',
                'price'    => !empty($cand['price']) ? '₦' . number_format((float)$cand['price'], 2) : '',
            ];
        }

        $candidatesJson = json_encode($candidateSummaries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $systemInstruction = $this->getRelatedSystemInstruction($limit);

        $userPrompt = "Target Product:\nName: {$productName}\nCategory: {$categoryName}";
        if (!empty($description)) {
            $userPrompt .= "\nSummary: {$description}";
        }
        $userPrompt .= "\n\nCandidate Products (JSON):\n{$candidatesJson}\n\nSelect up to {$limit} related products in order of relevance.";

        $rawResponse = $provider->chat([
            ['role' => 'user', 'content' => $userPrompt]
        ], [
            'system_instruction' => $systemInstruction,
            'temperature'        => 0.4,
            'max_tokens'         => 500,
        ]);

        return $this->parseRelatedProductsJson($rawResponse, $validCandidateIds);
    }

    /**
     * Parse and sanitize related products selection from AI.
     */
    protected function parseRelatedProductsJson(string $raw, array $validCandidateIds): array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $decoded = json_decode($clean, true);
        $results = [];

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (is_numeric($item)) {
                    $id = (int)$item;
                    if (isset($validCandidateIds[$id])) {
                        $results[] = ['id' => $id, 'reason' => 'AI recommended'];
                    }
                } elseif (is_array($item) && isset($item['id'])) {
                    $id = (int)$item['id'];
                    if (isset($validCandidateIds[$id])) {
                        $results[] = [
                            'id'     => $id,
                            'reason' => strip_tags((string)($item['reason'] ?? 'Recommended complement')),
                        ];
                    }
                }
            }

            if (!empty($results)) {
                return $results;
            }
        }

        // Fallback regex in case JSON was slightly corrupted
        if (preg_match_all('/"id"\s*:\s*(\d+)/i', $raw, $matches)) {
            foreach ($matches[1] as $matchedId) {
                $id = (int)$matchedId;
                if (isset($validCandidateIds[$id]) && !in_array($id, array_column($results, 'id'), true)) {
                    $results[] = ['id' => $id, 'reason' => 'AI recommended'];
                }
            }
        }

        return $results;
    }
}

