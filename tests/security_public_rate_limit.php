<?php

declare(strict_types=1);

namespace App\Core {

    use PDO;

    class Database
    {
        private static ?self $instance = null;

        private PDO $pdo;

        private function __construct()
        {
            $this->pdo = new PDO('sqlite::memory:');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        public static function getInstance(): self
        {
            return self::$instance ??= new self;
        }

        public static function reset(): void
        {
            self::$instance = new self;
        }

        public function getConnection(): PDO
        {
            return $this->pdo;
        }

        public function query(string $sql, array $params = [])
        {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        }
    }
}

namespace App\Helpers {

    final class RouterTargetHelper
    {
        public static array $resolvedTargets = [];

        public static function resolve(string $target, ?callable $resolver = null): string
        {
            self::$resolvedTargets[] = $target;

            return '198.51.100.10';
        }
    }
}

namespace App\Libraries {

    class RouterOSAPI
    {
        public static int $connectCalls = 0;

        public static array $connectArgs = [];

        public static array $commands = [];

        public static function reset(): void
        {
            self::$connectCalls = 0;
            self::$connectArgs = [];
            self::$commands = [];
        }

        public static function decrypt(string $value): string
        {
            return $value;
        }

        public function connect($ip, $login, $password, $peerName = null): bool
        {
            self::$connectCalls++;
            self::$connectArgs[] = [$ip, $login, $password, $peerName];

            return true;
        }

        public function comm($path, $params = [])
        {
            self::$commands[] = [$path, $params];

            return [];
        }

        public function disconnect(): void {}
    }
}

namespace App\Controllers {

    function getallheaders(): array
    {
        return \PublicStatusRateLimitTestState::$requestHeaders;
    }

    function file_get_contents(string $path): string|false
    {
        if ($path === 'php://input') {
            return \PublicStatusRateLimitTestState::$requestBody;
        }

        return \file_get_contents($path);
    }

    function header(string $header, bool $replace = true, int $responseCode = 0): void
    {
        \PublicStatusRateLimitTestState::$responseHeaders[] = $header;
    }

    function http_response_code(?int $code = null): int
    {
        if ($code !== null) {
            \PublicStatusRateLimitTestState::$statusCode = $code;
        }

        return \PublicStatusRateLimitTestState::$statusCode;
    }
}

namespace {

    define('ROOT', dirname(__DIR__));
    require_once ROOT.'/app/Core/Autoloader.php';
    \App\Core\Autoloader::register();

    use App\Controllers\PublicStatusController;
    use App\Core\Database;
    use App\Core\Migrations;
    use App\Helpers\RouterTargetHelper;
    use App\Libraries\RouterOSAPI;
    use App\Models\ApiRateLimit;

    final class PublicStatusRateLimitTestState
    {
        public static array $requestHeaders = [];

        public static string $requestBody = '';

        public static array $responseHeaders = [];

        public static int $statusCode = 200;

        public static function reset(): void
        {
            self::$requestHeaders = [];
            self::$requestBody = '';
            self::$responseHeaders = [];
            self::$statusCode = 200;
        }
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                'FAIL: %s (expected %s, got %s)',
                $message,
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException('FAIL: '.$message);
        }
    }

    function assertBetween(int $minimum, int $maximum, int $actual, string $message): void
    {
        if ($actual < $minimum || $actual > $maximum) {
            throw new RuntimeException(sprintf(
                'FAIL: %s (expected between %d and %d, got %d)',
                $message,
                $minimum,
                $maximum,
                $actual
            ));
        }
    }

    function responseHeaderValue(string $name): ?string
    {
        foreach (PublicStatusRateLimitTestState::$responseHeaders as $header) {
            if (stripos($header, $name.':') === 0) {
                return trim(substr($header, strlen($name) + 1));
            }
        }

        return null;
    }

    function resetEnvironment(?string $maxAttempts = '1', ?string $windowSeconds = '60'): void
    {
        Database::reset();
        RouterOSAPI::reset();
        RouterTargetHelper::$resolvedTargets = [];
        PublicStatusRateLimitTestState::reset();
        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        putenv($maxAttempts === null ? 'PUBLIC_STATUS_MAX_ATTEMPTS' : 'PUBLIC_STATUS_MAX_ATTEMPTS='.$maxAttempts);
        putenv($windowSeconds === null ? 'PUBLIC_STATUS_WINDOW_SECONDS' : 'PUBLIC_STATUS_WINDOW_SECONDS='.$windowSeconds);
        Migrations::up();
    }

