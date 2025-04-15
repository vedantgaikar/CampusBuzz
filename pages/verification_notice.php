<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
session_start();

// Check if there's a verification email sent message
if (!isset($_SESSION['verification_email_sent']) || !isset($_SESSION['verification_email'])) {
    header("Location: " . BASE_URL);
    exit();
}

$email = $_SESSION['verification_email'];

// Include header
$pageTitle = "Verify Your Email";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="verification-container" style="max-width: 600px; margin: 2rem auto; background-color: white; border-radius: 8px; box-shadow: var(--box-shadow); padding: 2rem; text-align: center;">
        <div class="icon" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">
            <i class="fas fa-envelope"></i>
        </div>
        
        <h2>Please Verify Your Email</h2>
        
        <div class="alert alert-success">
            <p>Thanks for signing up! A verification email has been sent to <strong><?= htmlspecialchars($email) ?></strong>.</p>
        </div>
        
        <p>Please check your inbox and click on the verification link to activate your account.</p>
        
        <div style="margin: 2rem 0; padding: 1rem; background-color: #f8f9fa; border-radius: 4px;">
            <h3>Didn't receive the email?</h3>
            <p>Check your spam folder or request a new verification link.</p>
            <a href="<?= BASE_URL ?>pages/resend_verification.php" class="btn" style="margin-top: 0.5rem;">Resend Verification Email</a>
        </div>
        
        <div>
            <p>Already verified your email?</p>
            <a href="<?= BASE_URL ?>pages/login.php" class="btn">Login to Your Account</a>
        </div>
    </div>
</div>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 