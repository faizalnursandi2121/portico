<?php

declare(strict_types=1);

const SOURCE_ROOT = __DIR__.'/..';
const VALID_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0wAAAABJRU5ErkJggg==';
const MAX_LOGO_BYTES = 5242880;
const MIXED_CASE_LOGO_ID = 'MiXeD42';

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$message);
    }
}

function recursiveCopy(string $source, string $destination): void
{
    if (is_link($source)) {
        $target = readlink($source);
        if ($target === false || ! symlink($target, $destination)) {
            throw new RuntimeException('Failed to copy symlink: '.$source);
        }

        return;
    }

    if (is_file($source)) {
        $parent = dirname($destination);
        if (! is_dir($parent) && ! mkdir($parent, 0777, true) && ! is_dir($parent)) {
            throw new RuntimeException('Failed to create directory: '.$parent);
        }

        if (! copy($source, $destination)) {
            throw new RuntimeException('Failed to copy file: '.$source);
        }

        return;
    }

    if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
        throw new RuntimeException('Failed to create directory: '.$destination);
    }

    $items = scandir($source);
    if ($items === false) {
        throw new RuntimeException('Failed to read directory: '.$source);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        recursiveCopy($source.'/'.$item, $destination.'/'.$item);
    }
}

function recursiveRemove(string $path): void
{
    if (! file_exists($path) && ! is_link($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        unlink($path);

        return;
    }

    $items = scandir($path);
    if ($items === false) {
        throw new RuntimeException('Failed to read directory: '.$path);
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        recursiveRemove($path.'/'.$item);
    }

    rmdir($path);
}

function makeTempDir(string $prefix): string
{
    $path = sys_get_temp_dir().'/'.$prefix.'-'.bin2hex(random_bytes(6));
    if (! mkdir($path, 0777, true) && ! is_dir($path)) {
        throw new RuntimeException('Failed to create temp directory: '.$path);
    }

    return $path;
}

function listRelativeFiles(string $root): array
{
    if (! file_exists($root)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $files = [];
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $files[] = substr($item->getPathname(), strlen($root) + 1);
        }
    }

    sort($files);

    return $files;
}

function openSandboxDatabase(string $sandboxRoot): PDO
{
    $pdo = new PDO('sqlite:'.$sandboxRoot.'/app/Database/database.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function createSandbox(bool $symlinkLogos = false): array
{
    $sandboxRoot = makeTempDir('restore-sandbox');
    recursiveCopy(SOURCE_ROOT.'/app', $sandboxRoot.'/app');
    @unlink($sandboxRoot.'/app/Database/database.sqlite');

    if (! is_dir($sandboxRoot.'/public/uploads') && ! mkdir($sandboxRoot.'/public/uploads', 0777, true) && ! is_dir($sandboxRoot.'/public/uploads')) {
        throw new RuntimeException('Failed to create sandbox uploads directory');
    }

    $escapeRoot = null;
    if ($symlinkLogos) {
        $escapeRoot = makeTempDir('restore-escape');
        if (! mkdir($escapeRoot.'/logos', 0777, true) && ! is_dir($escapeRoot.'/logos')) {
            throw new RuntimeException('Failed to create symlink target');
        }
        if (! symlink($escapeRoot.'/logos', $sandboxRoot.'/public/uploads/logos')) {
            throw new RuntimeException('Failed to create sandbox symlink');
        }
    } elseif (! mkdir($sandboxRoot.'/public/uploads/logos', 0777, true) && ! is_dir($sandboxRoot.'/public/uploads/logos')) {
        throw new RuntimeException('Failed to create sandbox logos directory');
    }

    return [
        'root' => $sandboxRoot,
        'escape_root' => $escapeRoot,
    ];
}

function cleanupSandbox(array $sandbox): void
{
    recursiveRemove($sandbox['root']);
    if (is_string($sandbox['escape_root'])) {
        recursiveRemove($sandbox['escape_root']);
    }
}

function writeFixtureFile(string $rawBackup): string
{
    $path = tempnam(sys_get_temp_dir(), 'restore-fixture-');
    if ($path === false) {
        throw new RuntimeException('Failed to create fixture file');
    }

    if (file_put_contents($path, $rawBackup) === false) {
        throw new RuntimeException('Failed to write fixture file');
    }

    return $path;
}

function writeSandboxFiles(string $sandboxRoot, array $files): void
{
    foreach ($files as $file) {
        $relativePath = ltrim((string) ($file['path'] ?? ''), '/');
        if ($relativePath === '') {
            throw new RuntimeException('Sandbox setup file path is required');
        }

        $targetPath = $sandboxRoot.'/'.$relativePath;
        $parent = dirname($targetPath);
        if (! is_dir($parent) && ! mkdir($parent, 0777, true) && ! is_dir($parent)) {
            throw new RuntimeException('Failed to create sandbox setup directory: '.$parent);
        }

        $contents = $file['contents'] ?? '';
        if (file_put_contents($targetPath, $contents) === false) {
            throw new RuntimeException('Failed to write sandbox setup file: '.$targetPath);
        }
    }
}

function readFlash(string $sessionDir, string $sessionId): ?array
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $currentSavePath = ini_get('session.save_path');
    session_save_path($sessionDir);
    session_id($sessionId);
    session_start();
    $flash = $_SESSION['flash_notification'] ?? null;
    session_write_close();
    session_save_path($currentSavePath === false ? '' : $currentSavePath);

    return is_array($flash) ? $flash : null;
}

function createExistingSession(string $sessionDir, string $sessionId): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $currentSavePath = ini_get('session.save_path');
    $currentSessionId = session_id();

    session_save_path($sessionDir);
    session_id($sessionId);
    session_start();
    session_write_close();

    session_id($currentSessionId);
    session_save_path($currentSavePath === false ? '' : $currentSavePath);
}

