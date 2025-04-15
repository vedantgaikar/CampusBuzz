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

// Check if user is an organizer
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organizer') {
    $_SESSION['message'] = "Access denied. Only organizers can create events.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

$errors = [];
$success = false;

// Get all categories
$categories = [];
try {
    $db = Database::getInstance();
    $categoriesStmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $registration_url = trim($_POST['registration_url'] ?? '');
    $venue_details = trim($_POST['venue_details'] ?? '');
    $max_attendees = !empty($_POST['max_attendees']) ? intval($_POST['max_attendees']) : null;
    $event_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    // Basic validation
    if (empty($name)) {
        $errors[] = "Event name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Event description is required";
    }
    
    if (empty($event_date)) {
        $errors[] = "Event date is required";
    }
    
    if (empty($event_time)) {
        $errors[] = "Event time is required";
    }
    
    if (empty($location)) {
        $errors[] = "Event location is required";
    }
    
    // Validate registration URL if provided
    if (!empty($registration_url) && !filter_var($registration_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid URL for the registration form";
    }

    // Validate categories
    if (empty($event_categories)) {
        $errors[] = "Please select at least one category for your event";
    } else {
        // Verify all selected categories exist
        $validCategories = array_column($categories, 'id');
        foreach ($event_categories as $category_id) {
            if (!in_array($category_id, $validCategories)) {
                $errors[] = "Invalid category selected";
                break;
            }
        }
    }
    
    // If no validation errors, create event
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            
            // Combine date and time
            $datetime = date('Y-m-d H:i:s', strtotime("$event_date $event_time"));
            
            // Handle image upload
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                // Validate file type and size
                if (!in_array($_FILES['image']['type'], $allowed_types)) {
                    $errors[] = "Invalid file type. Please upload JPEG, PNG, or GIF files.";
                    $pdo->rollBack();
                } elseif ($_FILES['image']['size'] > $max_size) {
                    $errors[] = "File size exceeds the maximum limit of 5MB.";
                    $pdo->rollBack();
                } else {
                    // Create directory if it doesn't exist
                    $upload_dir = dirname(__FILE__) . '/../assets/images/events/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $filename = time() . '_' . basename($_FILES['image']['name']);
                    $upload_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_file)) {
                        $image_path = 'assets/images/events/' . $filename;
                    } else {
                        $errors[] = "Failed to upload image. Please try again.";
                        $pdo->rollBack();
                    }
                }
            }
            
            if (empty($errors)) {
                // Insert event
                $stmt = $db->query(
                    "INSERT INTO events (name, description, event_date, location, venue_details, max_attendees, image, organizer_id, created_at, registration_url) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
                    [
                        $name, 
                        $description, 
                        $datetime, 
                        $location, 
                        $venue_details, 
                        $max_attendees, 
                        $image_path, 
                        $_SESSION['user_id'], 
                        $registration_url
                    ]
                );
                
                if ($stmt) {
                    $event_id = $pdo->lastInsertId();
                    
                    // Insert event categories
                    foreach ($event_categories as $category_id) {
                        $db->query(
                            "INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)",
                            [$event_id, $category_id]
                        );
                    }
                    
                    $pdo->commit();
                    $success = true;
                    
                    // Redirect to events page on success
                    header("Location: " . BASE_URL . "pages/events.php?created=1");
                    exit();
                } else {
                    $pdo->rollBack();
                    $errors[] = "Failed to create event. Please try again.";
                }
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "An error occurred while creating the event. Please try again.";
            error_log("Create event error: " . $e->getMessage());
        }
    }
}

// Include header
$pageTitle = "Create Event";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header" style="margin-bottom: 2rem;">
        <h1>Create a New Event</h1>
        <p>Fill out the form below to create your event.</p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <p>Event created successfully!</p>
        </div>
    <?php endif; ?>
    
    <div class="form-container" style="max-width: 800px;">
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-section">
                <h3 class="section-title">Basic Information</h3>
                
                <div class="form-group">
                    <label for="name">Event Name *</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="event_date">Date *</label>
                        <input type="date" class="form-control" id="event_date" name="event_date" value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event_time">Time *</label>
                        <input type="time" class="form-control" id="event_time" name="event_time" value="<?= htmlspecialchars($_POST['event_time'] ?? '') ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">Event Categories</h3>
                <p>Select categories that best describe your event.</p>
                
                <div class="categories-grid">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-checkbox">
                            <input type="checkbox" name="categories[]" id="category-<?= $category['id'] ?>" value="<?= $category['id'] ?>"
                                <?= isset($_POST['categories']) && in_array($category['id'], $_POST['categories']) ? 'checked' : '' ?>>
                            <label for="category-<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">Location Details</h3>
                
                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" placeholder="Building, Room, or Address" required>
                </div>
                
                <div class="form-group">
                    <label for="venue_details">Venue Details</label>
                    <textarea class="form-control" id="venue_details" name="venue_details" rows="3" placeholder="Provide additional details about the venue, such as parking information, entrance instructions, etc."><?= htmlspecialchars($_POST['venue_details'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">Registration</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="registration_url">External Registration URL</label>
                        <input type="url" class="form-control" id="registration_url" name="registration_url" value="<?= htmlspecialchars($_POST['registration_url'] ?? '') ?>" placeholder="https://forms.google.com/...">
                        <small>Enter a Google Form URL or other form link where attendees can register</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_attendees">Maximum Attendees</label>
                        <input type="number" class="form-control" id="max_attendees" name="max_attendees" value="<?= htmlspecialchars($_POST['max_attendees'] ?? '') ?>" min="1" placeholder="Leave blank for unlimited">
                        <small>Set a cap on the number of registrations</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">Event Image</h3>
                
                <div class="form-group">
                    <label for="image">Upload Event Image</label>
                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                    <small>Recommended size: 800x400 pixels. Max file size: 5MB. Supported formats: JPEG, PNG, GIF.</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Event</button>
                <a href="<?= BASE_URL ?>pages/events.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.form-section {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-of-type {
    border-bottom: none;
}

.section-title {
    color: var(--primary-color);
    margin-bottom: 1rem;
    font-size: 1.25rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.category-checkbox {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 4px;
    background-color: #f8f9fa;
    transition: all 0.2s ease;
}

.category-checkbox:hover {
    background-color: #e9ecef;
}

.category-checkbox input[type="checkbox"] {
    margin-right: 0.5rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.btn-primary {
    background-color: var(--primary-color);
}

.btn-secondary {
    background-color: #6c757d;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .categories-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}
</style>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 