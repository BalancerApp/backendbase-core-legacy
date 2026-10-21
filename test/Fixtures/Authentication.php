<?php

declare(strict_types=1);

namespace Fixtures;

use BackendBase\Domain\User\Model\User;
use const PASSWORD_BCRYPT;
use function array_replace;
use function base64_encode;
use function hash_hmac;
use function json_encode;
use function password_hash;
use function rtrim;
use function strtr;
use function time;

final class Authentication
{
    public const USER_ID = '11111111-1111-4111-8111-111111111111';
    public const EMAIL = 'editor@example.test';
    public const PASSWORD = 'test-password';
    public const SIGNING_KEY = 'd81c8751fdd0a01e62b7acac5bea23a0d7d29beb03e428b863d02376aea628c1';

    public static function user() : User
    {
        return User::create(
            self::USER_ID,
            self::EMAIL,
            'Test',
            'Editor',
            password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
            'editor',
            '2026-01-01 12:00:00'
        );
    }

    public static function token(array $claims = []) : string
    {
        $now = time();
        $header = self::encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::encode(json_encode(array_replace([
            'iss' => 'storage',
            'iat' => $now - 60,
            'nbf' => $now - 60,
            'exp' => $now + 3600,
            'userId' => self::USER_ID,
            'role' => 'editor',
        ], $claims)));
        $signature = hash_hmac('sha256', $header . '.' . $payload, self::SIGNING_KEY, true);

        return $header . '.' . $payload . '.' . self::encode($signature);
    }

    private static function encode(string $value) : string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