function runRestoreCase(array $case): array
{
    $sandbox = createSandbox($case['symlink_logos'] ?? false);
    $fixturePath = writeFixtureFile($case['raw_backup']);
    $sessionDir = makeTempDir('restore-session');
    $sessionId = 'restore'.bin2hex(random_bytes(6));
    createExistingSession($sessionDir, $sessionId);
    writeSandboxFiles($sandbox['root'], $case['setup_files'] ?? []);
    $beforeLogoFiles = listRelativeFiles($sandbox['root'].'/public/uploads/logos');
    $runnerPath = tempnam(sys_get_temp_dir(), 'restore-runner-');

    if ($runnerPath === false) {
        throw new RuntimeException('Failed to create runner file');
    }

    $sandboxExport = var_export($sandbox['root'], true);
    $fixtureExport = var_export($fixturePath, true);
    $sessionDirExport = var_export($sessionDir, true);
    $sessionIdExport = var_export($sessionId, true);
    $setupSqlExport = var_export($case['setup_sql'] ?? [], true);

    $runner = <<<PHP
<?php
declare(strict_types=1);
define('ROOT', {$sandboxExport});
require_once ROOT.'/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
ini_set('session.save_path', {$sessionDirExport});
session_id({$sessionIdExport});
\App\Core\Migrations::up();
\$pdo = \App\Core\Database::getInstance()->getConnection();
\$pdo->exec("INSERT INTO settings (key, value) VALUES ('quick_print_mode', '0')");
\$setupSql = {$setupSqlExport};
foreach (\$setupSql as \$sql) {
    \$pdo->exec(\$sql);
}
\$_FILES['backup_file'] = [
    'name' => 'fixture.mivo',
    'type' => 'application/octet-stream',
    'tmp_name' => {$fixtureExport},
    'error' => UPLOAD_ERR_OK,
    'size' => filesize({$fixtureExport}),
];
(new \App\Controllers\SettingsController())->restore();
PHP;

    if (file_put_contents($runnerPath, $runner) === false) {
        throw new RuntimeException('Failed to write runner file');
    }

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open(
        [PHP_BINARY, $runnerPath],
        $descriptors,
        $pipes,
        SOURCE_ROOT,
        [
            'APP_ENV' => 'testing',
            'APP_KEY' => str_repeat('k', 32),
        ]
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start restore runner');
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $flash = readFlash($sessionDir, $sessionId);

    recursiveRemove($sessionDir);
    unlink($fixturePath);
    unlink($runnerPath);

    return [
        'sandbox' => $sandbox,
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'flash' => $flash,
        'before_logo_files' => $beforeLogoFiles,
    ];
}

function assertFailureWithoutMutation(array $result, string $message): void
{
    $sandboxRoot = $result['sandbox']['root'];
    $pdo = openSandboxDatabase($sandboxRoot);

    assertTrue($pdo->query("SELECT value FROM settings WHERE key = 'quick_print_mode'")->fetchColumn() === '0', $message.' must not mutate settings');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM routers')->fetchColumn() === 0, $message.' must not insert routers');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM voucher_templates')->fetchColumn() === 0, $message.' must not insert voucher templates');
    assertTrue((int) $pdo->query('SELECT COUNT(*) FROM logos')->fetchColumn() === 0, $message.' must not insert logos');
    assertTrue(($result['flash']['type'] ?? null) === 'error', $message.' should produce an error flash');
}

