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
    }
}

namespace {

    define('ROOT', dirname(__DIR__));
    require_once ROOT.'/app/Core/Autoloader.php';
    \App\Core\Autoloader::register();

    use App\Core\Database;
    use App\Core\Migrations;
    use App\Models\LoginAttempt;

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

    putenv('AUTH_MAX_ATTEMPTS=5');
    putenv('AUTH_LOCKOUT_SECONDS=900');
    Database::reset();
    Migrations::up();

    assertTrue(class_exists(LoginAttempt::class), 'LoginAttempt model must exist');

    $attempts = new LoginAttempt;
    $username = strtolower(trim(' Admin '));
    $ip = '203.0.113.10';

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        assertSameValue(false, $attempts->isBlocked($username, $ip), 'failed attempts must stay allowed below the limit');
        $attempts->recordFailure($username, $ip);
    }

    assertTrue($attempts->isBlocked($username, $ip), 'sixth attempt must be blocked after five failures');
    assertSameValue(false, $attempts->isBlocked('admin', '203.0.113.11'), 'a different IP must not inherit the lockout');
    assertSameValue(false, $attempts->isBlocked('other-user', $ip), 'a different username must not inherit the lockout');

    $attempts->clear($username, $ip);
    assertSameValue(false, $attempts->isBlocked($username, $ip), 'successful login must clear prior failures');

    Database::getInstance()->getConnection()->prepare(
        'INSERT INTO login_attempts (username, ip_address, attempted_at) VALUES (?, ?, ?)'
    )->execute([$username, $ip, time() - 901]);
    assertSameValue(false, $attempts->isBlocked($username, $ip), 'expired failures must not block login');
    assertSameValue(0, (int) Database::getInstance()->getConnection()
        ->query('SELECT COUNT(*) FROM login_attempts')
        ->fetchColumn(), 'expired failures must be cleaned up during checks');

    putenv('AUTH_MAX_ATTEMPTS=invalid');
    putenv('AUTH_LOCKOUT_SECONDS=0');
    Database::reset();
    Migrations::up();
    $defaults = new LoginAttempt;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $defaults->recordFailure('default-user', $ip);
    }
    assertTrue($defaults->isBlocked('default-user', $ip), 'invalid limits must fall back to safe defaults');

    echo "PASS: login rate limiting checks\n";
}
