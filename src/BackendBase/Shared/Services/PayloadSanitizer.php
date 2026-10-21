<?php

declare(strict_types=1);

namespace BackendBase\Shared\Services;

use const FILTER_SANITIZE_EMAIL;
use const PASSWORD_ARGON2ID;
use function array_key_exists;
use function filter_var;
use function is_array;
use function is_int;
use function is_string;
use function password_hash;
use function preg_replace;
use function strip_tags;
use function strtr;

class PayloadSanitizer
{
    public static function sanitize(?array $payload, ?array $allowHtml = [], ?string $keyValue = '$') : array
    {
        if ($payload === null) {
            return [];
        }
        $sanitizedPayload = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $key = self::sanitizeString($key, true);
            }
            $currentKeyValue = $keyValue . '.' . $key;
            if (array_key_exists($currentKeyValue, $allowHtml)) {
                $sanitizedPayload[$key] = TagAndAttributeRemover::cleanHtml(
                    $value,
                    $allowHtml[$currentKeyValue]['allowedTags'],
                    $allowHtml[$currentKeyValue]['urlPrefixes'] ?? 'https'
                );
                continue;
            }
            if (is_int($value)) {
                $sanitizedPayload[$key] =  $value;
                continue;
            }
            if ($key === 'email') {
                $sanitizedPayload[$key] =  filter_var($value, FILTER_SANITIZE_EMAIL);
                continue;
            }
            if ($key === 'password') {
                $sanitizedPayload['passwordHash']     =  password_hash($payload['password'], PASSWORD_ARGON2ID);
                $sanitizedPayload['passwordHashAlgo'] = PASSWORD_ARGON2ID;
                continue;
            }
            if (is_string($value)) {
                $sanitizedPayload[$key] =  self::sanitizeString($value);
                continue;
            }
            if (is_array($value)) {
                $sanitizedPayload[$key] =  self::sanitize($value, $allowHtml, $keyValue . '.' . $key);
                continue;
            }
            $sanitizedPayload[$key] = $value;
        }

        return $sanitizedPayload;
    }

    private static function sanitizeString(string $value, bool $encodeQuotes = false): string
    {
        if ($encodeQuotes) {
            $value = strtr($value, ["'" => '&#39;', '"' => '&#34;']);
        }

        // The legacy filter also strips tags starting with whitespace after "<".
        return strip_tags(preg_replace('/<(?=[\x09-\x0D\x20])/', '<x', $value));
    }
}
