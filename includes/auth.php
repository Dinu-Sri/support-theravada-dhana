<?php
/**
 * Authentication System for Dhana Booking
 */

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    set_exception_handler(function ($error) {
        error_log('Unhandled donor application error: ' . $error->getMessage());
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Temporarily unavailable</title><body style="margin:0;background:#f7f4ef;color:#26221f;font-family:system-ui,sans-serif;display:grid;min-height:100vh;place-items:center">'
            . '<main style="max-width:520px;margin:24px;padding:32px;background:#fff;border:1px solid #e9e1d7;border-radius:16px;text-align:center">'
            . '<h1 style="color:#a8621c">Temporarily unavailable</h1><p>We could not complete this request right now. Please try again shortly.</p>'
            . '<p lang="si">මෙම ඉල්ලීම දැන් සම්පූර්ණ කළ නොහැක. කරුණාකර මඳ වේලාවකින් නැවත උත්සාහ කරන්න.</p></main></body></html>';
    });
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken($token) {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Register a new user
     */
    public function register($firstName, $lastName, $email, $contactNumber, $password, $isMonk = 0) {
        try {
            $firstName = trim((string)$firstName);
            $lastName = trim((string)$lastName);
            $email = strtolower(trim($email));
            $contactNumber = trim((string)$contactNumber);
            $isMonk = $isMonk ? 1 : 0;

            // Validate input
            $errors = $this->validateRegistration($firstName, $lastName, $email, $contactNumber, $password);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // ✅ FIX #3: Use database transaction for atomic operation
            $this->db->getConnection()->beginTransaction();

            try {
                // Check if email already exists
                $existingUser = $this->db->fetchOne(
                    "SELECT id FROM users WHERE email = ?",
                    [$email]
                );

                if ($existingUser) {
                    $this->db->getConnection()->rollback();
                    return ['success' => false, 'errors' => ['This email address is already registered. Please use a different email or try logging in.']];
                }

                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user with is_monk field
                $this->db->query(
                    "INSERT INTO users (first_name, last_name, email, contact_number, password_hash, is_monk) VALUES (?, ?, ?, ?, ?, ?)",
                    [$firstName, $lastName, $email, $contactNumber, $passwordHash, $isMonk]
                );

                $userId = $this->db->lastInsertId();

                // Commit transaction
                $this->db->getConnection()->commit();

                // Auto-login after registration
                $this->createSession($userId, $firstName, $lastName, $email);

                return ['success' => true, 'message' => 'Registration successful'];

            } catch (Exception $innerException) {
                // Rollback transaction on any error
                $this->db->getConnection()->rollback();
                throw $innerException; // Re-throw to outer catch
            }

        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());

            // ✅ FIX #1: Detect duplicate email error and provide user-friendly message
            $errorMessage = $e->getMessage();

            // Check for MySQL duplicate entry error (Error code 1062)
            if (strpos($errorMessage, 'Duplicate entry') !== false ||
                strpos($errorMessage, '1062') !== false ||
                strpos($errorMessage, 'duplicate key') !== false) {
                return ['success' => false, 'errors' => ['This email address is already registered. Please use a different email or try logging in.']];
            }

            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        try {
            $email = strtolower(trim($email));
            $attemptWindow = 300;
            $attemptLimit = 5;
            $now = time();
            $attempts = $_SESSION['login_attempts'] ?? [];
            $attempts = array_values(array_filter($attempts, function ($timestamp) use ($now, $attemptWindow) {
                return ($now - $timestamp) < $attemptWindow;
            }));
            if (count($attempts) >= $attemptLimit) {
                return ['success' => false, 'error' => 'Too many sign-in attempts. Please wait five minutes and try again.'];
            }

            $user = $this->db->fetchOne(
                "SELECT id, first_name, last_name, email, password_hash, is_active FROM users WHERE email = ?",
                [$email]
            );
            
            if (!$user) {
                $attempts[] = $now;
                $_SESSION['login_attempts'] = $attempts;
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            if (!$user['is_active']) {
                return ['success' => false, 'error' => 'Account is deactivated'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                $attempts[] = $now;
                $_SESSION['login_attempts'] = $attempts;
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            // Create session
            $this->createSession($user['id'], $user['first_name'], $user['last_name'], $user['email']);
            unset($_SESSION['login_attempts']);
            
            return ['success' => true, 'message' => 'Login successful'];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $user = $this->db->fetchOne(
            "SELECT id, first_name, last_name, email, is_monk, role, is_active, password_hash FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );

        if (!$user || !$user['is_active'] ||
            (!empty($_SESSION['password_version']) && !hash_equals($_SESSION['password_version'], $user['password_hash']))) {
            $this->logout();
            return null;
        }

        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name'] = $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['password_version'] = $user['password_hash'];

        unset($user['password_hash'], $user['is_active']);
        return $user;
    }
    
    /**
     * Create user session
     */
    private function createSession($userId, $firstName, $lastName, $email) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_first_name'] = $firstName;
        $_SESSION['user_last_name'] = $lastName;
        $_SESSION['user_email'] = $email;
        $_SESSION['login_time'] = time();
        $user = $this->db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
        $_SESSION['password_version'] = $user ? $user['password_hash'] : '';
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistration($firstName, $lastName, $email, $contactNumber, $password) {
        $errors = [];
        
        if (empty($firstName) || strlen($firstName) < 2 || strlen($firstName) > 50) {
            $errors[] = 'First name must be between 2 and 50 characters';
        }
        
        if (empty($lastName) || strlen($lastName) < 2 || strlen($lastName) > 50) {
            $errors[] = 'Last name must be between 2 and 50 characters';
        }
        
        if (empty($email) || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($contactNumber) || strlen($contactNumber) < 10 || strlen($contactNumber) > 20) {
            $errors[] = 'Valid contact number is required';
        }
        
        if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH || strlen($password) > 4096) {
            $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
        }
        
        return $errors;
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn() && isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
        }
        return true;
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        try {
            $email = strtolower(trim($email));
            $lastRequest = $_SESSION['password_reset_requested_at'] ?? 0;
            if (time() - $lastRequest < 60) {
                return ['success' => true, 'message' => 'If an account exists with this email, a password reset link has been sent.'];
            }
            $_SESSION['password_reset_requested_at'] = time();
            require_once __DIR__ . '/email.php';
            require_once __DIR__ . '/../config/email.php';

            // Check if user exists
            $user = $this->db->fetchOne(
                "SELECT id, first_name, last_name, email, is_active FROM users WHERE email = ?",
                [$email]
            );

            // Always return success message for security (don't reveal if email exists)
            if (!$user) {
                return ['success' => true, 'message' => 'If an account exists with this email, a password reset link has been sent.'];
            }

            if (!$user['is_active']) {
                return ['success' => true, 'message' => 'If an account exists with this email, a password reset link has been sent.'];
            }

            // Generate secure random token
            $token = bin2hex(random_bytes(32));

            // Calculate expiry time
            $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

            // Delete any existing unused tokens for this user
            $this->db->query(
                "DELETE FROM password_reset_tokens WHERE user_id = ? AND used = FALSE",
                [$user['id']]
            );

            // Store only a hash. The raw token is sent to the user and never persisted.
            $tokenHash = hash('sha256', $token);
            $this->db->query(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $tokenHash, $expiresAt]
            );

            // Send email
            $emailService = getEmailService();
            $userName = $user['first_name'] . ' ' . $user['last_name'];
            $emailResult = $emailService->sendPasswordResetEmail($user['email'], $userName, $token);

            if (!$emailResult['success']) {
                error_log("Failed to send password reset email to: {$email}");
                return ['success' => true, 'message' => 'If an account exists with this email, a password reset link has been sent.'];
            }

            return ['success' => true, 'message' => 'If an account exists with this email, a password reset link has been sent.'];

        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to process password reset request. Please try again.'];
        }
    }

    /**
     * Verify reset token
     */
    public function verifyResetToken($token) {
        try {
            $tokenHash = hash('sha256', $token);
            $resetToken = $this->db->fetchOne(
                "SELECT prt.*, u.email, u.first_name, u.last_name
                 FROM password_reset_tokens prt
                 JOIN users u ON prt.user_id = u.id
                 WHERE prt.token = ? AND prt.used = FALSE AND prt.expires_at > NOW()",
                [$tokenHash]
            );

            if (!$resetToken) {
                return ['success' => false, 'error' => 'Invalid or expired reset token'];
            }

            return ['success' => true, 'data' => $resetToken];

        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to verify token'];
        }
    }

    /**
     * Reset password using token
     */
    public function resetPassword($token, $newPassword) {
        try {
            // Validate password
            if (empty($newPassword) || strlen($newPassword) < PASSWORD_MIN_LENGTH || strlen($newPassword) > 4096) {
                return ['success' => false, 'error' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            $connection = $this->db->getConnection();
            $connection->beginTransaction();
            $tokenHash = hash('sha256', $token);
            $resetToken = $this->db->fetchOne(
                "SELECT * FROM password_reset_tokens WHERE token = ? AND used = FALSE AND expires_at > NOW() FOR UPDATE",
                [$tokenHash]
            );
            if (!$resetToken) {
                $connection->rollBack();
                return ['success' => false, 'error' => 'Invalid or expired reset token'];
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user password
            $this->db->query(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$passwordHash, $resetToken['user_id']]
            );

            // Mark token as used
            $this->db->query(
                "UPDATE password_reset_tokens SET used = TRUE, used_at = NOW() WHERE token = ?",
                [$tokenHash]
            );

            $connection->commit();

            return ['success' => true, 'message' => 'Password reset successfully. You can now login with your new password.'];

        } catch (Exception $e) {
            if (isset($connection) && $connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reset password. Please try again.'];
        }
    }
}

// Helper function to get auth instance
function getAuth() {
    return new Auth();
}

// Helper function to require login
function requireLogin() {
    $auth = getAuth();
    if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout() || !$auth->getCurrentUser()) {
        header('Location: index.php');
        exit;
    }
}
?>
