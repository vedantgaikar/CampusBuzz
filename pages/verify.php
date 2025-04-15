<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

$token = $_GET['token'] ?? '';
$verified = false;
$error = '';

// If there's a token, try to verify it
if (!empty($token)) {
    try {
        $db = Database::getInstance();
        
        // Find the user with this verification token
        $stmt = $db->query(
            "SELECT id, email, token_expiry FROM users WHERE verification_token = ? AND is_verified = 0",
            [$token]
        );
        
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if token has expired
            $now = new DateTime();
            $tokenExpiry = new DateTime($user['token_expiry']);
            
            if ($now > $tokenExpiry) {
                $error = "Verification link has expired. Please request a new one.";
            } else {
                // Update user as verified
                $updateStmt = $db->query(
                    "UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?",
                    [$user['id']]
                );
                
                if ($updateStmt) {
                    $verified = true;
                    
                    // Create a welcome notification
                    $db->query(
                        "INSERT INTO notifications (user_id, type, content, created_at) 
                        VALUES (?, 'welcome', 'Welcome to CampusBuzz! Your account has been verified successfully.', NOW())",
                        [$user['id']]
                    );
                } else {
                    $error = "Failed to verify your account. Please try again later.";
                }
            }
        } else {
            $error = "Invalid verification token or account already verified.";
        }
    } catch (Exception $e) {
        $error = "An error occurred during verification. Please try again later.";
        error_log("Verification error: " . $e->getMessage());
    }
} else {
    $error = "No verification token provided.";
}

// Include header
$pageTitle = "Email Verification";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="verification-result" style="max-width: 600px; margin: 2rem auto; background-color: white; border-radius: 8px; box-shadow: var(--box-shadow); padding: 2rem; text-align: center;">
        <?php if ($verified): ?>
            <div class="icon success" style="font-size: 4rem; color: #4CAF50; margin-bottom: 1rem;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Email Verified!</h2>
            <p>Your email has been successfully verified. Your account is now active.</p>
            <div style="margin-top: 2rem;">
                <a href="<?= BASE_URL ?>pages/login.php" class="btn">Login to Your Account</a>
            </div>
        <?php else: ?>
            <div class="icon error" style="font-size: 4rem; color: #f44336; margin-bottom: 1rem;">
                <i class="fas fa-times-circle"></i>
            </div>
            <h2>Verification Failed</h2>
            <div class="alert alert-danger">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
            <div style="margin-top: 2rem;">
                <p>If you're having trouble verifying your email, you can:</p>
                <a href="<?= BASE_URL ?>pages/resend_verification.php" class="btn">Request New Verification Email</a>
                <a href="<?= BASE_URL ?>pages/contact.php" class="btn" style="background-color: #6c757d; margin-top: 0.5rem;">Contact Support</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 