function makeBasePayload(): array
{
    return [
        'settings' => [
            'quick_print_mode' => '1',
        ],
        'sessions' => [],
        'voucher_templates' => [],
        'logos' => [],
    ];
}

function encodeBackup(array $payload): string
{
    return json_encode($payload, JSON_THROW_ON_ERROR);
}

function makeValidSession(): array
{
    return [
        'id' => 99,
        'session_name' => 'main-router',
        'ip_address' => '10.0.0.1',
        'username' => 'admin',
        'password' => 'secret',
        'hotspot_name' => 'Main Hotspot',
        'dns_name' => 'login.example.test',
        'currency' => 'RP',
        'reload_interval' => 45,
        'interface' => 'ether1',
        'description' => 'restored',
        'quick_access' => 1,
    ];
}

function makeValidTemplate(): array
{
    return [
        'id' => 7,
        'router_id' => null,
        'session_name' => 'main-router',
        'name' => 'default',
        'content' => '<div>Voucher</div>',
    ];
}

function makeValidLogo(array $overrides = []): array
{
    return array_replace([
        'id' => MIXED_CASE_LOGO_ID,
        'name' => 'logo.png',
        'path' => '/uploads/logos/'.MIXED_CASE_LOGO_ID.'.png',
        'type' => 'png',
        'size' => 67,
        'data' => VALID_PNG_BASE64,
    ], $overrides);
}

