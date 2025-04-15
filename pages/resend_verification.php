<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

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
            
            // Check if the email exists and account is not verified
            $stmt = $db->query(
                "SELECT id, name, is_verified FROM users WHERE email = ?",
                [$email]
            );
            
            $user = $stmt->fetch();
            
            if (!$user) {
                $errors[] = "No account found with this email address";
            } elseif ($user['is_verified'] == 1) {
                $errors[] = "This account has already been verified. Please login.";
            } else {
                // Generate new verification token
                $verification_token = bin2hex(random_bytes(32));
                $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Update user with new token
                $updateStmt = $db->query(
                    "UPDATE users SET verification_token = ?, token_expiry = ? WHERE id = ?",
                    [$verification_token, $token_expiry, $user['id']]
                );
                
                if ($updateStmt) {
                    // Send verification email (mock implementation)
                    $verification_url = BASE_URL . "pages/verify.php?token=" . $verification_token;
                    
                    // For demonstration, we'll just set a message
                    // In a real app, you would use mail() or a library like PHPMailer
                    $_SESSION['verification_email_sent'] = true;
                    $_SESSION['verification_email'] = $email;
                    
                    $success = true;
                } else {
                    $errors[] = "Failed to process your request. Please try again.";
                }
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again later.";
            error_log("Resend verification error: " . $e->getMessage());
        }
    }
}

// Include header
$pageTitle = "Resend Verification Email";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="form-container">
        <h2 class="form-title">Resend Verification Email</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>A new verification email has been sent to <strong><?= htmlspecialchars($email) ?></strong>. Please check your inbox and follow the verification link.</p>
                <p class="mt-3">
                    <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-sm">Back to Login</a>
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
            
            <p>Enter your email address below and we'll send you a new verification link.</p>
            
            <form method="post" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn" style="width: 100%;">Send Verification Email</button>
                </div>
                
                <p class="text-center">
                    <a href="<?= BASE_URL ?>pages/login.php">Back to Login</a>
                </p>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 