    function insertSession(string $sessionName): void
    {
        Database::getInstance()->query(
            'INSERT INTO routers (session_name, ip_address, username, password, hotspot_name, dns_name, currency, reload_interval, interface, description, quick_access)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$sessionName, 'router.internal.example', 'router-user', '', 'Test Hotspot', '', 'RP', 60, 'ether1', '', 0]
        );
    }

    function countRateLimits(string $scope): int
    {
        return (int) Database::getInstance()
            ->query('SELECT COUNT(*) AS count FROM api_rate_limits WHERE scope = ?', [$scope])
            ->fetch()['count'];
    }

    function runRequest(string $method, ?string $codeUrl = null, array $body = [], array $headers = [], string $remoteAddr = '203.0.113.10'): array
    {
        PublicStatusRateLimitTestState::reset();
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $headers['X-Forwarded-For'] ?? '';
        PublicStatusRateLimitTestState::$requestHeaders = $headers;
        PublicStatusRateLimitTestState::$requestBody = $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR);

        $controller = new PublicStatusController;
        ob_start();
        $controller->check($codeUrl);
        $output = ob_get_clean();

        return [
            'status' => PublicStatusRateLimitTestState::$statusCode,
            'headers' => PublicStatusRateLimitTestState::$responseHeaders,
            'body' => json_decode($output, true),
        ];
    }

    assertTrue(class_exists(ApiRateLimit::class), 'ApiRateLimit model must exist');

    resetEnvironment();
    $pdo = Database::getInstance()->getConnection();
    $rateLimitIndexes = $pdo->query('PRAGMA index_list(api_rate_limits)')->fetchAll();
    $hasExpiryIndex = false;
    foreach ($rateLimitIndexes as $rateLimitIndex) {
        if (($rateLimitIndex['name'] ?? '') === 'idx_api_rate_limits_expires_at') {
            $hasExpiryIndex = true;
            break;
        }

        $indexName = str_replace("'", "''", (string) ($rateLimitIndex['name'] ?? ''));
        $indexColumns = $pdo->query("PRAGMA index_info('{$indexName}')")->fetchAll();
        foreach ($indexColumns as $indexColumn) {
            if (($indexColumn['name'] ?? '') === 'expires_at') {
                $hasExpiryIndex = true;
                break 2;
            }
        }
    }
    assertTrue($hasExpiryIndex, 'api_rate_limits expiry cleanup must be indexed');

    $firstInvalid = runRequest('GET', 'ABC123', [], [
        'X-Mivo-Session' => 'missing-session',
        'X-Forwarded-For' => '198.51.100.25',
    ]);
    assertSameValue(404, $firstInvalid['status'], 'first invalid session request should stay public and return not found');
    assertSameValue(0, RouterOSAPI::$connectCalls, 'invalid session must not reach RouterOS');

    $secondInvalid = runRequest('GET', 'ABC123', [], [
        'X-Mivo-Session' => 'missing-session',
        'X-Forwarded-For' => '198.51.100.99',
    ]);
    $invalidRetryAfter = (int) (responseHeaderValue('Retry-After') ?? 0);
    assertSameValue(429, $secondInvalid['status'], 'second invalid session request from same REMOTE_ADDR must be rate limited');
    assertSameValue(0, RouterOSAPI::$connectCalls, 'rate-limited invalid request must not reach RouterOS');
    assertBetween(1, 60, $invalidRetryAfter, 'blocked invalid request must expose a positive retry window no greater than 60 seconds');
    assertSameValue(1, countRateLimits('ip'), 'invalid session attempts must create a single IP bucket');
    assertSameValue(0, countRateLimits('session'), 'invalid sessions must not create session buckets');

    resetEnvironment();
    $firstMissing = runRequest('GET', 'ABC123', [], [
        'X-Forwarded-For' => '198.51.100.77',
    ]);
    assertSameValue(400, $firstMissing['status'], 'first missing session request should preserve the existing validation failure');
    assertSameValue('Session and Voucher Code are required', $firstMissing['body']['error'] ?? null, 'missing session validation message should stay unchanged');
    assertSameValue(0, RouterOSAPI::$connectCalls, 'missing session must not reach RouterOS');

    $secondMissing = runRequest('GET', 'ABC123', [], [
        'X-Forwarded-For' => '198.51.100.88',
    ]);
    $missingRetryAfter = (int) (responseHeaderValue('Retry-After') ?? 0);
    assertSameValue(429, $secondMissing['status'], 'second missing session request from the same REMOTE_ADDR must be rate limited');
    assertSameValue(0, RouterOSAPI::$connectCalls, 'rate-limited missing session request must not reach RouterOS');
    assertBetween(1, 60, $missingRetryAfter, 'blocked missing session request must expose a positive retry window no greater than 60 seconds');
    assertSameValue(1, countRateLimits('ip'), 'missing session attempts must create a single IP bucket');
    assertSameValue(0, countRateLimits('session'), 'missing session attempts must not create session buckets');

    resetEnvironment();
    insertSession('alpha');
    $allowed = runRequest('POST', null, [
        'session' => 'alpha',
        'code' => 'ABC123',
    ], [], '203.0.113.20');
    assertSameValue(200, $allowed['status'], 'first valid status request should keep its existing success contract');
    assertSameValue(1, RouterOSAPI::$connectCalls, 'first valid status request must reach RouterOS');
    assertSameValue('Voucher Not Found', $allowed['body']['message'] ?? null, 'allowed request should preserve voucher response payload');

    $blocked = runRequest('GET', 'ABC123', [], [
        'X-Mivo-Session' => 'alpha',
    ], '203.0.113.20');
    $blockedRetryAfter = (int) (responseHeaderValue('Retry-After') ?? 0);
    assertSameValue(429, $blocked['status'], 'voucher endpoint must share the public rate limiter');
    assertSameValue(1, RouterOSAPI::$connectCalls, 'blocked voucher request must not create a second RouterOS connection');
    assertBetween(1, 60, $blockedRetryAfter, 'blocked voucher request must expose a positive retry window no greater than 60 seconds');
    assertSameValue(1, countRateLimits('ip'), 'allowed request should persist one IP bucket');
    assertSameValue(1, countRateLimits('session'), 'allowed request should persist one session bucket');

    resetEnvironment('invalid', '0');
    insertSession('defaults');
    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $defaultAllowed = runRequest('POST', null, [
            'session' => 'defaults',
            'code' => 'ABC123',
        ], [], '203.0.113.40');
        assertSameValue(200, $defaultAllowed['status'], 'default fallback must allow the first 30 requests');
        assertSameValue('Voucher Not Found', $defaultAllowed['body']['message'] ?? null, 'default fallback must preserve the status response payload');
        assertSameValue($attempt, RouterOSAPI::$connectCalls, 'default fallback must keep routing each allowed request to RouterOS');
    }
    assertSameValue(1, countRateLimits('ip'), 'default fallback should reuse a single IP bucket across allowed requests');
    assertSameValue(1, countRateLimits('session'), 'default fallback should reuse a single session bucket across allowed requests');

    $defaultBlocked = runRequest('GET', 'ABC123', [], [
        'X-Mivo-Session' => 'defaults',
    ], '203.0.113.40');
    $defaultRetryAfter = (int) (responseHeaderValue('Retry-After') ?? 0);
    assertSameValue(429, $defaultBlocked['status'], 'default fallback must block the 31st request across the shared public routes');
    assertSameValue(30, RouterOSAPI::$connectCalls, 'default fallback must not create a 31st RouterOS connection');
    assertBetween(1, 60, $defaultRetryAfter, 'default fallback retry window must be positive and no greater than 60 seconds');

    resetEnvironment();
    $atomicRateLimit = new ApiRateLimit;
    $firstAtomicResult = $atomicRateLimit->record('ip', 'atomic-ip');
    assertSameValue(0, $firstAtomicResult, 'first record call should consume and allow');

    $secondAtomicResult = $atomicRateLimit->record('ip', 'atomic-ip');
    assertBetween(1, 60, (int) $secondAtomicResult, 'second record call should consume and return a positive retry window no greater than 60 seconds');

    resetEnvironment();
    insertSession('beta');
    $rateLimit = new ApiRateLimit;
    $rateLimit->record('session', 'beta', 60);
    $sessionBlocked = runRequest('POST', null, [
        'session' => 'beta',
        'code' => 'ABC123',
    ], [], '203.0.113.30');
    $sessionRetryAfter = (int) (responseHeaderValue('Retry-After') ?? 0);
    assertSameValue(429, $sessionBlocked['status'], 'existing session bucket must block before RouterOS work');
    assertSameValue(0, RouterOSAPI::$connectCalls, 'session-limited request must not reach RouterOS');
    assertBetween(1, 60, $sessionRetryAfter, 'session-limited request must expose a positive retry window no greater than 60 seconds');

    echo "PASS: public status rate limiting checks\n";
}
