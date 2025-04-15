<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Function to log login attempts
function logLoginAttempt($user_id, $success, $ip, $user_agent) {
    $db = Database::getInstance();
    $db->query(
        "INSERT INTO login_history (user_id, login_time, ip_address, user_agent, success) 
         VALUES (?, NOW(), ?, ?, ?)",
        [$user_id, $ip, $user_agent, $success]
    );
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit();
}

// Check if remember me cookie exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    try {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT id, email, user_type, is_verified FROM users WHERE remember_token = ?",
            [$token]
        );
        
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if account is verified
            if ($user['is_verified'] == 1) {
                // Log successful login
                logLoginAttempt($user['id'], 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Update last login time
                $db->query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
                
                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    header("Location: " . BASE_URL . "admin/");
                } else {
                    header("Location: " . BASE_URL . "pages/dashboard.php");
                }
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Remember token error: " . $e->getMessage());
    }
}

$errors = [];
$email = '';

// Process login form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    if (!$email) {
        $errors[] = "Valid email address is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Check if user exists and if account is locked
            $stmt = $db->query(
                "SELECT id, email, password, user_type, is_verified, failed_login_attempts, account_locked_until 
                 FROM users WHERE email = ?", 
                 [$email]
            );
            
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if account is locked
                if ($user['account_locked_until'] !== null) {
                    $lockTime = new DateTime($user['account_locked_until']);
                    $now = new DateTime();
                    
                    if ($now < $lockTime) {
                        $timeLeft = $now->diff($lockTime);
                        $minutesLeft = ($timeLeft->h * 60) + $timeLeft->i;
                        
                        $errors[] = "Account temporarily locked due to too many failed login attempts. Try again in {$minutesLeft} minutes.";
                        logLoginAttempt($user['id'], 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                    } else {
                        // Lock has expired, reset the lock and failed attempts
                        $db->query(
                            "UPDATE users SET account_locked_until = NULL, failed_login_attempts = 0 WHERE id = ?",
                            [$user['id']]
                        );
                    }
                }
                
                // Proceed if account is not locked
                if (empty($errors)) {
                    if (password_verify($password, $user['password'])) {
                        // Check if account is verified
                        if ($user['is_verified'] == 0) {
                            $errors[] = "Your email address has not been verified. Please check your email for the verification link or <a href='" . BASE_URL . "pages/resend_verification.php'>request a new one</a>.";
                            logLoginAttempt($user['id'], 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                        } else {
                            // Login successful, reset failed attempts
                            $db->query(
                                "UPDATE users SET failed_login_attempts = 0, last_login = NOW() WHERE id = ?",
                                [$user['id']]
                            );
                            
                            // Log successful login
                            logLoginAttempt($user['id'], 1, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                            
                            // Set remember me cookie if requested
                            if ($remember) {
                                $remember_token = bin2hex(random_bytes(32));
                                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                                
                                // Save token to database
                                $db->query(
                                    "UPDATE users SET remember_token = ? WHERE id = ?",
                                    [$remember_token, $user['id']]
                                );
                                
                                // Set secure cookie
                                setcookie(
                                    'remember_token',
                                    $remember_token,
                                    [
                                        'expires' => $expiry,
                                        'path' => '/',
                                        'domain' => '',
                                        'secure' => true,
                                        'httponly' => true,
                                        'samesite' => 'Strict'
                                    ]
                                );
                            }
                            
                            // Set session variables
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['user_type'] = $user['user_type'];
                            
                            // Redirect based on user type
                            if ($user['user_type'] === 'admin') {
                                header("Location: " . BASE_URL . "admin/");
                            } else {
                                header("Location: " . BASE_URL . "pages/dashboard.php");
                            }
                            exit();
                        }
                    } else {
                        // Failed login, increment counter
                        $failedAttempts = $user['failed_login_attempts'] + 1;
                        
                        // Log failed login
                        logLoginAttempt($user['id'], 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                        
                        // If too many failed attempts, lock the account
                        if ($failedAttempts >= 5) {
                            $lockUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                            $db->query(
                                "UPDATE users SET failed_login_attempts = ?, account_locked_until = ? WHERE id = ?",
                                [$failedAttempts, $lockUntil, $user['id']]
                            );
                            
                            $errors[] = "Too many failed login attempts. Account locked for 30 minutes.";
                        } else {
                            $db->query(
                                "UPDATE users SET failed_login_attempts = ? WHERE id = ?",
                                [$failedAttempts, $user['id']]
                            );
                            
                            $errors[] = "Invalid email or password";
                        }
                    }
                }
            } else {
                // User doesn't exist, add generic error
                $errors[] = "Invalid email or password";
                
                // Log failed login without user ID
                logLoginAttempt(null, 0, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred during login. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Check if there was a successful signup
if (isset($_SESSION['signup_success'])) {
    $success = true;
    unset($_SESSION['signup_success']);
}

// Check for redirect from password reset
$resetSuccess = isset($_GET['reset']) && $_GET['reset'] == 'success';

// Include header
$pageTitle = "Login";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Login to <?= SITE_NAME ?></h2>
        
        <?php if (isset($success) && $success): ?>
            <div class="alert alert-success">
                <p>Account created successfully! Please log in.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($resetSuccess): ?>
            <div class="alert alert-success">
                <p>Password has been reset successfully! Please log in with your new password.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="remember" id="remember"> Remember me
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </div>
            
            <div class="form-links">
                <p class="text-center">
                    <a href="<?= BASE_URL ?>pages/forgot_password.php">Forgot Password?</a>
                </p>
                <p class="text-center">
                    Don't have an account? <a href="<?= BASE_URL ?>pages/signup.php">Sign up</a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 