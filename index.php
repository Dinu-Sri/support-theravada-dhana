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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $result = ['success' => false, 'error' => 'Your session expired. Please refresh and try again.'];
    } else {
        $result = $auth->login($_POST['email'] ?? '', $_POST['password'] ?? '');
    }
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
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $result = ['success' => false, 'errors' => ['Your session expired. Please refresh and try again.']];
    } elseif (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
        $result = ['success' => false, 'errors' => ['Passwords do not match.']];
    } elseif ($isMonk && ($_POST['vinaya_accepted'] ?? '') !== '1') {
        $result = ['success' => false, 'errors' => ['Please read and accept the Vinaya guidance before registering as a monk.']];
    } else {
        $result = $auth->register(
            $_POST['first_name'] ?? '',
            $_POST['last_name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['contact_number'] ?? '',
            $_POST['password'] ?? '',
            $isMonk
        );
    }

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
<?php echo ui_html_open(); ?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo te('Dāna Reservation System - Login', 'දාන වෙන්කිරීමේ පද්ධතිය - පිවිසීම'); ?></title>
    <?php include 'includes/favicon.php'; ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/pro-ui.css?v=20260915">
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
                <?php ui_lang_switch(); ?>
                <h1><i class="fas fa-lotus"></i> <?php echo te('Dāna Reservation', 'දාන වෙන්කිරීම'); ?></h1>
                <p><?php echo te('Welcome to our sacred reservation system', 'අපගේ පූජනීය වෙන්කිරීමේ පද්ධතියට සාදරයෙන් පිළිගනිමු'); ?></p>
            </div>

            <!-- Login Form -->
            <form id="loginForm" class="auth-form <?php echo !isset($registerErrors) ? 'active' : ''; ?>" method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="login">

                <h2><?php echo te('Sign In', 'පිවිසෙන්න'); ?></h2>

                <?php if (isset($loginError)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo te_msg($loginError); ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="loginEmail">
                        <i class="fas fa-envelope"></i>
                        <?php echo te('Email Address', 'විද්‍යුත් තැපැල් ලිපිනය'); ?>
                    </label>
                    <input type="email" id="loginEmail" name="email" autocomplete="email" maxlength="100" required>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">
                        <i class="fas fa-lock"></i>
                        <?php echo te('Password', 'මුරපදය'); ?>
                    </label>
                    <input type="password" id="loginPassword" name="password" autocomplete="current-password" required>
                </div>

                <div style="text-align: right; margin-bottom: 15px;">
                    <a href="forgot-password.php" style="color: #ff9800; text-decoration: none; font-size: 14px;">
                        <i class="fas fa-key"></i> <?php echo te('Forgot Password?', 'මුරපදය අමතකද?'); ?>
                    </a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <?php echo te('Sign In', 'පිවිසෙන්න'); ?>
                </button>

                <p class="auth-switch">
                    <?php echo te("Don't have an account?", 'ගිණුමක් නැද්ද?'); ?>
                    <a href="#registerForm" onclick="showRegisterForm(); return false;"><?php echo te('Create Account', 'ගිණුමක් සාදන්න'); ?></a>
                </p>
            </form>

            <!-- Registration Form -->
            <form id="registerForm" class="auth-form <?php echo isset($registerErrors) ? 'active' : ''; ?>" method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="register">

                <h2><?php echo te('Create Account', 'ගිණුමක් සාදන්න'); ?></h2>

                <?php if (isset($registerErrors)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <ul>
                            <?php foreach ($registerErrors as $error): ?>
                                <li><?php echo te_msg($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">
                            <i class="fas fa-user"></i>
                            <?php echo te('First Name', 'මුල් නම'); ?>
                        </label>
                        <input type="text" id="firstName" name="first_name" autocomplete="given-name" maxlength="50" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastName">
                            <i class="fas fa-user"></i>
                            <?php echo te('Last Name', 'අවසන් නම'); ?>
                        </label>
                        <input type="text" id="lastName" name="last_name" autocomplete="family-name" maxlength="50" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="registerEmail">
                        <i class="fas fa-envelope"></i>
                        <?php echo te('Email Address', 'විද්‍යුත් තැපැල් ලිපිනය'); ?>
                    </label>
                    <input type="email" id="registerEmail" name="email" autocomplete="email" maxlength="100" required>
                </div>
                
                <div class="form-group">
                    <label for="contactNumber">
                        <i class="fas fa-phone"></i>
                        <?php echo te('Contact Number', 'දුරකථන අංකය'); ?>
                    </label>
                    <input type="tel" id="contactNumber" name="contact_number" autocomplete="tel" inputmode="tel" maxlength="20" required>
                </div>
                
                <div class="form-group">
                    <label for="registerPassword">
                        <i class="fas fa-lock"></i>
                        <?php echo te('Password', 'මුරපදය'); ?>
                    </label>
                    <input type="password" id="registerPassword" name="password" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                </div>

                <div class="form-group">
                    <label for="confirmPassword"><i class="fas fa-lock"></i> <?php echo te('Confirm Password', 'මුරපදය තහවුරු කරන්න'); ?></label>
                    <input type="password" id="confirmPassword" name="confirm_password" autocomplete="new-password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                </div>
                
                <!-- Monk Status Checkbox -->
                <div class="form-group monk-checkbox-group">
                    <div class="checkbox-wrapper">
                        <input type="checkbox" id="isMonk" name="is_monk" value="1">
                        <input type="hidden" id="vinayaAccepted" name="vinaya_accepted" value="0">
                        <label for="isMonk" class="checkbox-label">
                            <i class="fas fa-temple"></i>
                            <?php echo te('I am a monk (Bhikkhu)', 'මම භික්ෂුවක් වෙමි'); ?>
                        </label>
                    </div>
                    <p class="checkbox-help">
                        <?php echo te('Please check this box if you are a Buddhist monk. Special Vinaya guidelines apply.', 'ඔබ බෞද්ධ භික්ෂුවක් නම් මෙය සලකුණු කරන්න. විශේෂ විනය නීති අදාළ වේ.'); ?>
                    </p>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i>
                    <?php echo te('Create Account', 'ගිණුමක් සාදන්න'); ?>
                </button>
                
                <p class="auth-switch">
                    <?php echo te('Already have an account?', 'දැනටමත් ගිණුමක් තිබේද?'); ?>
                    <a href="#loginForm" onclick="showLoginForm(); return false;"><?php echo te('Sign In', 'පිවිසෙන්න'); ?></a>
                </p>
            </form>
        </div>
    </div>

    <!-- Monk Vinaya Disclosure Modal -->
    <div id="monkModal" class="modal-overlay" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="monkModalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="monkModalTitle"><i class="fas fa-temple"></i> <?php echo te('Important Vinaya Disclosure', 'වැදගත් විනය දැනුම්දීම'); ?></h3>
            </div>
            <div class="modal-content">
                <div class="vinaya-text">
                    <p><?php echo t('In accordance with the <strong>Theravāda Vinaya discipline</strong>, bhikkhus (monks) are strictly prohibited from handling money or engaging in financial transactions.', '<strong>ථේරවාද විනය</strong> අනුව, භික්ෂූන් වහන්සේලාට මුදල් පරිහරණය කිරීම හෝ මූල්‍ය ගනුදෙනුවල යෙදීම සපුරා තහනම් ය.'); ?></p>
                    
                    <ul>
                        <li><?php echo te('A monk is not permitted to give or receive donations in the form of money or to manage personal funds.', 'භික්ෂුවකට මුදල් ලෙස පරිත්‍යාග දීමට හෝ ලැබීමට, හෝ පෞද්ගලික අරමුදල් කළමනාකරණය කිරීමට අවසර නැත.'); ?></li>
                        <li><?php echo te('A monk may not initiate or conduct financial transactions on his own behalf, either directly or indirectly.', 'භික්ෂුවකට තමන් වෙනුවෙන් සෘජුව හෝ වක්‍රව මූල්‍ය ගනුදෙනු ආරම්භ කිරීමට හෝ පැවැත්වීමට නොහැක.'); ?></li>
                        <li><?php echo te('All acts of dāna (almsgiving or offerings) must be carried out solely through lay supporters who arrange and manage such contributions in a manner consistent with the Vinaya.', 'සියලු දාන ක්‍රියා විනයට ගැළපෙන පරිදි ගිහි උපකාරකයන් හරහා පමණක් සිදු කළ යුතුය.'); ?></li>
                        <li><?php echo te('If a monk is to arrange a donation to the International Institute of Theravada, he can only do it through his blood relatives (ñātaka) or devotees who have invited him with four requisites (pavārita dāyaka).', 'භික්ෂුවකට ජාත්‍යන්තර ථේරවාද ආයතනයට පරිත්‍යාගයක් සංවිධානය කිරීමට අවශ්‍ය නම්, එය ඥාතීන් (ඤාතක) හරහා හෝ පිරිකර සතරෙන් ආරාධනා කළ දායකයන් (පවාරිත දායක) හරහා පමණක් කළ හැක.'); ?></li>
                    </ul>
                    
                    <p><?php echo te('This guideline exists to safeguard the purity of the Saṅgha and to maintain the original discipline established by the Buddha.', 'මෙම නියමය සංඝයාගේ පිරිසිදුභාවය රැක ගැනීමට සහ බුදුන් වහන්සේ පැනවූ මුල් විනය පවත්වා ගැනීමට ය.'); ?></p>
                    
                    <p><?php echo te('Therefore, any offerings intended for the support of bhikkhus should be organized only through lay stewards or appointed representatives, ensuring that monastics remain fully in compliance with the Vinaya.', 'එබැවින් භික්ෂූන් වහන්සේලාට අදහස් කළ පූජා ගිහි භාරකරුවන් හෝ පත් කළ නියෝජිතයන් හරහා පමණක් සංවිධානය කළ යුතුය.'); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <div style="display: flex; width: 100%; gap: 15px; justify-content: center;">
                    <button type="button" onclick="cancelMonkRegistration()" style="flex: 1; max-width: 180px; min-width: 180px; width: 180px; height: 45px; padding: 0; margin: 0; box-sizing: border-box; text-align: center; font-size: 14px; border: none; border-radius: 4px; background: #6c757d; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-weight: normal; text-decoration: none; outline: none;">
                        <i class="fas fa-times"></i>
                        <span><?php echo te('Cancel', 'අවලංගු කරන්න'); ?></span>
                    </button>
                    <button type="button" onclick="acceptMonkTerms()" style="flex: 1; max-width: 180px; min-width: 180px; width: 180px; height: 45px; padding: 0; margin: 0; box-sizing: border-box; text-align: center; font-size: 14px; border: none; border-radius: 4px; background: #ff9800; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-weight: normal; text-decoration: none; outline: none;">
                        <i class="fas fa-check"></i>
                        <span><?php echo te('I Accept & Understand', 'මම පිළිගනිමි'); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/auth.js?v=20261111"></script>
</body>
</html>
