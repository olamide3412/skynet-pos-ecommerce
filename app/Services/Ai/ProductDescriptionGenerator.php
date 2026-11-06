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
}

