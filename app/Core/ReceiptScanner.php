<?php

namespace App\Core;

/**
 * Reads a photographed/uploaded receipt and extracts the fields a Variable
 * Expense needs (date, amount, category, description) via the same
 * AI provider configured for the Advisor (Settings > AI Advisor —
 * Anthropic/OpenAI only; see AIClient::visionExtract()). Returns the
 * extracted fields for the user to review in the Add Expense form — this
 * never saves anything itself, so a misread amount or category is caught
 * before it becomes real financial data.
 */
class ReceiptScanner
{
    /**
     * @param string $imagePath Path to the already-saved receipt image on disk.
     * @param string $mediaType e.g. "image/jpeg".
     * @param array $categories Category::all() rows — the model is asked to pick one by name.
     * @return array{ok: bool, amount?: ?float, expense_date?: string, category_id?: ?int, category_name?: ?string, merchant?: ?string, description?: ?string, error?: string}
     */
    public static function extract(string $imagePath, string $mediaType, array $categories): array
    {
        $imageData = @file_get_contents($imagePath);
        if ($imageData === false) {
            return ['ok' => false, 'error' => 'Could not read the uploaded image.'];
        }
        $base64 = base64_encode($imageData);

        $categoryNames = array_map(fn($c) => $c['name'], $categories);
        $systemPrompt = "You are a receipt-reading assistant built into a personal finance tracker. "
            . "You will be shown a photo of a receipt or invoice. Reply with ONLY a single JSON object — "
            . "no markdown fences, no other text before or after it. Fields:\n"
            . "- date: the purchase date as YYYY-MM-DD (your best reading of the receipt; null if genuinely unreadable)\n"
            . "- amount: the final total paid, as a plain number with no currency symbol or thousands separators\n"
            . "- merchant: the store/vendor name, or null\n"
            . "- category: the single best match from exactly this list (copy the spelling exactly): "
            . implode(', ', $categoryNames) . " — use \"Other\" if nothing fits well\n"
            . "- description: a short 3-6 word summary of the purchase, e.g. \"Groceries at Sultan Center\"\n"
            . "If the image is not a receipt/invoice at all, still return your best-guess JSON with amount null.";

        $result = AIClient::visionExtract($systemPrompt, 'Extract this receipt as JSON.', $base64, $mediaType);
        if (!$result['ok']) {
            return $result;
        }

        $data = self::parseJson($result['text']);
        if ($data === null || !isset($data['amount']) || !is_numeric($data['amount'])) {
            return ['ok' => false, 'error' => 'Couldn\'t make out an amount on that receipt. Try a clearer, well-lit photo, or enter it manually.'];
        }

        $amount = round((float) $data['amount'], 3);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Couldn\'t make out an amount on that receipt. Try a clearer, well-lit photo, or enter it manually.'];
        }

        $date = date('Y-m-d');
        if (!empty($data['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['date'])) {
            $date = $data['date'];
        }

        $categoryName = trim((string) ($data['category'] ?? ''));
        $categoryId = self::matchCategory($categoryName, $categories);

        $merchant = !empty($data['merchant']) ? trim((string) $data['merchant']) : null;
        $description = !empty($data['description']) ? trim((string) $data['description']) : $merchant;

        return [
            'ok'            => true,
            'amount'        => $amount,
            'expense_date'  => $date,
            'category_id'   => $categoryId,
            'category_name' => $categoryName !== '' ? $categoryName : null,
            'merchant'      => $merchant,
            'description'   => $description,
        ];
    }

    /** Case-insensitive exact match against the category list, falling back to "Other" (if it exists), else null (left for the user to pick). */
    private static function matchCategory(string $name, array $categories): ?int
    {
        if ($name !== '') {
            foreach ($categories as $cat) {
                if (strcasecmp($cat['name'], $name) === 0) {
                    return (int) $cat['id'];
                }
            }
        }
        foreach ($categories as $cat) {
            if (strcasecmp($cat['name'], 'Other') === 0) {
                return (int) $cat['id'];
            }
        }
        return null;
    }

    /** Some models wrap JSON replies in ```json ... ``` fences despite being told not to — strip those before decoding. */
    private static function parseJson(string $text): ?array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $decoded = json_decode(trim((string) $text), true);
        return is_array($decoded) ? $decoded : null;
    }
}
