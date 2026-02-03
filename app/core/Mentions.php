<?php
/**
 * Mentions helper
 * Parses @username
 */

class Mentions
{
    /**
     * Extract mentioned usernames
     */
    public static function extract(string $text): array
    {
        preg_match_all('/@([A-Za-z0-9_]+)/', $text, $matches);
        return array_unique($matches[1]);
    }
}
