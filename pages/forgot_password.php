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
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    
    if (!$email) {
        $errors[] = "Valid email address is required";
    } else {
        try {
            $db = Database::getInstance();
            
            // Check if the email exists
            $stmt = $db->query(
                "SELECT id, name, is_verified FROM users WHERE email = ?",
                [$email]
            );
            
            $user = $stmt->fetch();
            
            if ($user) {
                // Check if account is verified
                if ($user['is_verified'] == 0) {
                    $errors[] = "This account has not been verified. Please verify your email first.";
                } else {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Save the reset token in the database
                    $insertStmt = $db->query(
                        "INSERT INTO password_resets (email, token, expiry_date, created_at) VALUES (?, ?, ?, NOW())",
                        [$email, $token, $expiry]
                    );
                    
                    if ($insertStmt) {
                        // Send reset email (mock implementation)
                        $resetUrl = BASE_URL . "pages/reset_password.php?token=" . $token;
                        
                        // For demonstration, we'll just set success flag
                        // In a real app, you would use mail() or a library like PHPMailer
                        $success = true;
                    } else {
                        $errors[] = "Failed to process your request. Please try again.";
                    }
                }
            } else {
                // Don't reveal if email exists or not (security)
                $success = true;
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again later.";
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// Include header
$pageTitle = "Forgot Password";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Reset Your Password</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>If the email address exists in our system, a password reset link has been sent. Please check your inbox and follow the instructions.</p>
                <p>The link will expire in 1 hour.</p>
                <p class="mt-3">
                    <a href="<?= BASE_URL ?>pages/login.php" class="btn">Back to Login</a>
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p>Enter your email address below and we'll send you a link to reset your password.</p>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Request Password Reset</button>
                </div>
                
                <p class="text-center">
                    Remember your password? <a href="<?= BASE_URL ?>pages/login.php">Login</a>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 