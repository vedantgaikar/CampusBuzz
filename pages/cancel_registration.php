<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = "Please log in to cancel your registration.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/login.php");
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($event_id <= 0) {
    $_SESSION['message'] = "Invalid event ID.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;
$event = null;

try {
    $db = Database::getInstance();
    
    // Get event details
    $eventStmt = $db->query(
        "SELECT name FROM events WHERE id = ?",
        [$event_id]
    );
    
    $event = $eventStmt->fetch();
    
    if (!$event) {
        $_SESSION['message'] = "Event not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: " . BASE_URL . "pages/dashboard.php");
        exit();
    }
    
    // Check if user is registered for this event
    $regStmt = $db->query(
        "SELECT id, status FROM event_registrations 
         WHERE event_id = ? AND user_id = ? AND status != 'cancelled'",
        [$event_id, $user_id]
    );
    
    $registration = $regStmt->fetch();
    
    if (!$registration) {
        $_SESSION['message'] = "You are not registered for this event.";
        $_SESSION['message_type'] = "warning";
        header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
        exit();
    }
    
    // Process cancellation if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cancelStmt = $db->query(
            "UPDATE event_registrations 
             SET status = 'cancelled' 
             WHERE event_id = ? AND user_id = ?",
            [$event_id, $user_id]
        );
        
        if ($cancelStmt) {
            $success = true;
            
            // Add notification for the event organizer
            $notifyStmt = $db->query(
                "INSERT INTO notifications (user_id, type, content, related_id, created_at)
                 SELECT organizer_id, 'registration_cancelled', ?, ?, NOW()
                 FROM events
                 WHERE id = ?",
                [$_SESSION['name'] . " has cancelled their registration for your event: " . $event['name'], $event_id, $event_id]
            );
            
            $_SESSION['message'] = "Your registration has been cancelled successfully.";
            $_SESSION['message_type'] = "success";
            
            // Redirect based on registration type
            if ($registration['status'] === 'external') {
                header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
            } else {
                header("Location: " . BASE_URL . "pages/dashboard.php");
            }
            exit();
        }
    }
    
} catch (Exception $e) {
    error_log("Cancel registration error: " . $e->getMessage());
    $_SESSION['message'] = "An error occurred while cancelling your registration.";
    $_SESSION['message_type'] = "danger";
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
}

// Include header
$pageTitle = "Cancel Registration";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="confirmation-card">
        <div class="confirmation-header">
            <h1>Cancel Registration</h1>
        </div>
        
        <div class="confirmation-body">
            <p>Are you sure you want to cancel your registration for the following event?</p>
            <div class="event-info">
                <h2><?= htmlspecialchars($event['name']) ?></h2>
            </div>
            
            <div class="alert alert-warning">
                <p><i class="fas fa-exclamation-triangle"></i> This action cannot be undone. You'll need to register again if you change your mind.</p>
            </div>
            
            <form method="post" action="">
                <div class="confirmation-actions">
                    <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event_id ?>" class="btn btn-secondary">No, Go Back</a>
                    <button type="submit" class="btn btn-danger">Yes, Cancel My Registration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.confirmation-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    max-width: 600px;
    margin: 3rem auto;
    overflow: hidden;
}

.confirmation-header {
    background-color: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

.confirmation-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--primary-color);
}

.confirmation-body {
    padding: 2rem;
}

.event-info {
    margin: 1.5rem 0;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid var(--primary-color);
}

.event-info h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #333;
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}

.alert i {
    margin-right: 0.5rem;
}

.confirmation-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 1.5rem;
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
    transition: all 0.2s ease-in-out;
    text-decoration: none;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border: none;
}

.btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
}

.btn-danger {
    color: #fff;
    background-color: #dc3545;
    border: none;
}

.btn-danger:hover {
    background-color: #c82333;
    transform: translateY(-2px);
}
</style>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 