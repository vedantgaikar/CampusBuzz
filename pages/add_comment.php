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
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$user_id = $_SESSION['user_id'];

// Validate data
if ($event_id <= 0) {
    $_SESSION['error'] = "Invalid event ID";
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

if (empty($comment)) {
    $_SESSION['error'] = "Comment cannot be empty";
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
}

// Check if event exists
try {
    $db = Database::getInstance();
    $eventStmt = $db->query("SELECT id FROM events WHERE id = ?", [$event_id]);
    if ($eventStmt->rowCount() === 0) {
        $_SESSION['error'] = "Event not found";
        header("Location: " . BASE_URL . "pages/events.php");
        exit();
    }
    
    // If this is a reply, check if parent comment exists
    if ($parent_id > 0) {
        $parentStmt = $db->query(
            "SELECT id FROM comments WHERE id = ? AND event_id = ?", 
            [$parent_id, $event_id]
        );
        if ($parentStmt->rowCount() === 0) {
            $_SESSION['error'] = "Parent comment not found";
            header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
            exit();
        }
    }
    
    // Insert the comment
    $parentIdParam = $parent_id > 0 ? $parent_id : null;
    $insertStmt = $db->query(
        "INSERT INTO comments (event_id, user_id, comment, parent_id, created_at) 
         VALUES (?, ?, ?, ?, NOW())",
        [$event_id, $user_id, $comment, $parentIdParam]
    );
    
    if ($insertStmt) {
        // Add notification for event organizer if this is a new comment (not a reply)
        if ($parent_id === 0) {
            // Get event organizer
            $organizerStmt = $db->query(
                "SELECT organizer_id, name FROM events WHERE id = ?", 
                [$event_id]
            );
            $event = $organizerStmt->fetch();
            
            // Only create notification if organizer is not the commenter
            if ($event && $event['organizer_id'] != $user_id) {
                $notificationContent = "New comment on your event \"" . $event['name'] . "\"";
                $db->query(
                    "INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                     VALUES (?, 'comment', ?, ?, NOW())",
                    [$event['organizer_id'], $notificationContent, $event_id]
                );
            }
        } else {
            // This is a reply, notify the parent comment author
            $parentAuthorStmt = $db->query(
                "SELECT c.user_id, u.name as user_name, e.name as event_name 
                 FROM comments c
                 JOIN users u ON c.user_id = u.id
                 JOIN events e ON c.event_id = e.id
                 WHERE c.id = ?", 
                [$parent_id]
            );
            $parentAuthor = $parentAuthorStmt->fetch();
            
            // Only create notification if parent comment author is not the replier
            if ($parentAuthor && $parentAuthor['user_id'] != $user_id) {
                $notificationContent = "New reply to your comment on \"" . $parentAuthor['event_name'] . "\"";
                $db->query(
                    "INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                     VALUES (?, 'reply', ?, ?, NOW())",
                    [$parentAuthor['user_id'], $notificationContent, $event_id]
                );
            }
        }
        
        $_SESSION['success'] = "Comment added successfully";
    } else {
        $_SESSION['error'] = "Failed to add comment";
    }
    
    // Redirect back to event page
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
} catch (Exception $e) {
    error_log("Add comment error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while adding your comment";
    header("Location: " . BASE_URL . "pages/event_details.php?id=" . $event_id);
    exit();
}
?> 