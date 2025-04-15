<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "pages/login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

// Get form data
$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review = isset($_POST['review']) ? trim($_POST['review']) : '';
$user_id = $_SESSION['user_id'];

// Validate data
if ($event_id <= 0) {
    $_SESSION['error'] = "Invalid event ID";
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

if ($rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Rating must be between 1 and 5";
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
}

try {
    $db = Database::getInstance();
    
    // Check if event exists and is published
    $eventStmt = $db->query(
        "SELECT id, name, organizer_id FROM events WHERE id = ? AND status = 'published'", 
        [$event_id]
    );
    
    if ($eventStmt->rowCount() === 0) {
        $_SESSION['error'] = "Event not found or not published";
        header("Location: " . BASE_URL . "pages/events.php");
        exit();
    }
    
    $event = $eventStmt->fetch();
    
    // Check if user has already rated this event
    $existingRatingStmt = $db->query(
        "SELECT id FROM event_ratings WHERE event_id = ? AND user_id = ?", 
        [$event_id, $user_id]
    );
    $existingRating = $existingRatingStmt->fetch();
    
    if ($existingRating) {
        // Update existing rating
        $updateStmt = $db->query(
            "UPDATE event_ratings SET rating = ?, review = ?, updated_at = NOW() WHERE id = ?",
            [$rating, $review, $existingRating['id']]
        );
        
        if ($updateStmt) {
            $_SESSION['success'] = "Your rating has been updated";
        } else {
            $_SESSION['error'] = "Failed to update your rating";
        }
    } else {
        // Insert new rating
        $insertStmt = $db->query(
            "INSERT INTO event_ratings (event_id, user_id, rating, review, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$event_id, $user_id, $rating, $review]
        );
        
        if ($insertStmt) {
            $_SESSION['success'] = "Thank you for rating this event";
            
            // Notify the event organizer
            if ($event['organizer_id'] != $user_id) {
                $notificationContent = "New rating for your event \"" . $event['name'] . "\" (" . $rating . " stars)";
                $db->query(
                    "INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                     VALUES (?, 'rating', ?, ?, NOW())",
                    [$event['organizer_id'], $notificationContent, $event_id]
                );
            }
        } else {
            $_SESSION['error'] = "Failed to submit your rating";
        }
    }
    
    // Redirect back to event page
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
} catch (Exception $e) {
    error_log("Rating error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again.";
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
}
?> 