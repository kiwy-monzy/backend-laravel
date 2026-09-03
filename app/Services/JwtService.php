<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

class JwtService
{
    public function issue(string $userId, string $username, string $role): string
    {
        $now = time();
        $payload = [
            'sub' => $userId,
            'username' => $username,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $this->ttl(),
        ];
        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decode(string $token): ?array
    {
        try {
            return (array) JWT::decode($token, new Key($this->secret(), 'HS256'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Read through the config layer, not env().
     *
     * After `config:cache` — which the deploy runbook runs on every deploy —
     * the .env file is no longer read and env('JWT_SECRET') is null. Reading
     * env() here therefore fell through to a hard-coded default and signed
     * every token with a publicly known secret. A missing secret must fail
     * loudly rather than silently sign with a guessable one.
     */
    private function secret(): string
    {
        $secret = (string) config('jwt.secret', '');

        if ($secret === '') {
            throw new RuntimeException('JWT_SECRET is not set. Refusing to sign tokens with a default secret.');
        }

        return $secret;
    }

    private function ttl(): int
    {
        return (int) config('jwt.ttl', 86400);
    }
}
