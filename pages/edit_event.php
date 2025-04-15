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
    $_SESSION['message'] = "Access denied. Only organizers can edit events.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

$errors = [];
$success = false;
$event = [];

try {
    $db = Database::getInstance();
    
    // Get event details and verify ownership
    $stmt = $db->query(
        "SELECT * FROM events WHERE id = ? AND organizer_id = ?",
        [$event_id, $_SESSION['user_id']]
    );
    
    $event = $stmt->fetch();
    
    if (!$event) {
        $_SESSION['message'] = "You are not authorized to edit this event or the event doesn't exist.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . BASE_URL . "pages/dashboard.php");
        exit();
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
        $status = $_POST['status'] ?? 'published';
        $venue_details = trim($_POST['venue_details'] ?? '');
        
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
        
        // Validate status
        $valid_statuses = ['draft', 'published', 'cancelled', 'completed'];
        if (!in_array($status, $valid_statuses)) {
            $errors[] = "Invalid event status";
        }
        
        // If no validation errors, update event
        if (empty($errors)) {
            try {
                // Handle image upload if a new image is provided
                $image_path = $event['image']; // Existing image path
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = dirname(__FILE__) . '/../assets/images/events/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $filename = time() . '_' . basename($_FILES['image']['name']);
                    $upload_file = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_file)) {
                        $image_path = 'assets/images/events/' . $filename;
                    }
                }
                
                // Combine date and time
                $datetime = date('Y-m-d H:i:s', strtotime("$event_date $event_time"));
                
                // Update event
                $updateStmt = $db->query(
                    "UPDATE events SET 
                     name = ?, 
                     description = ?, 
                     event_date = ?, 
                     location = ?, 
                     image = ?, 
                     registration_url = ?,
                     venue_details = ?,
                     status = ?,
                     updated_at = NOW()
                     WHERE id = ? AND organizer_id = ?",
                    [
                        $name, 
                        $description, 
                        $datetime, 
                        $location, 
                        $image_path, 
                        $registration_url,
                        $venue_details,
                        $status,
                        $event_id, 
                        $_SESSION['user_id']
                    ]
                );
                
                $success = true;
                
                // Refresh event data
                $stmt = $db->query(
                    "SELECT * FROM events WHERE id = ? AND organizer_id = ?",
                    [$event_id, $_SESSION['user_id']]
                );
                $event = $stmt->fetch();
                
            } catch (Exception $e) {
                $errors[] = "An error occurred while updating the event. Please try again.";
                error_log("Edit event error: " . $e->getMessage());
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Edit event page error: " . $e->getMessage());
    $_SESSION['message'] = "An error occurred. Please try again later.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

// Format event date and time for the form
$event_date = date('Y-m-d', strtotime($event['event_date']));
$event_time = date('H:i', strtotime($event['event_date']));

// Include header
$pageTitle = "Edit Event";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Edit Event</h1>
        <p>Update your event details below.</p>
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
            <p>Event updated successfully!</p>
        </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="post" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Event Name*</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($event['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description*</label>
                <textarea class="form-control" id="description" name="description" rows="5" required><?= htmlspecialchars($event['description']) ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="event_date">Date*</label>
                    <input type="date" class="form-control" id="event_date" name="event_date" value="<?= $event_date ?>" required>
                </div>
                
                <div class="form-group col-md-6">
                    <label for="event_time">Time*</label>
                    <input type="time" class="form-control" id="event_time" name="event_time" value="<?= $event_time ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="location">Location*</label>
                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($event['location']) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="venue_details">Venue Details</label>
                <textarea class="form-control" id="venue_details" name="venue_details" rows="3"><?= htmlspecialchars($event['venue_details'] ?? '') ?></textarea>
                <small class="form-text text-muted">Provide additional details about the venue, like directions, parking information, etc.</small>
            </div>
            
            <div class="form-group">
                <label for="registration_url">Registration Form URL</label>
                <input type="url" class="form-control" id="registration_url" name="registration_url" value="<?= htmlspecialchars($event['registration_url'] ?? '') ?>" placeholder="https://forms.google.com/...">
                <small class="form-text text-muted">Enter a Google Form URL or other form link where attendees can register.</small>
            </div>
            
            <div class="form-group">
                <label for="status">Event Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="published" <?= $event['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft" <?= $event['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="cancelled" <?= $event['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="completed" <?= $event['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Current Image</label>
                <?php if (!empty($event['image'])): ?>
                    <div class="current-image">
                        <img src="<?= BASE_URL . $event['image'] ?>" alt="<?= htmlspecialchars($event['name']) ?>" class="img-thumbnail" style="max-height: 200px;">
                    </div>
                <?php else: ?>
                    <p>No image uploaded.</p>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="image">Upload New Image</label>
                <input type="file" class="form-control-file" id="image" name="image" accept="image/*">
                <small class="form-text text-muted">Leave empty to keep the current image. Recommended size: 800x400 pixels.</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Event</button>
                <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event_id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
/* Form Styles */
.form-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 2rem;
    max-width: 800px;
    margin: 0 auto 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
    color: #333;
}

.form-control {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #495057;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    margin-right: -15px;
    margin-left: -15px;
}

.col-md-6 {
    position: relative;
    width: 100%;
    padding-right: 15px;
    padding-left: 15px;
}

@media (min-width: 768px) {
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

.form-text {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.current-image {
    margin: 1rem 0;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 4px;
    text-align: center;
}

.img-thumbnail {
    padding: 0.25rem;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    max-width: 100%;
    height: auto;
}

.form-actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

.btn {
    display: inline-block;
    font-weight: 500;
    text-align: center;
    vertical-align: middle;
    user-select: none;
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    line-height: 1.5;
    border-radius: 0.25rem;
    transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.btn-primary {
    color: #fff;
    background-color: var(--primary-color);
    border: none;
}

.btn-primary:hover {
    background-color: #0069d9;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border: none;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* Alert Styles */
.alert {
    position: relative;
    padding: 1rem;
    margin-bottom: 1rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

/* Page Header */
.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    color: var(--primary-color);
    font-weight: 700;
}
</style>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 