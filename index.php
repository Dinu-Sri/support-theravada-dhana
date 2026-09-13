<?php
require_once 'includes/auth.php';

$auth = getAuth();

// Redirect to dashboard if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $result = $auth->login($_POST['email'], $_POST['password']);
    if ($result['success']) {
        header('Location: dashboard.php');
        exit;
    } else {
        $loginError = $result['error'];
    }
}

// Handle registration form submission
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $isMonk = isset($_POST['is_monk']) ? 1 : 0;
    $result = $auth->register(
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['email'],
        $_POST['contact_number'],
        $_POST['password'],
        $isMonk
    );

    if ($result['success']) {
        // Store success message in session and redirect
        $_SESSION['registration_success'] = true;
        $_SESSION['success_message'] = 'Account created successfully! Welcome to Dāna Reservation System.';
        header('Location: dashboard.php');
        exit;
    } else {
        $registerErrors = $result['errors'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dāna Reservation System - Login</title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .login-page {
            background-image: url('uploads/bck.webp');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        /* Responsive background for different screen sizes */
        @media (max-width: 768px) {
            .login-page {
                background-attachment: scroll;
                background-size: cover;
            }
        }

        @media (max-width: 480px) {
            .login-page {
                background-size: cover;
                background-position: center center;
            }
        }

        /* Add overlay for better text readability */
        .login-page::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: -1;
        }

        .login-card {
            position: relative;
            z-index: 1;
        }
        
        /* Monk Checkbox Styling */
        .monk-checkbox-group {
            background: linear-gradient(135deg, #fff8e1, #f3e5ab);
            border: 2px solid #ff9800;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            accent-color: #ff9800;
        }
        
        .checkbox-label {
            font-weight: 600;
            color: #e65100;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-help {
            font-size: 0.9rem;
            color: #bf5f00;
            margin: 0;
            font-style: italic;
        }
        
        /* Modal Styling */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .modal-content {
            padding: 25px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .vinaya-text {
            line-height: 1.6;
            color: #333;
        }
        
        .vinaya-text p {
            margin-bottom: 15px;
        }
        
        .vinaya-text ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        
        .vinaya-text li {
            margin-bottom: 8px;
        }
        
        .vinaya-text strong {
            color: #e65100;
        }
        
        .closing-blessing {
            background: #f3e5ab;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #ff9800;
            font-style: italic;
            text-align: center;
        }
        
        .modal-footer {
            background: #f5f5f5;
            padding: 20px;
            display: flex;
            gap: 15px;
            justify-content: center;
            border-top: 1px solid #ddd;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="fas fa-lotus"></i> Dāna Reservation</h1>
                <p>Welcome to our sacred reservation system</p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="auth-form <?php echo !isset($registerErrors) ? 'active' : ''; ?>" method="POST">
                <input type="hidden" name="action" value="login">

                <h2>Sign In</h2>

                <?php if (isset($loginError)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($loginError); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="loginEmail">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="loginEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>

                <div style="text-align: right; margin-bottom: 15px;">
                    <a href="forgot-password.php" style="color: #ff9800; text-decoration: none; font-size: 14px;">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>

                <p class="auth-switch">
                    Don't have an account?
                    <a href="#" onclick="showRegisterForm()">Create Account</a>
                </p>
            </form>

            <!-- Registration Form -->
            <form id="registerForm" class="auth-form <?php echo isset($registerErrors) ? 'active' : ''; ?>" method="POST">
                <input type="hidden" name="action" value="register">

                <h2>Create Account</h2>

                <?php if (isset($registerErrors)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul>
                            <?php foreach ($registerErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">
                            <i class="fas fa-user"></i>
                            First Name
                        </label>
                        <input type="text" id="firstName" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName">
                            <i class="fas fa-user"></i>
                            Last Name
                        </label>
                        <input type="text" id="lastName" name="last_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="registerEmail">
                        <i class="fas fa-envelope"></i>
                        Email Address
                    </label>
                    <input type="email" id="registerEmail" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="contactNumber">
                        <i class="fas fa-phone"></i>
                        Contact Number
                    </label>
                    <input type="tel" id="contactNumber" name="contact_number" required>
                </div>
                
                <div class="form-group">
                    <label for="registerPassword">
                        <i class="fas fa-lock"></i>
                        Password
                    </label>
                    <input type="password" id="registerPassword" name="password" required>
                </div>
                
                <!-- Monk Status Checkbox -->
                <div class="form-group monk-checkbox-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="isMonk" name="is_monk" value="1">
                        <label for="isMonk" class="checkbox-label">
                            <i class="fas fa-temple"></i>
                            I am a monk (Bhikkhu)
                        </label>
                    </div>
                    <p class="checkbox-help">
                        Please check this box if you are a Buddhist monk. Special Vinaya guidelines apply.
                    </p>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    Create Account
                </button>
                
                <p class="auth-switch">
                    Already have an account? 
                    <a href="#" onclick="showLoginForm()">Sign In</a>
                </p>
            </form>
        </div>
    </div>

    <!-- Monk Vinaya Disclosure Modal -->
    <div id="monkModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-temple"></i> Important Vinaya Disclosure</h3>
            </div>
            <div class="modal-content">
                <div class="vinaya-text">
                    <p>In accordance with the <strong>Theravāda Vinaya discipline</strong>, bhikkhus (monks) are strictly prohibited from handling money or engaging in financial transactions.</p>
                    
                    <ul>
                        <li>A monk is not permitted to give or receive donations in the form of money or to manage personal funds.</li>
                        <li>A monk may not initiate or conduct financial transactions on his own behalf, either directly or indirectly.</li>
                        <li>All acts of dāna (almsgiving or offerings) must be carried out solely through lay supporters who arrange and manage such contributions in a manner consistent with the Vinaya.</li>
                        <li>If a monk is to arrange a donation to the International Institute of Theravada, he can only do it through his blood relatives (ñātaka) or devotees who have invited him with four requisites (pavārita dāyaka).</li>
                    </ul>
                    
                    <p>This guideline exists to safeguard the purity of the Saṅgha and to maintain the original discipline established by the Buddha.</p>
                    
                    <p>Therefore, any offerings intended for the support of bhikkhus should be organized only through lay stewards or appointed representatives, ensuring that monastics remain fully in compliance with the Vinaya.</p>
                </div>
            </div>
            <div class="modal-footer">
                <div style="display: flex; width: 100%; gap: 15px; justify-content: center;">
                    <button type="button" onclick="cancelMonkRegistration()" style="flex: 1; max-width: 180px; min-width: 180px; width: 180px; height: 45px; padding: 0; margin: 0; box-sizing: border-box; text-align: center; font-size: 14px; border: none; border-radius: 4px; background: #6c757d; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-weight: normal; text-decoration: none; outline: none;">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </button>
                    <button type="button" onclick="acceptMonkTerms()" style="flex: 1; max-width: 180px; min-width: 180px; width: 180px; height: 45px; padding: 0; margin: 0; box-sizing: border-box; text-align: center; font-size: 14px; border: none; border-radius: 4px; background: #ff9800; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-weight: normal; text-decoration: none; outline: none;">
                        <i class="fas fa-check"></i>
                        <span>I Accept & Understand</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
    
    <!-- Debug script to check function availability - runs after auth.js loads -->
    <script>
        // Define monk modal functions inline to ensure they're available
        window.isMonkConfirmed = false;
        
        window.showMonkModal = function() {
            const modal = document.getElementById('monkModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        window.closeMonkModal = function() {
            const modal = document.getElementById('monkModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        window.cancelMonkRegistration = function() {
            const monkCheckbox = document.getElementById('isMonk');
            if (monkCheckbox) {
                monkCheckbox.checked = false;
            }
            window.isMonkConfirmed = false;
            window.closeMonkModal();
        }
        
        window.acceptMonkTerms = function() {
            window.isMonkConfirmed = true;
            window.closeMonkModal();
            
            // Show success message
            showSuccessMessage('Monk registration terms accepted. You can now proceed with creating your account.');
        }
        
        function showSuccessMessage(message) {
            // Remove existing messages
            const existingMessages = document.querySelectorAll('.js-success-message, .js-error-message');
            existingMessages.forEach(msg => msg.remove());
            
            // Create new success message
            const successDiv = document.createElement('div');
            successDiv.className = 'success-message js-success-message';
            successDiv.style.cssText = 'background: #d4edda; color: #155724; padding: 15px; margin: 15px 0; border: 1px solid #c3e6cb; border-radius: 4px; display: flex; align-items: center; gap: 10px;';
            successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
            
            // Insert success message in the registration form
            const registerForm = document.getElementById('registerForm');
            const formTitle = registerForm.querySelector('h2');
            formTitle.insertAdjacentElement('afterend', successDiv);
            
            // Auto-remove success message after 5 seconds
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.remove();
                }
            }, 5000);
        }
        
        // Set up checkbox listener when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            const monkCheckbox = document.getElementById('isMonk');
            if (monkCheckbox) {
                monkCheckbox.addEventListener('change', function() {
                    if (this.checked && !window.isMonkConfirmed) {
                        window.showMonkModal();
                    }
                });
            }
        });
    </script>
</body>
</html>
