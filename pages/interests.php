<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$categories = [];
$userInterests = [];
$errors = [];
$success = false;

try {
    $db = Database::getInstance();
    
    // Get all categories
    $categoriesStmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
    
    // Get user's current interests
    $interestsStmt = $db->query(
        "SELECT category_id FROM user_interests WHERE user_id = ?", 
        [$user_id]
    );
    $userInterests = $interestsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get selected interests from form
        $selectedInterests = isset($_POST['interests']) ? $_POST['interests'] : [];
        
        // Validate that interests are valid category IDs
        $validCategories = array_column($categories, 'id');
        foreach ($selectedInterests as $interest) {
            if (!in_array($interest, $validCategories)) {
                $errors[] = "Invalid category selected";
                break;
            }
        }
        
        if (empty($errors)) {
            // Start transaction
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            
            try {
                // Delete current interests
                $deleteStmt = $db->query(
                    "DELETE FROM user_interests WHERE user_id = ?", 
                    [$user_id]
                );
                
                // Insert new interests
                if (!empty($selectedInterests)) {
                    $values = [];
                    $params = [];
                    
                    foreach ($selectedInterests as $category_id) {
                        $values[] = "(?, ?)";
                        array_push($params, $user_id, $category_id);
                    }
                    
                    $sql = "INSERT INTO user_interests (user_id, category_id) VALUES " . implode(', ', $values);
                    $insertStmt = $db->query($sql, $params);
                }
                
                // Commit transaction
                $pdo->commit();
                
                // Update user interests variable for display
                $userInterests = $selectedInterests;
                
                $success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to update interests. Please try again.";
                error_log("Interests update error: " . $e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    $errors[] = "An error occurred. Please try again later.";
    error_log("Interests page error: " . $e->getMessage());
}

// Include header
$pageTitle = "My Interests";
include dirname(__FILE__) . '/../includes/header.php';

// Function to get appropriate icon for each category
function getCategoryIcon($categoryName) {
    $categoryName = strtolower($categoryName);
    
    if (strpos($categoryName, 'academic') !== false) return 'fas fa-graduation-cap';
    if (strpos($categoryName, 'sport') !== false) return 'fas fa-basketball-ball';
    if (strpos($categoryName, 'cultural') !== false) return 'fas fa-theater-masks';
    if (strpos($categoryName, 'workshop') !== false) return 'fas fa-tools';
    if (strpos($categoryName, 'conference') !== false) return 'fas fa-microphone';
    if (strpos($categoryName, 'networking') !== false) return 'fas fa-handshake';
    if (strpos($categoryName, 'entertainment') !== false) return 'fas fa-film';
    if (strpos($categoryName, 'charity') !== false) return 'fas fa-hand-holding-heart';
    if (strpos($categoryName, 'tech') !== false) return 'fas fa-laptop-code';
    if (strpos($categoryName, 'business') !== false) return 'fas fa-briefcase';
    if (strpos($categoryName, 'health') !== false) return 'fas fa-heartbeat';
    if (strpos($categoryName, 'art') !== false) return 'fas fa-paint-brush';
    if (strpos($categoryName, 'music') !== false) return 'fas fa-music';
    if (strpos($categoryName, 'food') !== false) return 'fas fa-utensils';
    
    // Default icon
    return 'fas fa-star';
}
?>

<div class="container">
    <div class="page-header">
        <h1>My Interests</h1>
        <p>Select categories that interest you to get personalized event recommendations.</p>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <p>Your interests have been updated successfully!</p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="form-container interests-container">
        <form method="post" action="">
            <?php if (count($categories) > 8): ?>
            <div class="category-filter">
                <input type="text" id="categorySearch" class="form-control" placeholder="Search categories...">
            </div>
            <?php endif; ?>
            
            <div class="interests-grid">
                <?php foreach ($categories as $category): ?>
                    <div class="interest-item <?= in_array($category['id'], $userInterests) ? 'selected' : '' ?>" data-category="<?= htmlspecialchars($category['name']) ?>">
                        <div class="interest-icon">
                            <i class="<?= getCategoryIcon($category['name']) ?>"></i>
                        </div>
                        <div class="interest-content">
                            <label class="interest-label">
                                <input type="checkbox" name="interests[]" value="<?= $category['id'] ?>" 
                                    <?= in_array($category['id'], $userInterests) ? 'checked' : '' ?>>
                                <span class="interest-name"><?= htmlspecialchars($category['name']) ?></span>
                            </label>
                            <?php if (!empty($category['description'])): ?>
                                <p class="interest-description"><?= htmlspecialchars($category['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Interests</button>
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make the entire interest item clickable
    const interestItems = document.querySelectorAll('.interest-item');
    interestItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if the click was directly on the checkbox
            if (e.target.tagName !== 'INPUT') {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                
                // Toggle selected class
                this.classList.toggle('selected', checkbox.checked);
            }
        });
    });
    
    // Search/filter functionality
    const searchInput = document.getElementById('categorySearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            
            interestItems.forEach(item => {
                const categoryName = item.getAttribute('data-category').toLowerCase();
                
                if (categoryName.includes(searchValue)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 