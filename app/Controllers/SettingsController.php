<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Middleware;
use App\Core\PluginManager;
use App\Helpers\EncryptionHelper;
use App\Helpers\FlashHelper;
use App\Helpers\FormatHelper;
use App\Helpers\RouterTargetHelper;
use App\Models\Config;
use App\Models\Logo;
use App\Models\Setting;
use App\Models\VoucherTemplateModel;

class SettingsController extends Controller
{
    private const RESTORE_MAX_LOGO_BYTES = 5242880;

    private const RESTORE_TOP_LEVEL_KEYS = ['settings', 'sessions', 'voucher_templates', 'logos'];

    private const RESTORE_SESSION_KEYS = [
        'id',
        'session_name',
        'ip_address',
        'username',
        'password',
        'hotspot_name',
        'dns_name',
        'currency',
        'reload_interval',
        'interface',
        'description',
        'quick_access',
    ];

    private const RESTORE_TEMPLATE_KEYS = [
        'id',
        'router_id',
        'session_name',
        'name',
        'content',
        'created_at',
        'updated_at',
    ];

    private const RESTORE_LOGO_KEYS = [
        'id',
        'name',
        'path',
        'type',
        'size',
        'created_at',
        'data',
    ];

    private const RESTORE_ALLOWED_LOGO_TYPES = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'svg' => 'svg',
        'webp' => 'webp',
    ];

    public function __construct()
    {
        // Auth handled by Router Middleware
    }

    public function system()
    {
        // Systems Settings Tab (Admin, Global, Backup)
        $settingModel = new Setting;
        $settings = $settingModel->getAll();

        $username = $_SESSION['username'] ?? 'admin';

        return $this->view('settings/systems', [
            'settings' => $settings,
            'username' => $username,
        ]);
    }

    public function routers()
    {
        // Routers List Tab
        $configModel = new Config;
        $routers = $configModel->getAllSessions();

        return $this->view('settings/index', ['routers' => $routers]);
    }

    // ... (Existing Store methods) ...
    public function store()
    {
        $ipAddress = $_POST['ipmik'] ?? '';
        if (! RouterTargetHelper::validate((string) $ipAddress)) {
            FlashHelper::set('error', 'Invalid Router Target', 'Enter a valid router IP address or hostname.');
            header('Location: /settings/routers');

            return;
        }

        // Sanitize Session Name (Duplicate Frontend Logic)
        $rawSess = $_POST['sessname'] ?? '';
        $sessName = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $rawSess)));

        $data = [
            'session_name' => $sessName,
            'ip_address' => $ipAddress,
            'username' => $_POST['usermik'],
            'password' => $_POST['passmik'],
            'hotspot_name' => $_POST['hotspotname'],
            'dns_name' => $_POST['dnsname'],
            'currency' => $_POST['currency'],
            'reload_interval' => $_POST['areload'],
            'interface' => $_POST['iface'],
            'description' => 'Added via Remake',
            'quick_access' => isset($_POST['quick_access']) ? 1 : 0,
        ];

        $configModel = new Config;
        try {
            $configModel->addSession($data);

            $redirect = '/settings/routers';
            if (isset($_POST['action']) && $_POST['action'] === 'connect') {
                $redirect = '/'.urlencode($data['session_name']).'/dashboard';
            }

            FlashHelper::set('success', 'toasts.router_added', 'toasts.router_added_desc', ['name' => $data['session_name']], true);
            header("Location: $redirect");
        } catch (\Exception $e) {
            echo 'Error adding session: '.$e->getMessage();
        }
    }

    // Update Admin Password
    public function updateAdmin()
    {
        $newPassword = $_POST['admin_password'] ?? '';

        if (! empty($newPassword)) {
            $db = Database::getInstance();
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            // Assuming we are updating the default 'admin' user or the currently logged in user
            // Original Mivo usually has one main user. Let's update 'admin' for now.
            $db->query("UPDATE users SET password = ? WHERE username = 'admin'", [$hash]);
            FlashHelper::set('success', 'toasts.password_updated', 'toasts.password_updated_desc', [], true);
        }

        header('Location: /settings/system');
    }

    // Update Global Settings
    public function updateGlobal()
    {
        $settingModel = new Setting;

        if (isset($_POST['quick_print_mode'])) {
            $settingModel->set('quick_print_mode', $_POST['quick_print_mode']);
            FlashHelper::set('success', 'toasts.settings_saved', 'toasts.settings_saved_desc', [], true);
        }

        header('Location: /settings/system');
    }

    public function update()
    {
        $id = $_POST['id'];
        $ipAddress = $_POST['ipmik'] ?? '';
        if (! RouterTargetHelper::validate((string) $ipAddress)) {
            FlashHelper::set('error', 'Invalid Router Target', 'Enter a valid router IP address or hostname.');
            header('Location: /settings/routers');

            return;
        }

        // Sanitize Session Name
        $rawSess = $_POST['sessname'] ?? '';
        $sessName = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $rawSess)));

        $data = [
            'session_name' => $sessName,
            'ip_address' => $ipAddress,
            'username' => $_POST['usermik'],
            'password' => $_POST['passmik'], // Can be empty if not changing
            'hotspot_name' => $_POST['hotspotname'],
            'dns_name' => $_POST['dnsname'],
            'currency' => $_POST['currency'],
            'reload_interval' => $_POST['areload'],
            'interface' => $_POST['iface'],
            'description' => 'Updated via Remake',
            'quick_access' => isset($_POST['quick_access']) ? 1 : 0,
        ];

        $configModel = new Config;
        try {
            $configModel->updateSession($id, $data);

            $redirect = '/settings/routers';
            if (isset($_POST['action']) && $_POST['action'] === 'connect') {
                $redirect = '/'.urlencode($data['session_name']).'/dashboard';
            }

            FlashHelper::set('success', 'toasts.router_updated', 'toasts.router_updated_desc', ['name' => $data['session_name']], true);
            header("Location: $redirect");
        } catch (\Exception $e) {
            echo 'Error updating session: '.$e->getMessage();
        }
    }

    public function delete()
    {
        $id = $_POST['id'];
        $configModel = new Config;
        $configModel->deleteSession($id);
        FlashHelper::set('success', 'toasts.router_deleted', 'toasts.router_deleted_desc', [], true);
        header('Location: /settings/routers');
    }

    public function backup()
    {
        $backupName = 'mivo_backup_'.date('d-m-Y').'.mivo';
        $json = [];

        // Backup Settings
        $settingModel = new Setting;
        $settings = $settingModel->getAll();
        $json['settings'] = $settings;

        // Backup Sessions
        $configModel = new Config;
        $sessions = $configModel->getAllSessions();

        // Decrypt passwords for portability
        foreach ($sessions as &$session) {
            if (! empty($session['password'])) {
                $session['password'] = EncryptionHelper::decrypt($session['password']);
            }
        }
        $json['sessions'] = $sessions;

        // Backup Voucher Templates
        $templateModel = new VoucherTemplateModel;
        $json['voucher_templates'] = $templateModel->getAll();

        // Backup Logos
        $logoModel = new Logo;
        $logos = $logoModel->getAll();
        foreach ($logos as &$logo) {
            $filePath = ROOT.'/public'.$logo['path'];
            if (file_exists($filePath)) {
                $logo['data'] = base64_encode(file_get_contents($filePath));
            }
        }
        $json['logos'] = $logos;

        // Encode
        $jsonString = json_encode($json, JSON_PRETTY_PRINT);

        // Encrypt the entire file content for security
        // Decrypted data inside (like passwords) remain plaintext relative to the JSON structure
        // ensuring portability if decrypted successfully.
        $content = EncryptionHelper::encrypt($jsonString);

        // Force Download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.basename($backupName));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: '.strlen($content));
        ob_clean();
        flush();
        echo $content;
        exit;
    }

    public function restore()
    {
        if (! isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $this->failRestore('toasts.no_file_selected');
        }

        $file = $_FILES['backup_file'];
        $filename = $file['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = $file['type'];

        // Validate Extension & MIME
        $allowedExtensions = ['mivo'];
        $allowedMimes = ['application/octet-stream', 'text/plain']; // text/plain fallback for some OS/Browsers

        if (! in_array($extension, $allowedExtensions) || (! empty($mime) && ! in_array($mime, $allowedMimes))) {
            $this->failRestore('toasts.invalid_file_type_mivo');
        }

        $rawValue = file_get_contents($file['tmp_name']);
        if ($rawValue === false || $rawValue === '') {
            $this->failRestore('toasts.file_empty');
        }

        // Attempt to decrypt. If file is old (JSON plaintext), decrypt() returns it as-is.
        $content = EncryptionHelper::decrypt($rawValue);
        $json = json_decode((string) $content, true);
        if (! is_array($json)) {
            $this->failRestore('toasts.file_corrupted');
        }

        try {
            $validated = $this->validateRestorePayload($json);
            $this->applyValidatedRestore($validated);
        } catch (\Throwable $e) {
            error_log('Backup restore failed: '.$e->getMessage());
            $this->failRestore('toasts.file_corrupted');
        }

        FlashHelper::set('success', 'toasts.restore_success', 'toasts.restore_success_desc', [], true);
        header('Location: /settings/system');
    }

    private function failRestore(string $messageKey): void
    {
        FlashHelper::set('error', 'toasts.restore_failed', $messageKey, [], true);
        header('Location: /settings/system');
        exit;
    }

    private function validateRestorePayload(array $payload): array
    {
        $unknownKeys = array_diff(array_keys($payload), self::RESTORE_TOP_LEVEL_KEYS);
        if ($unknownKeys !== []) {
            throw new \RuntimeException('Unknown restore keys: '.implode(', ', $unknownKeys));
        }

        $presentKeys = array_intersect(self::RESTORE_TOP_LEVEL_KEYS, array_keys($payload));
        if ($presentKeys === []) {
            throw new \RuntimeException('Restore payload is empty');
        }

        return [
            'settings' => $this->validateRestoreSettings($payload['settings'] ?? []),
            'sessions' => $this->validateRestoreSessions($payload['sessions'] ?? []),
            'voucher_templates' => $this->validateRestoreTemplates($payload['voucher_templates'] ?? []),
            'logos' => $this->validateRestoreLogos($payload['logos'] ?? []),
        ];
    }

    private function validateRestoreSettings(mixed $settings): array
    {
        if (! is_array($settings)) {
            throw new \RuntimeException('Settings payload must be an array');
        }

        $validated = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key) || $key === '') {
                throw new \RuntimeException('Settings keys must be non-empty strings');
            }

            if (is_array($value) || is_object($value)) {
                throw new \RuntimeException('Settings values must be scalar');
            }

            $validated[$key] = $value === null ? '' : (string) $value;
        }

        return $validated;
    }

    private function validateRestoreSessions(mixed $sessions): array
    {
        if (! is_array($sessions)) {
            throw new \RuntimeException('Sessions payload must be an array');
        }

        $validated = [];
        foreach ($sessions as $session) {
            if (! is_array($session)) {
                throw new \RuntimeException('Each session must be an array');
            }

            $unknownKeys = array_diff(array_keys($session), self::RESTORE_SESSION_KEYS);
            if ($unknownKeys !== []) {
                throw new \RuntimeException('Unknown session keys: '.implode(', ', $unknownKeys));
            }

            $sessionName = $this->restoreScalarString($session['session_name'] ?? null, 'session_name');
            if (! preg_match('/\A[a-z0-9-]+\z/', $sessionName)) {
                throw new \RuntimeException('Session name is invalid');
            }

            $validated[] = [
                'session_name' => $sessionName,
                'ip_address' => $this->restoreOptionalScalarString($session['ip_address'] ?? ''),
                'username' => $this->restoreOptionalScalarString($session['username'] ?? ''),
                'password' => $this->restoreOptionalScalarString($session['password'] ?? ''),
                'hotspot_name' => $this->restoreOptionalScalarString($session['hotspot_name'] ?? ''),
                'dns_name' => $this->restoreOptionalScalarString($session['dns_name'] ?? ''),
                'currency' => $this->restoreOptionalScalarString($session['currency'] ?? 'RP'),
                'reload_interval' => $this->restoreOptionalIntString($session['reload_interval'] ?? 60),
                'interface' => $this->restoreOptionalScalarString($session['interface'] ?? 'ether1'),
                'description' => $this->restoreOptionalScalarString($session['description'] ?? ''),
                'quick_access' => $this->restoreBooleanFlag($session['quick_access'] ?? 0),
            ];
        }

        return $validated;
    }

    private function validateRestoreTemplates(mixed $templates): array
    {
        if (! is_array($templates)) {
            throw new \RuntimeException('Voucher templates payload must be an array');
        }

        $validated = [];
        foreach ($templates as $template) {
            if (! is_array($template)) {
                throw new \RuntimeException('Each voucher template must be an array');
            }

            $unknownKeys = array_diff(array_keys($template), self::RESTORE_TEMPLATE_KEYS);
            if ($unknownKeys !== []) {
                throw new \RuntimeException('Unknown voucher template keys: '.implode(', ', $unknownKeys));
            }

            $content = $template['content'] ?? null;
            if (is_array($content) || is_object($content)) {
                throw new \RuntimeException('Voucher template content must be scalar');
            }

            $validated[] = [
                'router_id' => $this->restoreNullableInt($template['router_id'] ?? null),
                'session_name' => $this->restoreScalarString($template['session_name'] ?? null, 'template session_name'),
                'name' => $this->restoreScalarString($template['name'] ?? null, 'template name'),
                'content' => $content === null ? '' : (string) $content,
            ];
        }

        return $validated;
    }

    private function validateRestoreLogos(mixed $logos): array
    {
        if (! is_array($logos)) {
            throw new \RuntimeException('Logos payload must be an array');
        }

        $uploadDir = ROOT.'/public/uploads/logos';
        $this->assertRestorePathHasNoSymlinks(ROOT.'/public/uploads');
        if (file_exists($uploadDir)) {
            $this->assertRestorePathHasNoSymlinks($uploadDir);
        }

        $validated = [];
        foreach ($logos as $logo) {
            if (! is_array($logo)) {
                throw new \RuntimeException('Each logo must be an array');
            }

            $unknownKeys = array_diff(array_keys($logo), self::RESTORE_LOGO_KEYS);
            if ($unknownKeys !== []) {
                throw new \RuntimeException('Unknown logo keys: '.implode(', ', $unknownKeys));
            }

            $logoId = $this->validateRestoreLogoId($logo['id'] ?? null);
            $logoType = $this->validateRestoreLogoType($logo['type'] ?? null);
            $logoName = $this->restoreScalarString($logo['name'] ?? $logoId, 'logo name');

            if (isset($logo['size']) && (is_array($logo['size']) || is_object($logo['size']))) {
                throw new \RuntimeException('Logo size must be scalar');
            }

            $data = $logo['data'] ?? null;
            if ($data === null || $data === '') {
                continue;
            }

            if (! is_string($data)) {
                throw new \RuntimeException('Logo data must be a base64 string');
            }

            $binaryData = base64_decode($data, true);
            if ($binaryData === false) {
                throw new \RuntimeException('Invalid base64 logo data');
            }

            $binarySize = strlen($binaryData);
            if ($binarySize > self::RESTORE_MAX_LOGO_BYTES) {
                throw new \RuntimeException('Logo data exceeds size limit');
            }

            $actualType = $this->detectRestoreLogoType($binaryData);
            if ($actualType === null || $actualType !== $logoType) {
                throw new \RuntimeException('Logo bytes do not match declared type');
            }

            $validated[] = [
                'id' => $logoId,
                'name' => $logoName,
                'type' => $actualType,
                'size' => $binarySize,
                'data' => $binaryData,
            ];
        }

        return $validated;
    }

    private function applyValidatedRestore(array $validated): void
    {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $settingModel = new Setting;
        $configModel = new Config;
        $templateModel = new VoucherTemplateModel;

        $uploadDir = ROOT.'/public/uploads/logos';
        $stageDir = null;
        $stagedLogos = [];
        $createdFiles = [];
        $replacedFiles = [];

        try {
            if ($validated['logos'] !== []) {
                $uploadDir = $this->prepareRestoreUploadDirectory($uploadDir);
                $stageDir = $this->createRestoreStageDirectory($uploadDir);

                foreach ($validated['logos'] as $logo) {
                    $stagePath = $this->buildRestorePath($stageDir, $logo['id'].'.'.$logo['type']);
                    $bytesWritten = file_put_contents($stagePath, $logo['data'], LOCK_EX);
                    if ($bytesWritten !== $logo['size']) {
                        throw new \RuntimeException('Failed to stage restored logo');
                    }

                    $stagedLogos[] = $logo + ['stage_path' => $stagePath];
                }
            }

            $pdo->beginTransaction();

            foreach ($validated['settings'] as $key => $value) {
                $settingModel->set($key, $value);
            }

            foreach ($validated['sessions'] as $session) {
                $configModel->addSession($session);
            }

            foreach ($validated['voucher_templates'] as $template) {
                $existing = $db->query('SELECT id FROM voucher_templates WHERE name = ? AND session_name = ?', [$template['name'], $template['session_name']])->fetch();

                if ($existing) {
                    $templateModel->update($existing['id'], $template);
                } else {
                    $templateModel->add($template);
                }
            }

            foreach ($stagedLogos as $logo) {
                $targetPath = $this->generateRestoreLogoTargetPath($uploadDir, $logo['id'], $logo['type']);
                $targetWebPath = '/uploads/logos/'.basename($targetPath);
                $existingLogo = $db->query('SELECT path FROM logos WHERE id = ?', [$logo['id']])->fetch();

                if (! rename($logo['stage_path'], $targetPath)) {
                    throw new \RuntimeException('Failed to move restored logo');
                }

                $createdFiles[] = $targetPath;
                if ($existingLogo && isset($existingLogo['path']) && is_string($existingLogo['path'])) {
                    $replacedFiles[] = $existingLogo['path'];
                }

                $db->query('INSERT INTO logos (id, name, path, type, size) VALUES (:id, :name, :path, :type, :size)
                            ON CONFLICT(id) DO UPDATE SET name=excluded.name, path=excluded.path, type=excluded.type, size=excluded.size', [
                    'id' => $logo['id'],
                    'name' => $logo['name'],
                    'path' => $targetWebPath,
                    'type' => $logo['type'],
                    'size' => $logo['size'],
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($createdFiles as $createdFile) {
                if (is_file($createdFile)) {
                    unlink($createdFile);
                }
            }

            if ($stageDir !== null) {
                $this->removeRestoreDirectory($stageDir, $uploadDir);
            }

            throw $e;
        }

        if ($stageDir !== null) {
            $this->removeRestoreDirectory($stageDir, $uploadDir);
        }

        foreach ($replacedFiles as $replacedFile) {
            $replacedPath = $this->resolveSafeRestoreCleanupPath($replacedFile, $uploadDir);
            if ($replacedPath !== null) {
                unlink($replacedPath);
            }
        }
    }

    private function restoreScalarString(mixed $value, string $fieldName): string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            throw new \RuntimeException($fieldName.' must be scalar');
        }

        return (string) $value;
    }

    private function restoreOptionalScalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            throw new \RuntimeException('Optional restore value must be scalar');
        }

        return (string) $value;
    }

    private function restoreOptionalIntString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (! is_scalar($value) || filter_var((string) $value, FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException('Restore integer value is invalid');
        }

        return (string) (int) $value;
    }

    private function restoreBooleanFlag(mixed $value): int
    {
        if (! is_scalar($value) && $value !== null) {
            throw new \RuntimeException('Restore boolean flag must be scalar');
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function restoreNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_scalar($value) || filter_var((string) $value, FILTER_VALIDATE_INT) === false) {
            throw new \RuntimeException('Restore integer value is invalid');
        }

        return (int) $value;
    }

    private function validateRestoreLogoId(mixed $value): string
    {
        $logoId = $this->restoreScalarString($value, 'logo id');
        if (! preg_match('/\A[A-Za-z0-9_-]+\z/', $logoId)) {
            throw new \RuntimeException('Logo id is invalid');
        }

        return $logoId;
    }

    private function validateRestoreLogoType(mixed $value): string
    {
        $logoType = strtolower($this->restoreScalarString($value, 'logo type'));
        if (! isset(self::RESTORE_ALLOWED_LOGO_TYPES[$logoType])) {
            throw new \RuntimeException('Logo type is invalid');
        }

        return self::RESTORE_ALLOWED_LOGO_TYPES[$logoType];
    }

    private function detectRestoreLogoType(string $binaryData): ?string
    {
        $imageInfo = @getimagesizefromstring($binaryData);
        if (is_array($imageInfo) && isset($imageInfo['mime'])) {
            return match ($imageInfo['mime']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => null,
            };
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binaryData);
        if ($mimeType === 'image/svg+xml' || $mimeType === 'text/xml' || $mimeType === 'application/xml') {
            if (preg_match('/^\s*(?:<\?xml[^>]*>\s*)?<svg\b/i', ltrim($binaryData)) === 1) {
                return 'svg';
            }
        }

        return null;
    }

    private function prepareRestoreUploadDirectory(string $uploadDir): string
    {
        $this->assertRestorePathHasNoSymlinks(dirname($uploadDir));
        if (is_link($uploadDir)) {
            throw new \RuntimeException('Restore upload directory cannot be a symlink');
        }

        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0777, true) && ! is_dir($uploadDir)) {
            throw new \RuntimeException('Failed to create restore upload directory');
        }

        $this->assertRestorePathHasNoSymlinks($uploadDir);
        $resolvedDir = realpath($uploadDir);
        if ($resolvedDir === false || ! is_dir($resolvedDir)) {
            throw new \RuntimeException('Restore upload directory is invalid');
        }

        return rtrim($resolvedDir, DIRECTORY_SEPARATOR);
    }

    private function createRestoreStageDirectory(string $uploadDir): string
    {
        $stageDir = $this->buildRestorePath($uploadDir, '.restore-'.bin2hex(random_bytes(8)));
        if (! mkdir($stageDir, 0777, true)) {
            throw new \RuntimeException('Failed to create restore staging directory');
        }

        return $stageDir;
    }

    private function generateRestoreLogoTargetPath(string $uploadDir, string $logoId, string $logoType): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $targetName = $logoId.'-'.bin2hex(random_bytes(8)).'.'.$logoType;
            $targetPath = $this->buildRestorePath($uploadDir, $targetName);
            if (! file_exists($targetPath)) {
                return $targetPath;
            }
        }

        throw new \RuntimeException('Failed to allocate unique restore filename');
    }

    private function buildRestorePath(string $baseDir, string $basename): string
    {
        $trimmedBase = rtrim($baseDir, DIRECTORY_SEPARATOR);
        $targetPath = $trimmedBase.DIRECTORY_SEPARATOR.$basename;
        $resolvedBase = realpath($trimmedBase);
        if ($resolvedBase === false || ! $this->isRestorePathWithin($targetPath, $resolvedBase)) {
            throw new \RuntimeException('Restore path resolved outside upload directory');
        }

        return $targetPath;
    }

    private function removeRestoreDirectory(string $directory, string $uploadDir): void
    {
        if (! $this->isRestorePathWithin($directory, $uploadDir) || ! is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    private function isRestorePathWithin(string $path, string $directory): bool
    {
        $normalizedDirectory = rtrim(str_replace('\\', '/', $directory), '/');
        $normalizedPath = str_replace('\\', '/', $path);

        return $normalizedPath === $normalizedDirectory || str_starts_with($normalizedPath, $normalizedDirectory.'/');
    }

    private function resolveSafeRestoreCleanupPath(string $webPath, string $uploadDir): ?string
    {
        if (strpos($webPath, "\0") !== false || str_contains($webPath, '\\')) {
            return null;
        }

        if (preg_match('#^/uploads/logos/[A-Za-z0-9._-]+$#', $webPath) !== 1) {
            return null;
        }

        $resolvedUploadDir = realpath($uploadDir);
        if ($resolvedUploadDir === false) {
            return null;
        }

        $candidatePath = ROOT.'/public'.$webPath;
        if (is_link($candidatePath)) {
            return null;
        }

        $resolvedCandidate = realpath($candidatePath);
        if ($resolvedCandidate === false || is_link($resolvedCandidate) || ! is_file($resolvedCandidate)) {
            return null;
        }

        if (! $this->isRestorePathWithin($resolvedCandidate, $resolvedUploadDir) || $resolvedCandidate === $resolvedUploadDir) {
            return null;
        }

        return $resolvedCandidate;
    }

    private function assertRestorePathHasNoSymlinks(string $path): void
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        $segments = array_filter(explode('/', ltrim($normalized, '/')), static fn ($segment) => $segment !== '');
        $current = DIRECTORY_SEPARATOR;

        foreach ($segments as $segment) {
            $current .= $segment;
            if (is_link($current)) {
                throw new \RuntimeException('Restore path contains a symlink');
            }
            $current .= DIRECTORY_SEPARATOR;
        }
    }

    // --- Logo Management ---

    public function logos()
    {
        $logoModel = new Logo; // Fully qualified to avoid import issues for now or add import
        $logoModel->syncFiles(); // Ensure FS and DB are in sync
        $logos = $logoModel->getAll();

        // Format size for display (since DB stores raw bytes or maybe we want helper there)
        // Actually model stored bytes, we format in View or here.
        // Let's format here for consistency with previous view.
        foreach ($logos as &$logo) {
            $logo['formatted_size'] = FormatHelper::formatBytes($logo['size']);
        }

        return $this->view('settings/logos', ['logos' => $logos]);
    }

    public function uploadLogo()
    {
        if (! isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
            FlashHelper::set('error', 'toasts.upload_failed', 'toasts.no_file_selected', [], true);
            header('Location: /settings/logos');
            exit;
        }

        $logoModel = new Logo;
        try {
            $result = $logoModel->add($_FILES['logo_file']);
            if ($result) {
                FlashHelper::set('success', 'toasts.logo_uploaded', 'toasts.logo_uploaded_desc', [], true);
            } else {
                FlashHelper::set('error', 'toasts.upload_failed', 'Generic upload error', [], true);
            }
        } catch (\Exception $e) {
            FlashHelper::set('error', 'toasts.upload_failed', $e->getMessage(), [], true);
        }

        header('Location: /settings/logos');
    }

    public function deleteLogo()
    {
        $id = $_POST['id']; // Changed from filename to id

        $logoModel = new Logo;
        $logoModel->delete($id);

        FlashHelper::set('success', 'toasts.logo_deleted', 'toasts.logo_deleted_desc', [], true);
        header('Location: /settings/logos');
    }

    // --- API CORS Management ---

    public function apiCors()
    {
        $db = Database::getInstance();
        $rules = $db->query('SELECT * FROM api_cors ORDER BY created_at DESC')->fetchAll();

        // Decode JSON methods and headers for view
        foreach ($rules as &$rule) {
            $rule['methods_arr'] = json_decode($rule['methods'], true) ?: [];
            $rule['headers_arr'] = json_decode($rule['headers'], true) ?: [];
        }

        return $this->view('settings/api_cors', ['rules' => $rules]);
    }

    public function storeApiCors()
    {
        $origin = $_POST['origin'] ?? '';
        $methods = isset($_POST['methods']) ? json_encode($_POST['methods']) : '["GET","POST"]';
        $headers = isset($_POST['headers']) ? json_encode(array_map('trim', explode(',', $_POST['headers']))) : '["*"]';
        $maxAge = (int) ($_POST['max_age'] ?? 3600);

        if (! empty($origin)) {
            $db = Database::getInstance();
            $db->query('INSERT INTO api_cors (origin, methods, headers, max_age) VALUES (?, ?, ?, ?)', [
                $origin, $methods, $headers, $maxAge,
            ]);
            FlashHelper::set('success', 'toasts.cors_rule_added', 'toasts.cors_rule_added_desc', ['origin' => $origin], true);
        }

        header('Location: /settings/api-cors');
    }

    public function updateApiCors()
    {
        $id = $_POST['id'] ?? null;
        $origin = $_POST['origin'] ?? '';
        $methods = isset($_POST['methods']) ? json_encode($_POST['methods']) : '["GET","POST"]';
        $headers = isset($_POST['headers']) ? json_encode(array_map('trim', explode(',', $_POST['headers']))) : '["*"]';
        $maxAge = (int) ($_POST['max_age'] ?? 3600);

        if ($id && ! empty($origin)) {
            $db = Database::getInstance();
            $db->query('UPDATE api_cors SET origin = ?, methods = ?, headers = ?, max_age = ? WHERE id = ?', [
                $origin, $methods, $headers, $maxAge, $id,
            ]);
            FlashHelper::set('success', 'toasts.cors_rule_updated', 'toasts.cors_rule_updated_desc', ['origin' => $origin], true);
        }

        header('Location: /settings/api-cors');
    }

    public function deleteApiCors()
    {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $db = Database::getInstance();
            $db->query('DELETE FROM api_cors WHERE id = ?', [$id]);
            FlashHelper::set('success', 'toasts.cors_rule_deleted', 'toasts.cors_rule_deleted_desc', [], true);
        }
        header('Location: /settings/api-cors');
    }

    // --- Plugin Management ---

    public function plugins()
    {
        $pluginManager = new PluginManager;
        // Since PluginManager loads everything in constructor/loadPlugins,
        // we can just scan the directory to list them and check status (implied active for now)
        $pluginsDir = ROOT.'/plugins';
        $plugins = [];

        if (is_dir($pluginsDir)) {
            $folders = scandir($pluginsDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }

                $path = $pluginsDir.'/'.$folder;
                if (is_dir($path) && file_exists($path.'/plugin.php')) {
                    // Try to read header from plugin.php
                    $content = file_get_contents($path.'/plugin.php', false, null, 0, 1024); // Read first 1KB
                    preg_match('/Plugin Name:\s*(.*)$/mi', $content, $nameMatch);
                    preg_match('/Version:\s*(.*)$/mi', $content, $verMatch);
                    preg_match('/Description:\s*(.*)$/mi', $content, $descMatch);
                    preg_match('/Author:\s*(.*)$/mi', $content, $authMatch);

                    $plugins[] = [
                        'id' => $folder,
                        'name' => trim($nameMatch[1] ?? $folder),
                        'version' => trim($verMatch[1] ?? '1.0.0'),
                        'description' => trim($descMatch[1] ?? '-'),
                        'author' => trim($authMatch[1] ?? '-'),
                        'path' => $path,
                    ];
                }
            }
        }

        return $this->view('settings/plugins', ['plugins' => $plugins]);
    }

    public function uploadPlugin()
    {
        if (! isset($_FILES['plugin_file']) || $_FILES['plugin_file']['error'] !== UPLOAD_ERR_OK) {
            FlashHelper::set('error', 'toasts.upload_failed', 'toasts.no_file_selected', [], true);
            header('Location: /settings/plugins');
            exit;
        }

        $file = $_FILES['plugin_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'zip') {
            FlashHelper::set('error', 'toasts.upload_failed', 'Only .zip files are allowed', [], true);
            header('Location: /settings/plugins');
            exit;
        }

        $zip = new \ZipArchive;
        if ($zip->open($file['tmp_name']) === true) {
            $extractPath = ROOT.'/plugins/';
            if (! is_dir($extractPath)) {
                mkdir($extractPath, 0755, true);
            }

            // TODO: Better validation to prevent overwriting existing plugins without confirmation?
            // For now, extraction overwrites.

            // Validate content before extracting everything
            // Check if zip has a root folder or just files
            // Logic:
            // 1. Extract to temp.
            // 2. Find plugin.php
            // 3. Move to plugins dir.

            $tempExtract = sys_get_temp_dir().'/mivo_plugin_'.uniqid();
            if (! mkdir($tempExtract, 0755, true)) {
                FlashHelper::set('error', 'toasts.upload_failed', 'Failed to create temp dir', [], true);
                header('Location: /settings/plugins');
                exit;
            }

            $zip->extractTo($tempExtract);
            $zip->close();

            // Search for plugin.php
            $pluginFile = null;
            $pluginRoot = $tempExtract;

            // Recursive iterator to find plugin.php (max depth 2 to avoid deep scanning)
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempExtract));
            foreach ($rii as $file) {
                if ($file->isDir()) {
                    continue;
                }
                if ($file->getFilename() === 'plugin.php') {
                    $pluginFile = $file->getPathname();
                    $pluginRoot = dirname($pluginFile);
                    break;
                }
            }

            if ($pluginFile) {
                // Determine destination name
                // If the immediate parent of plugin.php is NOT the temp dir, use that folder name.
                // Else use the zip name.
                $folderName = basename($pluginRoot);
                if ($pluginRoot === $tempExtract) {
                    $folderName = pathinfo($_FILES['plugin_file']['name'], PATHINFO_FILENAME);
                }

                $dest = $extractPath.$folderName;

                // Move/Copy
                // Using helper or rename. Rename might fail across volumes (temp to project).
                // Use custom recursive copy then delete temp.
                $this->recurseCopy($pluginRoot, $dest);

                FlashHelper::set('success', 'toasts.plugin_installed', 'toasts.plugin_installed_desc', ['name' => $folderName], true);
            } else {
                FlashHelper::set('error', 'toasts.install_failed', 'toasts.invalid_plugin_desc', [], true);
            }

            // Cleanup
            $this->recurseDelete($tempExtract);

        } else {
            FlashHelper::set('error', 'toasts.upload_failed', 'toasts.zip_open_failed_desc', [], true);
        }

        header('Location: /settings/plugins');
    }

    public function deletePlugin()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /settings/plugins');
            exit;
        }

        $id = $_POST['plugin_id'] ?? '';
        if (empty($id)) {
            FlashHelper::set('error', 'common.error', 'Invalid plugin ID', [], true);
            header('Location: /settings/plugins');
            exit;
        }

        // Security check: validate id is just a folder name, no path traversal
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            FlashHelper::set('error', 'common.error', 'Invalid plugin ID format', [], true);
            header('Location: /settings/plugins');
            exit;
        }

        $pluginDir = ROOT.'/plugins/'.$id;

        if (is_dir($pluginDir)) {
            $this->recurseDelete($pluginDir);
            FlashHelper::set('success', 'toasts.plugin_deleted', 'toasts.plugin_deleted_desc', [], true);
        } else {
            FlashHelper::set('error', 'common.error', 'Plugin directory not found', [], true);
        }

        header('Location: /settings/plugins');
        exit;
    }

    // Helper for recursive copy (since rename/move_uploaded_file limit across partitions)
    private function recurseCopy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src.'/'.$file)) {
                    $this->recurseCopy($src.'/'.$file, $dst.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    private function recurseDelete($dir)
    {
        if (! is_dir($dir)) {
            return;
        }
        $scan = scandir($dir);
        foreach ($scan as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($dir.'/'.$file)) {
                $this->recurseDelete($dir.'/'.$file);
            } else {
                unlink($dir.'/'.$file);
            }
        }
        rmdir($dir);
    }
}
