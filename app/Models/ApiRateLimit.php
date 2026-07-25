<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ApiRateLimit
{
    private const DEFAULT_MAX_ATTEMPTS = 30;

    private const DEFAULT_WINDOW_SECONDS = 60;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function check(string $scope, string $identifier, ?int $maxAttempts = null): int
    {
        $now = time();
        $this->cleanup($now);

        $stmt = $this->pdo->prepare('SELECT attempts, expires_at FROM api_rate_limits WHERE scope = ? AND identifier = ?');
        $stmt->execute([$scope, $identifier]);
        $rateLimit = $stmt->fetch();

        if (! $rateLimit) {
            return 0;
        }

        if ((int) $rateLimit['attempts'] < $this->positiveInt($maxAttempts, 'PUBLIC_STATUS_MAX_ATTEMPTS', self::DEFAULT_MAX_ATTEMPTS)) {
            return 0;
        }

        return max(1, (int) $rateLimit['expires_at'] - $now);
    }

    public function record(string $scope, string $identifier, ?int $windowSeconds = null): int
    {
        $now = time();
        $expiresAt = $now + $this->positiveInt($windowSeconds, 'PUBLIC_STATUS_WINDOW_SECONDS', self::DEFAULT_WINDOW_SECONDS);
        $maxAttempts = $this->positiveInt(null, 'PUBLIC_STATUS_MAX_ATTEMPTS', self::DEFAULT_MAX_ATTEMPTS);
        $this->cleanup($now);

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_rate_limits (scope, identifier, attempts, expires_at, updated_at)
             VALUES (?, ?, 1, ?, ?)
             ON CONFLICT(scope, identifier) DO UPDATE SET
                 attempts = CASE
                     WHEN api_rate_limits.expires_at <= excluded.updated_at THEN 1
                     ELSE api_rate_limits.attempts + 1
                 END,
                 expires_at = CASE
                     WHEN api_rate_limits.expires_at <= excluded.updated_at THEN excluded.expires_at
                     ELSE api_rate_limits.expires_at
                 END,
                 updated_at = excluded.updated_at
             RETURNING attempts, expires_at'
        );
        $stmt->execute([$scope, $identifier, $expiresAt, $now]);
        $rateLimit = $stmt->fetch();

        if (! $rateLimit) {
            return 0;
        }

        if ((int) $rateLimit['attempts'] <= $maxAttempts) {
            return 0;
        }

        return max(1, (int) $rateLimit['expires_at'] - $now);
    }

    public function cleanup(?int $now = null): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_rate_limits WHERE expires_at <= ?');
        $stmt->execute([$now ?? time()]);
    }

    private function positiveInt(?int $value, string $envName, int $default): int
    {
        if ($value !== null && $value > 0) {
            return $value;
        }

        $envValue = getenv($envName);
        if ($envValue === false) {
            return $default;
        }

        $parsed = filter_var(trim((string) $envValue), FILTER_VALIDATE_INT);

        return $parsed !== false && $parsed > 0 ? $parsed : $default;
    }
}
