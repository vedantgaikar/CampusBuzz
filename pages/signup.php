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

$errors = [];
$success = false;

// Process signup form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = trim($_POST['name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'student';
    
    // Additional profile fields
    $phone = trim($_POST['phone'] ?? '');
    $institution = trim($_POST['institution'] ?? '');
    
    // Basic validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (!$email) {
        $errors[] = "Valid email address is required";
    }
    
    // Password strength validation
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
    
    // Validate user type
    if (!in_array($user_type, ['student', 'organizer'])) {
        $errors[] = "Invalid user type selected";
    }
    
    // If no validation errors, create user
    if (empty($errors)) {
        require_once dirname(__FILE__) . '/../includes/db_connect.php';
        $db = Database::getInstance();
        
        try {
            // Check if email already exists
            $checkStmt = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
            if ($checkStmt->fetch()) {
                $errors[] = "Email address is already registered";
            } else {
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Hash the password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Start transaction
                $pdo = $db->getConnection();
                $pdo->beginTransaction();
                
                // Insert new user
                $insertStmt = $db->query(
                    "INSERT INTO users (name, email, password, user_type, verification_token, token_expiry, is_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
                    [$name, $email, $hashedPassword, $user_type, $verification_token, $token_expiry]
                );
                
                if ($insertStmt) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Insert user profile
                    $profileStmt = $db->query(
                        "INSERT INTO user_profiles (user_id, phone, institution, created_at) 
                        VALUES (?, ?, ?, NOW())",
                        [$user_id, $phone, $institution]
                    );
                    
                    if ($profileStmt) {
                        $pdo->commit();
                        
                        // Send verification email (mock implementation)
                        $verification_url = BASE_URL . "pages/verify.php?token=" . $verification_token;
                        
                        // For demonstration, we'll just set a message
                        // In a real app, you would use mail() or a library like PHPMailer
                        $_SESSION['verification_email_sent'] = true;
                        $_SESSION['verification_email'] = $email;
                        
                        // Redirect to verification notice page
                        header("Location: " . BASE_URL . "pages/verification_notice.php");
                        exit();
                    } else {
                        $pdo->rollBack();
                        $errors[] = "Failed to create user profile. Please try again.";
                    }
                } else {
                    $pdo->rollBack();
                    $errors[] = "Failed to create account. Please try again.";
                }
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "An error occurred during registration. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

// Include header
$pageTitle = "Sign Up";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Create an Account</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" id="signupForm">
            <div class="form-group">
                <label for="name">Full Name*</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address*</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="user_type">Account Type*</label>
                <select class="form-control" id="user_type" name="user_type" required>
                    <option value="student" <?= (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : '' ?>>Student</option>
                    <option value="organizer" <?= (isset($_POST['user_type']) && $_POST['user_type'] === 'organizer') ? 'selected' : '' ?>>Event Organizer</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="institution">School/Institution</label>
                <input type="text" class="form-control" id="institution" name="institution" value="<?= htmlspecialchars($_POST['institution'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password*</label>
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
                <label for="confirm_password">Confirm Password*</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="terms" id="terms" required> I agree to the <a href="<?= BASE_URL ?>pages/terms.php" target="_blank">Terms of Service</a> and <a href="<?= BASE_URL ?>pages/privacy.php" target="_blank">Privacy Policy</a>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">Sign Up</button>
            </div>
            
            <p class="text-center">
                Already have an account? <a href="<?= BASE_URL ?>pages/login.php">Login</a>
            </p>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    document.getElementById('signupForm').addEventListener('submit', function(e) {
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
});
</script>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 