function invalidPayloadCases(): array
{
    $oversizedData = base64_encode(str_repeat('A', MAX_LOGO_BYTES + 1));
    $cases = [];

    $unsafeLogoCases = [
        'logo_id_contains_dotdot' => ['field' => 'id', 'value' => '..', 'message' => 'logo id containing ..'],
        'logo_id_contains_forward_slash' => ['field' => 'id', 'value' => 'bad/id', 'message' => 'logo id containing /'],
        'logo_id_contains_backslash' => ['field' => 'id', 'value' => 'bad\\id', 'message' => 'logo id containing backslash'],
        'logo_id_contains_nul' => ['field' => 'id', 'value' => "bad\0id", 'message' => 'logo id containing NUL'],
        'logo_id_absolute_path' => ['field' => 'id', 'value' => '/tmp/evil', 'message' => 'logo id absolute path'],
        'logo_type_executable' => ['field' => 'type', 'value' => 'php', 'message' => 'logo type executable'],
        'logo_type_contains_dotdot' => ['field' => 'type', 'value' => '..', 'message' => 'logo type containing ..'],
        'logo_type_contains_forward_slash' => ['field' => 'type', 'value' => 'bad/type', 'message' => 'logo type containing /'],
        'logo_type_contains_backslash' => ['field' => 'type', 'value' => 'bad\\type', 'message' => 'logo type containing backslash'],
        'logo_type_contains_nul' => ['field' => 'type', 'value' => "bad\0type", 'message' => 'logo type containing NUL'],
        'logo_type_absolute_path' => ['field' => 'type', 'value' => '/tmp/png', 'message' => 'logo type absolute path'],
    ];

    foreach ($unsafeLogoCases as $name => $spec) {
        $cases[$name] = function () use ($spec): array {
            $payload = makeBasePayload();
            $payload['logos'][] = makeValidLogo([$spec['field'] => $spec['value']]);

            return [
                'raw_backup' => encodeBackup($payload),
                'assert' => fn (array $result) => assertFailureWithoutMutation($result, $spec['message']),
            ];
        };
    }

    $cases['invalid_base64'] = function (): array {
        $payload = makeBasePayload();
        $payload['logos'][] = makeValidLogo([
            'id' => 'broken',
            'data' => '%%%not-base64%%%',
        ]);

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'invalid base64'),
        ];
    };

    $cases['oversized_logo'] = function () use ($oversizedData): array {
        $payload = makeBasePayload();
        $payload['logos'][] = makeValidLogo([
            'id' => 'too-big',
            'size' => MAX_LOGO_BYTES + 1,
            'data' => $oversizedData,
        ]);

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'oversized logo'),
        ];
    };

    $cases['mismatched_image_bytes'] = function (): array {
        $payload = makeBasePayload();
        $payload['logos'][] = makeValidLogo([
            'id' => 'bad-image',
            'data' => base64_encode('not-an-image'),
        ]);

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'mismatched image bytes'),
        ];
    };

    $cases['unknown_top_level_key'] = function (): array {
        $payload = makeBasePayload();
        $payload['unexpected'] = true;

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'unknown top-level key'),
        ];
    };

    $cases['unknown_session_nested_key'] = function (): array {
        $payload = makeBasePayload();
        $payload['sessions'] = [array_replace(makeValidSession(), ['unexpected' => true])];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'unknown session nested key'),
        ];
    };

    $cases['unknown_template_nested_key'] = function (): array {
        $payload = makeBasePayload();
        $payload['voucher_templates'] = [array_replace(makeValidTemplate(), ['unexpected' => true])];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'unknown voucher template nested key'),
        ];
    };

    $cases['unknown_logo_nested_key'] = function (): array {
        $payload = makeBasePayload();
        $payload['logos'] = [makeValidLogo(['unexpected' => true])];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'unknown logo nested key'),
        ];
    };

    $cases['malformed_settings'] = function (): array {
        $payload = makeBasePayload();
        $payload['settings'] = ['quick_print_mode' => ['nested']];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'malformed settings'),
        ];
    };

    $cases['malformed_sessions'] = function (): array {
        $payload = makeBasePayload();
        $payload['sessions'] = ['not-a-session'];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'malformed sessions'),
        ];
    };

    $cases['invalid_session_name_route_breaking_char'] = function (): array {
        $payload = makeBasePayload();
        $payload['sessions'] = [array_replace(makeValidSession(), [
            'session_name' => 'branch/router',
        ])];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'invalid session_name containing route-breaking characters'),
        ];
    };

    $cases['malformed_templates'] = function (): array {
        $payload = makeBasePayload();
        $payload['voucher_templates'] = [[
            'session_name' => 'main',
            'name' => 'voucher',
            'content' => ['bad'],
        ]];

        return [
            'raw_backup' => encodeBackup($payload),
            'assert' => fn (array $result) => assertFailureWithoutMutation($result, 'malformed templates'),
        ];
    };

    $cases['symlink_escape'] = function (): array {
        $payload = makeBasePayload();
        $payload['logos'][] = makeValidLogo([
            'id' => 'logo',
            'name' => 'logo',
        ]);

        return [
            'raw_backup' => encodeBackup($payload),
            'symlink_logos' => true,
            'assert' => function (array $result): void {
                assertFailureWithoutMutation($result, 'symlink escape');
                $escapeRoot = $result['sandbox']['escape_root'];
                assertTrue(is_string($escapeRoot), 'symlink escape test must create an escape root');
                assertTrue(listRelativeFiles($escapeRoot) === [], 'symlink escape must not write outside sandbox root');
            },
        ];
    };

    $cases['rollback_after_logo_move_failure'] = function (): array {
        $payload = makeBasePayload();
        $payload['sessions'] = [makeValidSession()];
        $payload['voucher_templates'] = [makeValidTemplate()];
        $payload['logos'] = [
            makeValidLogo(['id' => 'FirstMove', 'name' => 'first.png']),
            makeValidLogo(['id' => 'SecondMove', 'name' => 'second.png']),
        ];

        return [
            'raw_backup' => encodeBackup($payload),
            'setup_sql' => [
                "CREATE TRIGGER fail_second_logo_insert BEFORE INSERT ON logos WHEN (SELECT COUNT(*) FROM logos) >= 1 BEGIN SELECT RAISE(ABORT, 'force logo insert failure'); END;",
            ],
            'assert' => function (array $result): void {
                assertFailureWithoutMutation($result, 'rollback after moved logo failure');
                $logoDir = $result['sandbox']['root'].'/public/uploads/logos';
                assertTrue(listRelativeFiles($logoDir) === $result['before_logo_files'], 'rollback after moved logo failure must remove every created file');
            },
        ];
    };

    return $cases;
}

function runInvalidCase(string $name, callable $factory): void
{
    $case = $factory();
    $result = runRestoreCase($case);

    try {
        $assert = $case['assert'];
        $assert($result);
    } finally {
        cleanupSandbox($result['sandbox']);
    }
}

