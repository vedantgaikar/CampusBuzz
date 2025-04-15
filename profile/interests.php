<?php
require_once '../includes/config.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please log in to access your interests page.";
    $_SESSION['message_type'] = "danger";
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user interests
$stmt = $pdo->prepare("SELECT category_id FROM user_interests WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_interests = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get all categories
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete existing user interests
        $delete_stmt = $pdo->prepare("DELETE FROM user_interests WHERE user_id = ?");
        $delete_stmt->execute([$user_id]);
        
        // Insert new interests
        if (isset($_POST['categories']) && is_array($_POST['categories'])) {
            $insert_stmt = $pdo->prepare("INSERT INTO user_interests (user_id, category_id) VALUES (?, ?)");
            
            foreach ($_POST['categories'] as $category_id) {
                $insert_stmt->execute([$user_id, $category_id]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Your interests have been updated successfully!";
        
        // Refresh user interests
        $stmt = $pdo->prepare("SELECT category_id FROM user_interests WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_interests = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error_message = "An error occurred: " . $e->getMessage();
    }
}

$page_title = "My Interests";
require_once '../includes/header.php';
?>

<div class="profile-layout">
    <?php require_once '../includes/profile_sidebar.php'; ?>
    
    <div class="profile-content">
        <div class="interests-container">
            <h1 class="interests-title">My Interests</h1>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <p class="interests-description">
                Select your interests to personalize your CampusBuzz experience. We'll use these to recommend events and activities that match your preferences.
            </p>
            
            <form action="interests.php" method="post">
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-item <?php echo in_array($category['id'], $user_interests) ? 'selected' : ''; ?>">
                            <input type="checkbox" name="categories[]" id="category-<?php echo $category['id']; ?>" 
                                   value="<?php echo $category['id']; ?>" 
                                   <?php echo in_array($category['id'], $user_interests) ? 'checked' : ''; ?>>
                            <label for="category-<?php echo $category['id']; ?>" class="category-name">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="interests-actions">
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Interests</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Add click handler to make the entire category item act as a checkbox toggle
    document.querySelectorAll('.category-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Skip if the actual checkbox or label was clicked
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') {
                return;
            }
            
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            // Toggle selected class
            this.classList.toggle('selected', checkbox.checked);
        });
    });
    
    // Add event listener to checkboxes to toggle selected class
    document.querySelectorAll('.category-item input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            this.closest('.category-item').classList.toggle('selected', this.checked);
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?> 