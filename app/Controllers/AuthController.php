<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Helpers\CsrfHelper;
use App\Helpers\FlashHelper;
use App\Models\LoginAttempt;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }

        return $this->view('login');
    }

    public function login()
    {
        $username = is_string($_POST['username'] ?? null) ? $_POST['username'] : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $rateLimitUsername = strtolower(trim($username));
        $clientIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')) ?: 'unknown';
        $loginAttempts = new LoginAttempt;
        // ponytail: split check/record keeps the SQLite schema minimal; add per-key locking if strict concurrent limits matter.
        $blocked = $loginAttempts->isBlocked($rateLimitUsername, $clientIp);

        $user = false;
        if (! $blocked) {
            $userModel = new User;
            $user = $userModel->attempt($username, $password);
        }

        if ($user) {
            $loginAttempts->clear($rateLimitUsername, $clientIp);
            Session::regenerateId();
            CsrfHelper::rotate();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            FlashHelper::set('success', 'Welcome Back', 'Login successful.');
            header('Location: /');
            exit;
        } else {
            if (! $blocked) {
                $loginAttempts->recordFailure($rateLimitUsername, $clientIp);
            }
            FlashHelper::set('error', 'Login Failed', 'Invalid credentials');
            header('Location: /login');
            exit;
        }
    }

    public function logout()
    {
        Session::destroy();
        header('Location: /login');
        exit;
    }
}
