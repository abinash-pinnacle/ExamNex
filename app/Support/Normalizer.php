<?php

namespace App\Support;

class Normalizer
{
    /**
     * Canonical form of a question used for duplicate detection.
     * Lowercases, strips punctuation/symbols, collapses whitespace so that
     * "What is OOP?" and "  what is oop " map to the same key.
     * Mirrors the original normalize.ts; the DB unique index on
     * questions.normalized_text enforces this globally.
     */
    public static function questionText(string $text): string
    {
        $t = \Normalizer::isNormalized($text) ? $text : \Normalizer::normalize($text, \Normalizer::FORM_KC);
        if ($t === false) {
            $t = $text;
        }
        $t = mb_strtolower($t);
        // Drop anything that is not a letter, number or whitespace (Unicode-aware).
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t);
        return trim($t);
    }
}
