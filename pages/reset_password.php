<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL);
    exit();
}

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;
$valid_token = false;
$email = '';

// Validate token
if (empty($token)) {
    $errors[] = "Invalid or missing reset token";
} else {
    try {
        $db = Database::getInstance();
        
        // Check if token exists and is valid
        $stmt = $db->query(
            "SELECT email, expiry_date, used FROM password_resets 
             WHERE token = ? AND used = 0 AND expiry_date > NOW() 
             ORDER BY created_at DESC LIMIT 1",
            [$token]
        );
        
        $reset = $stmt->fetch();
        
        if ($reset) {
            $valid_token = true;
            $email = $reset['email'];
        } else {
            // Check if token exists but has expired
            $expiredStmt = $db->query(
                "SELECT expiry_date FROM password_resets 
                 WHERE token = ? AND used = 0 AND expiry_date <= NOW()",
                [$token]
            );
            
            if ($expiredStmt->fetch()) {
                $errors[] = "This password reset link has expired. Please request a new one.";
            } else {
                $errors[] = "Invalid reset token or the link has already been used.";
            }
        }
    } catch (Exception $e) {
        $errors[] = "An error occurred. Please try again later.";
        error_log("Reset token validation error: " . $e->getMessage());
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Password validation
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no validation errors, reset the password
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Start transaction
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            
            // Update the user's password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $db->query(
                "UPDATE users SET password = ? WHERE email = ?",
                [$hashedPassword, $email]
            );
            
            if ($updateStmt) {
                // Mark token as used
                $markUsedStmt = $db->query(
                    "UPDATE password_resets SET used = 1 WHERE token = ?",
                    [$token]
                );
                
                if ($markUsedStmt) {
                    $pdo->commit();
                    $success = true;
                    
                    // Clear any failed login attempts
                    $db->query(
                        "UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE email = ?",
                        [$email]
                    );
                } else {
                    $pdo->rollBack();
                    $errors[] = "Failed to process your request. Please try again.";
                }
            } else {
                $pdo->rollBack();
                $errors[] = "Failed to update password. Please try again.";
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "An error occurred. Please try again later.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// Include header
$pageTitle = "Reset Password";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Reset Your Password</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Your password has been reset successfully!</p>
                <p class="mt-3">
                    <a href="<?= BASE_URL ?>pages/login.php?reset=success" class="btn">Login with New Password</a>
                </p>
            </div>
        <?php elseif ($valid_token): ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p>Enter your new password below.</p>
            
            <form method="post" action="<?= $_SERVER['REQUEST_URI'] ?>" id="resetForm">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="password-strength-meter" style="margin-top: 5px;">
                        <div class="strength-bar" style="height: 5px; background-color: #ddd; border-radius: 2px;">
                            <span id="strengthBar" style="display: block; height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background-color 0.3s;"></span>
                        </div>
                        <small id="passwordStrengthText">Password strength: Not entered</small>
                    </div>
                    <small>
                        Password must have at least 8 characters, including uppercase, number, and special character.
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Reset Password</button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <p>The password reset link is invalid or has expired. Please request a new one.</p>
            
            <div class="text-center" style="margin-top: 2rem;">
                <a href="<?= BASE_URL ?>pages/forgot_password.php" class="btn">Request New Reset Link</a>
                <a href="<?= BASE_URL ?>pages/login.php" class="btn" style="background-color: #6c757d; margin-top: 0.5rem;">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let message = '';
            
            // Check length
            if (password.length >= 8) {
                strength += 25;
            }
            
            // Check uppercase
            if (/[A-Z]/.test(password)) {
                strength += 25;
            }
            
            // Check numbers
            if (/[0-9]/.test(password)) {
                strength += 25;
            }
            
            // Check special characters
            if (/[^A-Za-z0-9]/.test(password)) {
                strength += 25;
            }
            
            // Update visual indicator
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) {
                strengthBar.style.backgroundColor = '#ff4d4d'; // Red
                message = 'Weak';
            } else if (strength <= 50) {
                strengthBar.style.backgroundColor = '#ffa64d'; // Orange
                message = 'Fair';
            } else if (strength <= 75) {
                strengthBar.style.backgroundColor = '#ffff4d'; // Yellow
                message = 'Good';
            } else {
                strengthBar.style.backgroundColor = '#4CAF50'; // Green
                message = 'Strong';
            }
            
            strengthText.textContent = 'Password strength: ' + message;
        });
        
        // Password match validation
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation
        resetForm.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            let isValid = true;
            const errorMessages = [];
            
            // Check password strength
            if (password.length < 8) {
                isValid = false;
                errorMessages.push('Password must be at least 8 characters');
            }
            
            if (!/[A-Z]/.test(password)) {
                isValid = false;
                errorMessages.push('Password must contain an uppercase letter');
            }
            
            if (!/[0-9]/.test(password)) {
                isValid = false;
                errorMessages.push('Password must contain a number');
            }
            
            if (!/[^A-Za-z0-9]/.test(password)) {
                isValid = false;
                errorMessages.push('Password must contain a special character');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following issues:\n' + errorMessages.join('\n'));
            }
        });
    }
});
</script>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 