function runBackupShapedMixedCaseSuccessCase(): void
{
    $payload = [
        'settings' => [
            'quick_print_mode' => '1',
        ],
        'sessions' => [makeValidSession()],
        'voucher_templates' => [makeValidTemplate()],
        'logos' => [makeValidLogo([
            'id' => MIXED_CASE_LOGO_ID,
            'name' => 'MiXeD42.png',
            'path' => '/uploads/logos/MiXeD42.png',
        ])],
    ];

    $result = runRestoreCase([
        'raw_backup' => encodeBackup($payload),
    ]);

    try {
        $sandboxRoot = $result['sandbox']['root'];
        $pdo = openSandboxDatabase($sandboxRoot);

        assertTrue(($result['flash']['type'] ?? null) === 'success', 'legacy plaintext restore should succeed');
        assertTrue($pdo->query("SELECT value FROM settings WHERE key = 'quick_print_mode'")->fetchColumn() === '1', 'legacy plaintext restore should update settings');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM routers')->fetchColumn() === 1, 'legacy plaintext restore should insert routers');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM voucher_templates')->fetchColumn() === 1, 'legacy plaintext restore should insert voucher templates');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM logos')->fetchColumn() === 1, 'legacy plaintext restore should insert logos');

        $logo = $pdo->query("SELECT id, path, type, size FROM logos WHERE id = '".MIXED_CASE_LOGO_ID."'")->fetch();
        assertTrue(is_array($logo), 'backup-shaped mixed-case restore should preserve exact logo id');
        assertTrue($logo['id'] === MIXED_CASE_LOGO_ID, 'backup-shaped mixed-case restore must keep mixed-case logo id in the database');
        assertTrue($logo['type'] === 'png', 'legacy plaintext restore should persist detected logo type');
        assertTrue((int) $logo['size'] > 0, 'legacy plaintext restore should persist actual logo size');
        assertTrue(str_starts_with($logo['path'], '/uploads/logos/'), 'legacy plaintext restore should write under uploads/logos');
        assertTrue(! str_contains($logo['path'], '..'), 'legacy plaintext restore should use a safe server-generated path');
        assertTrue(str_starts_with(basename($logo['path']), MIXED_CASE_LOGO_ID), 'backup-shaped mixed-case restore must preserve mixed-case id semantics in the generated path');

        $logoPath = $sandboxRoot.'/public'.$logo['path'];
        assertTrue(file_exists($logoPath), 'legacy plaintext restore should write the logo file');
        assertTrue(base64_encode(file_get_contents($logoPath)) === VALID_PNG_BASE64, 'legacy plaintext restore should preserve logo bytes');
    } finally {
        cleanupSandbox($result['sandbox']);
    }
}

function runExistingMixedCaseLogoReplacementCase(): void
{
    $oldRelativePath = 'public/uploads/logos/original-existing.png';
    $oldWebPath = '/uploads/logos/original-existing.png';
    $oldContents = "old-logo-content";

    $payload = [
        'settings' => [
            'quick_print_mode' => '1',
        ],
        'sessions' => [],
        'voucher_templates' => [],
        'logos' => [makeValidLogo([
            'id' => MIXED_CASE_LOGO_ID,
            'name' => 'replacement.png',
            'path' => $oldWebPath,
        ])],
    ];

    $result = runRestoreCase([
        'raw_backup' => encodeBackup($payload),
        'setup_files' => [[
            'path' => $oldRelativePath,
            'contents' => $oldContents,
        ]],
        'setup_sql' => [
            "INSERT INTO logos (id, name, path, type, size) VALUES ('".MIXED_CASE_LOGO_ID."', 'original-existing.png', '".$oldWebPath."', 'png', ".strlen($oldContents).")",
        ],
    ]);

    try {
        $sandboxRoot = $result['sandbox']['root'];
        $pdo = openSandboxDatabase($sandboxRoot);

        assertTrue(($result['flash']['type'] ?? null) === 'success', 'existing mixed-case logo replacement should succeed');
        assertTrue((int) $pdo->query('SELECT COUNT(*) FROM logos')->fetchColumn() === 1, 'existing mixed-case logo replacement should keep one logical logo row');
        $logo = $pdo->query("SELECT id, path, type, size FROM logos WHERE id = '".MIXED_CASE_LOGO_ID."'")->fetch();
        assertTrue(is_array($logo), 'existing mixed-case logo replacement should update the original mixed-case id row');
        assertTrue($logo['path'] !== $oldWebPath, 'existing mixed-case logo replacement must switch to a different generated path');
        assertTrue(str_starts_with(basename($logo['path']), MIXED_CASE_LOGO_ID), 'existing mixed-case logo replacement must preserve mixed-case id semantics in the new path');

        $newPath = $sandboxRoot.'/public'.$logo['path'];
        $oldPath = $sandboxRoot.'/'.$oldRelativePath;
        assertTrue(file_exists($newPath), 'existing mixed-case logo replacement should create the new logo file');
        assertTrue(base64_encode(file_get_contents($newPath)) === VALID_PNG_BASE64, 'existing mixed-case logo replacement should preserve new logo bytes');

        if (file_exists($oldPath)) {
            assertTrue(file_get_contents($oldPath) === $oldContents, 'existing mixed-case logo replacement must not overwrite the old file in place');
        }
    } finally {
        cleanupSandbox($result['sandbox']);
    }
}

