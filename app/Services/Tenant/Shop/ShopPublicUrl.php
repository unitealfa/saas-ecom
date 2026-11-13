<?php

namespace App\Services\Tenant\Shop;

use Illuminate\Validation\ValidationException;

final class ShopPublicUrl
{
    public static function assertSafe(string $url, string $field): void
    {
        $parts = parse_url($url);
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || filter_var($url, FILTER_VALIDATE_URL) === false || strlen($url) > 255) {
            throw ValidationException::withMessages([$field => 'URL publique HTTP(S) invalide.']);
        }
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            foreach (array_keys($query) as $key) {
                if (preg_match('/(?:secret|password|token|api[_-]?key|access[_-]?key|signature|auth)/i', (string) $key)) {
                    throw ValidationException::withMessages([$field => 'Ne publiez pas de clé ou jeton secret dans une URL.']);
                }
            }
        }
    }
}
