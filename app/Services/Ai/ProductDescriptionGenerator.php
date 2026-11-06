<?php

namespace App\Services\Ai;

class ProductDescriptionGenerator
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

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
        $price = !empty($data['price']) ? '₦' . number_format((float)$data['price'], 2) : 'Affordable';
        $keywords = $data['keywords'] ?? '';
        $tone = $data['tone'] ?? 'conversion-focused and punchy';

        $systemInstruction = <<<EOT
You are an expert e-commerce copywriter.
Write a compelling, high-converting Short Description for an online retail store product.
Strict Constraints:
1. Exactly 1 to 2 engaging, persuasive sentences.
2. Return strictly PLAIN TEXT.
3. Absolutely NO markdown (no asterisks, no bullets, no bold tags).
4. Absolutely NO HTML tags or quotation marks wrapping the text.
EOT;

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}\nPrice: {$price}\nTone: {$tone}";
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
        $price = !empty($data['price']) ? '₦' . number_format((float)$data['price'], 2) : '';
        $keywords = $data['keywords'] ?? '';
        $tone = $data['tone'] ?? 'informative, engaging, and professional';

        $systemInstruction = <<<EOT
You are an elite e-commerce product copywriter and retail merchandiser.
Generate a complete, conversion-driven, and richly structured Full Product Description formatted in clean semantic HTML designed for a Quill/WYSIWYG editor.

HTML Formatting Rules:
1. Use <h3> for section headers.
2. Use <p> for introductory paragraphs and descriptive write-ups.
3. Use <ul> and <li> for feature highlights and specifications.
4. Use <strong> to emphasize key specs, feature titles, or notable advantages (e.g. <li><strong>Rich Nutrition:</strong> Fortified with vitamins...</li>).
5. Do NOT include <html>, <head>, or <body> wrapper tags.
6. Do NOT wrap output in markdown code blocks like ```html or ```. Output raw HTML tags directly.

CRITICAL CONTENT INTEGRITY & NO-BLANK-HEADING RULES:
1. NEVER output a heading without content directly underneath it. EVERY heading MUST be followed by at least 1 rich <p> paragraph or a <ul> list with multiple <li> items.
2. If you do not have write-up or details for a section, DO NOT output that heading at all.
3. For any product (groceries, electronics, fashion, beauty, home):
   - Always include an engaging <h3>Overview</h3> with 1–2 descriptive paragraphs.
   - Always include <h3>Key Features & Benefits</h3> with 4–6 bullet points (<ul><li><strong>Feature:</strong> Benefit description...</li></ul>). Even for everyday items like milk or beverages, highlight freshness, packaging, taste, nutritional value, and daily utility.
   - Only include <h3>Specifications</h3> if you can provide at least 3 concrete, realistic specs (e.g. Brand, Net Weight/Volume, Packaging Type, Usage/Storage) formatted as <ul><li><strong>Item:</strong> Value</li></ul>. NEVER leave Specifications empty.
   - End with <h3>Why Choose [Product Name]?</h3> or <h3>Usage & Recommendations</h3> with a persuasive closing paragraph.
4. Absolutely NO empty tags: no <h3></h3>, no <p></p>, no <ul></ul>, no consecutive headings with empty space between them.
EOT;

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}";
        if (!empty($price)) {
            $userPrompt .= "\nPrice: {$price}";
        }
        $userPrompt .= "\nTone: {$tone}";
        if (!empty($keywords)) {
            $userPrompt .= "\nKey Highlights / Must-Include Features: {$keywords}\n(Incorporate these specific highlights naturally into the description write-up and bullet points)";
        }
        $userPrompt .= "\nGenerate the complete structured HTML product description. Remember: no empty headings without write-up.";

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
        $cleaned = preg_replace('/^```(?:html)?\s*/i', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        // 2. Iteratively strip empty tags, whitespace-only tags, and headings with no write-up
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

        // 3. Strip any orphan heading at the very end of the content with no text following it
        $cleaned = preg_replace('/<h([1-6])[^>]*>(?:(?!<\/h\1>).)*?<\/h\1>\s*$/iu', '', $cleaned);

        // 4. Normalize multiple consecutive line breaks
        $cleaned = preg_replace('/\n{3,}/', "\n\n", $cleaned);

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

        $userPrompt = "Product Name: {$productName}\nCategory: {$category}\nPrice: {$price}";
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

        $systemInstruction = <<<EOT
You are an expert e-commerce merchandising and cross-selling AI assistant.
Your task is to analyze a Target Product and select the most logical, complementary, and enticing Related Products from a candidate catalog list.

Selection Rules:
1. Pick complementary accessories, logical add-ons, or close alternatives that a customer would realistically buy alongside or instead of the target product.
2. Select between 2 and {$limit} products from the candidates list.
3. Return ONLY a valid JSON array of objects with the selected product "id" (integer) and a brief 1-phrase "reason" (string).
Format:
[
  {"id": 12, "reason": "Complementary protective case"},
  {"id": 34, "reason": "Frequently bought together"}
]
Strictly return valid JSON. Do not write markdown, code blocks, or explanations outside the JSON array.
EOT;

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