function runMaliciousExistingPathNotDeletedCase(): void
{
    $outsideRelativePath = 'public/outside.txt';
    $outsideWebPath = '/uploads/logos/../../outside.txt';
    $outsideContents = 'outside-sentinel';

    $payload = [
        'settings' => [
            'quick_print_mode' => '1',
        ],
        'sessions' => [],
        'voucher_templates' => [],
        'logos' => [makeValidLogo([
            'id' => MIXED_CASE_LOGO_ID,
            'name' => 'replacement.png',
            'path' => $outsideWebPath,
        ])],
    ];

    $result = runRestoreCase([
        'raw_backup' => encodeBackup($payload),
        'setup_files' => [[
            'path' => $outsideRelativePath,
            'contents' => $outsideContents,
        ]],
        'setup_sql' => [
            "INSERT INTO logos (id, name, path, type, size) VALUES ('".MIXED_CASE_LOGO_ID."', 'outside.txt', '".$outsideWebPath."', 'png', ".strlen($outsideContents).")",
        ],
    ]);

    try {
        $sandboxRoot = $result['sandbox']['root'];
        $pdo = openSandboxDatabase($sandboxRoot);

        assertTrue(($result['flash']['type'] ?? null) === 'success', 'malicious existing logo path replacement should succeed');
        $logo = $pdo->query("SELECT id, path, type, size FROM logos WHERE id = '".MIXED_CASE_LOGO_ID."'")->fetch();
        assertTrue(is_array($logo), 'malicious existing logo path replacement should keep the logical logo row');
        assertTrue($logo['path'] !== $outsideWebPath, 'malicious existing logo path replacement must switch to a safe generated path');
        assertTrue(str_starts_with($logo['path'], '/uploads/logos/'), 'malicious existing logo path replacement must point to uploads/logos');
        assertTrue(! str_contains($logo['path'], '..'), 'malicious existing logo path replacement must not keep traversal segments');

        $logoPath = $sandboxRoot.'/public'.$logo['path'];
        assertTrue(file_exists($logoPath), 'malicious existing logo path replacement should create the restored file');
        assertTrue(base64_encode(file_get_contents($logoPath)) === VALID_PNG_BASE64, 'malicious existing logo path replacement should preserve restored bytes');

        $outsidePath = $sandboxRoot.'/'.$outsideRelativePath;
        assertTrue(file_exists($outsidePath), 'malicious existing logo path replacement must not delete the outside sentinel');
        assertTrue(file_get_contents($outsidePath) === $outsideContents, 'malicious existing logo path replacement must not alter the outside sentinel');
    } finally {
        cleanupSandbox($result['sandbox']);
    }
}

$sourceSentinel = file_exists(SOURCE_ROOT.'/public/uploads/.gitignore')
    ? sha1_file(SOURCE_ROOT.'/public/uploads/.gitignore')
    : null;

foreach (invalidPayloadCases() as $name => $factory) {
    runInvalidCase($name, $factory);
}

runBackupShapedMixedCaseSuccessCase();
runExistingMixedCaseLogoReplacementCase();
runMaliciousExistingPathNotDeletedCase();

$currentSentinel = file_exists(SOURCE_ROOT.'/public/uploads/.gitignore')
    ? sha1_file(SOURCE_ROOT.'/public/uploads/.gitignore')
    : null;
assertTrue($sourceSentinel === $currentSentinel, 'sandbox tests must not mutate repository uploads sentinel');

echo "PASS: Backup restore security checks\n";
