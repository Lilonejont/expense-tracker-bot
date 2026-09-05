<?php
// parser.php - Parses expense messages in Telegram

/**
 * Parse input message string to extract amount, description, and optional tag.
 *
 * Supported formats:
 * - кофе 350
 * - 350 кофе
 * - такси 900 работа
 * - обед 450.50
 * - обед 450,50
 * - 500
 *
 * @param string $text
 * @return array|null [ 'amount' => float, 'description' => string, 'tag' => string|null ]
 */
function parseExpenseMessage($text) {
    $trimmed = trim($text);
    if (empty($trimmed)) {
        return null;
    }

    // Ignore bot commands starting with /
    if (strpos($trimmed, '/') === 0) {
        return null;
    }

    // Replace commas in decimals with dots
    $normalized = str_replace(',', '.', $trimmed);

    // Split by whitespace
    $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
    if (!$tokens) {
        return null;
    }

    $amountIndex = -1;
    $amount = 0.0;

    // Locate the token that represents the numerical amount
    foreach ($tokens as $idx => $token) {
        // Match numbers like 350, 350.50, 350р, 350руб, 350сом, $50
        if (preg_match('/^(\d+(?:\.\d{1,2})?)(?:р(?:уб)?\.?|сом|c|\$|€)?$/ui', $token, $matches)) {
            $amount = floatval($matches[1]);
            $amountIndex = $idx;
            break;
        }
    }

    if ($amountIndex === -1 || $amount <= 0) {
        return null;
    }

    $beforeTokens = array_slice($tokens, 0, $amountIndex);
    $afterTokens = array_slice($tokens, $amountIndex + 1);

    $description = '';
    $tag = null;

    if (!empty($beforeTokens)) {
        // e.g., "кофе 350" or "такси 900 работа"
        $description = implode(' ', $beforeTokens);
        if (!empty($afterTokens)) {
            $tag = implode(' ', $afterTokens);
        }
    } elseif (!empty($afterTokens)) {
        // e.g., "350 кофе" or "350 кофе работа"
        $description = $afterTokens[0];
        if (count($afterTokens) > 1) {
            $tag = implode(' ', array_slice($afterTokens, 1));
        }
    } else {
        // Just the amount, e.g. "350"
        $description = 'Расход';
    }

    if ($tag !== null) {
        $tag = ltrim($tag, '#');
    }

    return [
        'amount' => round($amount, 2),
        'description' => $description,
        'tag' => $tag
    ];
}
