<?php
/**
 * Authentication System for Dhana Booking
 */

require_once __DIR__ . '/../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
            // ✅ FIX #2: Normalize email to lowercase to prevent case-sensitive duplicates
            $email = strtolower(trim($email));

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
            $user = $this->db->fetchOne(
                "SELECT id, first_name, last_name, email, password_hash, is_active FROM users WHERE email = ?",
                [$email]
            );
            
            if (!$user) {
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            if (!$user['is_active']) {
                return ['success' => false, 'error' => 'Account is deactivated'];
            }
            
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Invalid email or password'];
            }
            
            // Create session
            $this->createSession($user['id'], $user['first_name'], $user['last_name'], $user['email']);
            
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
        
        return [
            'id' => $_SESSION['user_id'],
            'first_name' => $_SESSION['user_first_name'],
            'last_name' => $_SESSION['user_last_name'],
            'email' => $_SESSION['user_email']
        ];
    }
    
    /**
     * Create user session
     */
    private function createSession($userId, $firstName, $lastName, $email) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_first_name'] = $firstName;
        $_SESSION['user_last_name'] = $lastName;
        $_SESSION['user_email'] = $email;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistration($firstName, $lastName, $email, $contactNumber, $password) {
        $errors = [];
        
        if (empty($firstName) || strlen($firstName) < 2) {
            $errors[] = 'First name must be at least 2 characters';
        }
        
        if (empty($lastName) || strlen($lastName) < 2) {
            $errors[] = 'Last name must be at least 2 characters';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($contactNumber) || strlen($contactNumber) < 10) {
            $errors[] = 'Valid contact number is required';
        }
        
        if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
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

            // Insert new token
            $this->db->query(
                "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $token, $expiresAt]
            );

            // Send email
            $emailService = getEmailService();
            $userName = $user['first_name'] . ' ' . $user['last_name'];
            $emailResult = $emailService->sendPasswordResetEmail($user['email'], $userName, $token);

            if (!$emailResult['success']) {
                error_log("Failed to send password reset email to: {$email}");
                return ['success' => false, 'error' => 'Failed to send reset email. Please try again later.'];
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
            $resetToken = $this->db->fetchOne(
                "SELECT prt.*, u.email, u.first_name, u.last_name
                 FROM password_reset_tokens prt
                 JOIN users u ON prt.user_id = u.id
                 WHERE prt.token = ? AND prt.used = FALSE AND prt.expires_at > NOW()",
                [$token]
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
            // Verify token
            $tokenResult = $this->verifyResetToken($token);
            if (!$tokenResult['success']) {
                return $tokenResult;
            }

            $resetToken = $tokenResult['data'];

            // Validate password
            if (empty($newPassword) || strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'error' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            // Hash new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            // Update user password
            $this->db->query(
                "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
                [$passwordHash, $resetToken['user_id']]
            );

            // Mark token as used
            $this->db->query(
                "UPDATE password_reset_tokens SET used = TRUE, used_at = NOW() WHERE token = ?",
                [$token]
            );

            return ['success' => true, 'message' => 'Password reset successfully. You can now login with your new password.'];

        } catch (Exception $e) {
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
    if (!$auth->isLoggedIn() || !$auth->checkSessionTimeout()) {
        header('Location: index.php');
        exit;
    }
}
?>
