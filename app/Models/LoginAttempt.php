<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LoginAttempt
{
    private const DEFAULT_MAX_ATTEMPTS = 5;

    private const DEFAULT_LOCKOUT_SECONDS = 900;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function isBlocked(string $username, string $ip): bool
    {
        $now = time();
        $this->cleanup($now);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE username = ? AND ip_address = ? AND attempted_at >= ?'
        );
        $stmt->execute([$this->normalizeUsername($username), trim($ip), $now - $this->lockoutSeconds()]);

        return (int) $stmt->fetchColumn() >= $this->maxAttempts();
    }

    public function recordFailure(string $username, string $ip): void
    {
        $now = time();
        $this->cleanup($now);

        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$this->normalizeUsername($username), trim($ip), $now]);
    }

    public function clear(string $username, string $ip): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE username = ? AND ip_address = ?');
        $stmt->execute([$this->normalizeUsername($username), trim($ip)]);
    }

    private function cleanup(int $now): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $stmt->execute([$now - $this->lockoutSeconds()]);
    }

    private function maxAttempts(): int
    {
        return $this->positiveEnvironmentValue('AUTH_MAX_ATTEMPTS', self::DEFAULT_MAX_ATTEMPTS);
    }

    private function lockoutSeconds(): int
    {
        return $this->positiveEnvironmentValue('AUTH_LOCKOUT_SECONDS', self::DEFAULT_LOCKOUT_SECONDS);
    }

    private function positiveEnvironmentValue(string $name, int $default): int
    {
        $value = getenv($name);
        $parsed = $value === false ? false : filter_var(trim((string) $value), FILTER_VALIDATE_INT);

        return $parsed !== false && $parsed > 0 ? $parsed : $default;
    }

    private function normalizeUsername(string $username): string
    {
        return strtolower(trim($username));
    }
}
