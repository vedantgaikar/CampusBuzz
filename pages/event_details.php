<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Get event ID from URL
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

try {
    $db = Database::getInstance();
    
    // Get event details
    $stmt = $db->query(
        "SELECT e.*, u.name as organizer_name 
         FROM events e 
         JOIN users u ON e.organizer_id = u.id 
         WHERE e.id = ? AND e.status = 'published'",
        [$event_id]
    );
    
    $event = $stmt->fetch();
    
    if (!$event) {
        header("Location: " . BASE_URL . "pages/events.php");
        exit();
    }
    
    // Get event categories
    $categoriesStmt = $db->query(
        "SELECT c.name 
         FROM categories c
         JOIN event_categories ec ON c.id = ec.category_id
         WHERE ec.event_id = ?",
        [$event_id]
    );
    
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check if user is registered (if logged in)
    $isRegistered = false;
    if (isset($_SESSION['user_id'])) {
        $regStmt = $db->query(
            "SELECT id FROM event_registrations 
             WHERE event_id = ? AND user_id = ? AND status != 'cancelled'",
            [$event_id, $_SESSION['user_id']]
        );
        
        $isRegistered = $regStmt->rowCount() > 0;
    }
    
    // Process Google Form registration tracking
    if (isset($_GET['register']) && isset($_SESSION['user_id']) && !empty($event['registration_url'])) {
        try {
            // Check if already registered
            $checkStmt = $db->query(
                "SELECT id FROM event_registrations 
                 WHERE event_id = ? AND user_id = ?",
                [$event_id, $_SESSION['user_id']]
            );
            
            if ($checkStmt->rowCount() == 0) {
                // Create registration record for external registration
                $regStmt = $db->query(
                    "INSERT INTO event_registrations (event_id, user_id, registration_date, status) 
                     VALUES (?, ?, NOW(), 'external')",
                    [$event_id, $_SESSION['user_id']]
                );
                
                // Add success message if needed
                $registrationSuccess = true;
            }
            
            // Redirect to the actual Google Form
            header("Location: " . $event['registration_url']);
            exit();
        } catch (Exception $e) {
            error_log("Error tracking Google Form registration: " . $e->getMessage());
            // Will fall through and display the page normally
        }
    }
    
    // Process cancellation of Google Form registration
    if (isset($_GET['cancel_external']) && isset($_SESSION['user_id'])) {
        try {
            $cancelStmt = $db->query(
                "UPDATE event_registrations SET status = 'cancelled'
                 WHERE event_id = ? AND user_id = ? AND status = 'external'",
                [$event_id, $_SESSION['user_id']]
            );
            
            if ($cancelStmt->rowCount() > 0) {
                $cancellationSuccess = true;
            }
            
            // Refresh registration status
            $regStmt = $db->query(
                "SELECT id, status FROM event_registrations 
                 WHERE event_id = ? AND user_id = ? AND status != 'cancelled'",
                [$event_id, $_SESSION['user_id']]
            );
            
            $registration = $regStmt->fetch();
            $isRegistered = $regStmt->rowCount() > 0;
            $registrationType = $registration['status'] ?? '';
        } catch (Exception $e) {
            error_log("Error cancelling external registration: " . $e->getMessage());
        }
    }
    
    // Check registration type if registered
    $registrationType = '';
    if (isset($_SESSION['user_id']) && $isRegistered) {
        try {
            $typeStmt = $db->query(
                "SELECT status FROM event_registrations 
                 WHERE event_id = ? AND user_id = ? AND status != 'cancelled'",
                [$event_id, $_SESSION['user_id']]
            );
            
            $reg = $typeStmt->fetch();
            if ($reg) {
                $registrationType = $reg['status'];
            }
        } catch (Exception $e) {
            error_log("Error checking registration type: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log("Event details error: " . $e->getMessage());
    header("Location: " . BASE_URL . "pages/events.php");
    exit();
}

// Include header
$pageTitle = htmlspecialchars($event['name']);
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <p><?= htmlspecialchars($_SESSION['success']) ?></p>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <p><?= htmlspecialchars($_SESSION['error']) ?></p>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="event-details-page">
        <!-- Event Header -->
        <div class="event-header">
            <h1><?= htmlspecialchars($event['name']) ?></h1>
            <div class="event-meta">
                <span class="event-date">
                    <i class="far fa-calendar-alt"></i>
                    <?= date('F j, Y - g:i A', strtotime($event['event_date'])) ?>
                </span>
                <span class="event-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <?= htmlspecialchars($event['location']) ?>
                </span>
                <span class="event-organizer">
                    <i class="fas fa-user"></i>
                    Organized by: <?= htmlspecialchars($event['organizer_name']) ?>
                </span>
            </div>
            
            <?php if (!empty($categories)): ?>
            <div class="event-categories">
                <?php foreach ($categories as $category): ?>
                    <span class="event-category"><?= htmlspecialchars($category) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Event Content -->
        <div class="event-content">
            <div class="event-main">
                <!-- Event Image -->
                <div class="event-image-container">
                    <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : BASE_URL . 'assets/images/placeholders/event-placeholder.php' ?>" 
                         alt="<?= htmlspecialchars($event['name']) ?>"
                         class="event-detail-image"
                         onerror="this.src='<?= BASE_URL ?>assets/images/placeholders/event-placeholder.php'">
                </div>
                
                <!-- Event Description -->
                <div class="event-description">
                    <h2>About This Event</h2>
                    <div class="description-content">
                        <?= nl2br(htmlspecialchars($event['description'])) ?>
                    </div>
                </div>
                
                <!-- Event Venue Details (if available) -->
                <?php if (!empty($event['venue_details'])): ?>
                <div class="event-venue">
                    <h2>Venue Information</h2>
                    <div class="venue-content">
                        <?= nl2br(htmlspecialchars($event['venue_details'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Event Sidebar -->
            <div class="event-sidebar">
                <div class="action-card">
                    <h3>Event Registration</h3>
                    
                    <?php if (isset($registrationSuccess)): ?>
                        <div class="alert alert-success">
                            <p>You've been registered for this event! You will be redirected to the external registration form.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($cancellationSuccess)): ?>
                        <div class="alert alert-success">
                            <p>Your registration has been cancelled successfully.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['registration_url'])): ?>
                        <p>This event requires external registration. Click the button below to register.</p>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($isRegistered && $registrationType == 'external'): ?>
                                <div class="registration-status">
                                    <p><i class="fas fa-check-circle"></i> You're registered for this event!</p>
                                    <small>You've been registered through the external form.</small>
                                    
                                    <a href="?id=<?= $event_id ?>&cancel_external=1" class="btn btn-danger btn-block mt-3">
                                        Cancel Registration
                                    </a>
                                </div>
                            <?php else: ?>
                                <a href="?id=<?= $event_id ?>&register=1" class="btn btn-primary btn-block" target="_blank">
                                    Register via Google Form
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= $event['registration_url'] ?>" target="_blank" class="btn btn-primary btn-block">
                                Register via Google Form
                            </a>
                            <small>Note: Login to track your registration status.</small>
                        <?php endif; ?>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <?php if ($isRegistered): ?>
                            <p><i class="fas fa-check-circle"></i> You're registered for this event!</p>
                            <a href="<?= BASE_URL ?>pages/cancel_registration.php?event_id=<?= $event_id ?>" class="btn btn-danger btn-block">
                                Cancel Registration
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>pages/register.php?event_id=<?= $event_id ?>" class="btn btn-primary btn-block" target="_blank">
                                Register for Event
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Please log in to register for this event.</p>
                        <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-outline-primary btn-block">
                            Log In
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="share-card">
                    <h3>Share This Event</h3>
                    <div class="share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(BASE_URL . 'pages/event_details.php?id=' . $event_id) ?>" target="_blank" class="share-btn facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Check out this event: ' . $event['name']) ?>&url=<?= urlencode(BASE_URL . 'pages/event_details.php?id=' . $event_id) ?>" target="_blank" class="share-btn twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="mailto:?subject=<?= urlencode('Check out this event: ' . $event['name']) ?>&body=<?= urlencode('I thought you might be interested in this event: ' . $event['name'] . '\n\n' . BASE_URL . 'pages/event_details.php?id=' . $event_id) ?>" class="share-btn email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['organizer_id']): ?>
                <div class="organizer-card">
                    <h3>Organizer Actions</h3>
                    <a href="<?= BASE_URL ?>pages/edit_event.php?id=<?= $event_id ?>" class="btn btn-outline-primary btn-block">
                        Edit Event
                    </a>
                    <a href="<?= BASE_URL ?>pages/event_registrations.php?id=<?= $event_id ?>" class="btn btn-outline-primary btn-block">
                        View Registrations
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Event Comments Section -->
    <div class="event-comments-section">
        <h2>Comments</h2>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="comment-form-container">
                <form method="post" action="<?= BASE_URL ?>pages/add_comment.php" class="comment-form">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="parent_id" value="0">
                    
                    <div class="form-group">
                        <textarea name="comment" placeholder="Add a comment..." class="form-control" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-to-comment">
                <p>Please <a href="<?= BASE_URL ?>pages/login.php?redirect=event_details.php?id=<?= $event_id ?>">log in</a> to add a comment.</p>
            </div>
        <?php endif; ?>
        
        <?php
        // Get event comments
        $comments = [];
        try {
            // First get all parent comments
            $commentStmt = $db->query(
                "SELECT c.*, u.name as user_name 
                 FROM comments c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.event_id = ? AND c.parent_id IS NULL 
                 ORDER BY c.created_at DESC",
                [$event_id]
            );
            $parentComments = $commentStmt->fetchAll();
            
            // Then get all replies
            $replyStmt = $db->query(
                "SELECT c.*, u.name as user_name 
                 FROM comments c 
                 JOIN users u ON c.user_id = u.id 
                 WHERE c.event_id = ? AND c.parent_id IS NOT NULL 
                 ORDER BY c.created_at ASC",
                [$event_id]
            );
            $replies = $replyStmt->fetchAll();
            
            // Organize replies by parent comment
            $repliesByParent = [];
            foreach ($replies as $reply) {
                $parentId = $reply['parent_id'];
                if (!isset($repliesByParent[$parentId])) {
                    $repliesByParent[$parentId] = [];
                }
                $repliesByParent[$parentId][] = $reply;
            }
            
            // Add replies to their parent comments
            foreach ($parentComments as &$comment) {
                $comment['replies'] = isset($repliesByParent[$comment['id']]) ? $repliesByParent[$comment['id']] : [];
            }
            
            $comments = $parentComments;
        } catch (Exception $e) {
            error_log("Error fetching comments: " . $e->getMessage());
        }
        ?>
        
        <div class="comments-list">
            <?php if (empty($comments)): ?>
                <div class="no-comments">
                    <p>No comments yet. Be the first to share your thoughts!</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-header">
                            <div class="comment-author">
                                <i class="fas fa-user-circle"></i>
                                <span class="author-name"><?= htmlspecialchars($comment['user_name']) ?></span>
                            </div>
                            <div class="comment-date">
                                <i class="far fa-clock"></i>
                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="comment-content">
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="comment-actions">
                                <button class="btn-link reply-toggle" data-parent="<?= $comment['id'] ?>">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                            </div>
                            
                            <div class="reply-form-container" id="reply-form-<?= $comment['id'] ?>" style="display: none;">
                                <form method="post" action="<?= BASE_URL ?>pages/add_comment.php" class="reply-form">
                                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                    <input type="hidden" name="parent_id" value="<?= $comment['id'] ?>">
                                    
                                    <div class="form-group">
                                        <textarea name="comment" placeholder="Add a reply..." class="form-control" required></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary btn-sm">Post Reply</button>
                                        <button type="button" class="btn btn-secondary btn-sm cancel-reply" data-parent="<?= $comment['id'] ?>">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($comment['replies'])): ?>
                            <div class="comment-replies">
                                <?php foreach ($comment['replies'] as $reply): ?>
                                    <div class="comment reply">
                                        <div class="comment-header">
                                            <div class="comment-author">
                                                <i class="fas fa-user-circle"></i>
                                                <span class="author-name"><?= htmlspecialchars($reply['user_name']) ?></span>
                                            </div>
                                            <div class="comment-date">
                                                <i class="far fa-clock"></i>
                                                <?= date('M j, Y g:i A', strtotime($reply['created_at'])) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="comment-content">
                                            <?= nl2br(htmlspecialchars($reply['comment'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Ratings Section -->
    <div class="event-ratings-section">
        <h2>Event Ratings</h2>
        
        <?php
        // Check if user has already rated this event
        $userRating = null;
        if (isset($_SESSION['user_id'])) {
            try {
                $ratingStmt = $db->query(
                    "SELECT rating, review FROM event_ratings WHERE event_id = ? AND user_id = ?",
                    [$event_id, $_SESSION['user_id']]
                );
                $userRating = $ratingStmt->fetch();
            } catch (Exception $e) {
                error_log("Error checking user rating: " . $e->getMessage());
            }
        }
        
        // Get average rating
        $avgRating = null;
        $ratingCount = 0;
        try {
            $avgStmt = $db->query(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM event_ratings WHERE event_id = ?",
                [$event_id]
            );
            $ratingData = $avgStmt->fetch();
            if ($ratingData && $ratingData['count'] > 0) {
                $avgRating = round($ratingData['avg_rating'], 1);
                $ratingCount = $ratingData['count'];
            }
        } catch (Exception $e) {
            error_log("Error getting average rating: " . $e->getMessage());
        }
        
        // Get all ratings and reviews
        $ratings = [];
        try {
            $allRatingsStmt = $db->query(
                "SELECT r.*, u.name as user_name 
                 FROM event_ratings r
                 JOIN users u ON r.user_id = u.id
                 WHERE r.event_id = ?
                 ORDER BY r.created_at DESC 
                 LIMIT 10",
                [$event_id]
            );
            $ratings = $allRatingsStmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error getting ratings: " . $e->getMessage());
        }
        ?>
        
        <div class="rating-summary">
            <?php if ($avgRating): ?>
                <div class="avg-rating">
                    <div class="rating-number"><?= $avgRating ?></div>
                    <div class="stars">
                        <?php 
                        $fullStars = floor($avgRating);
                        $halfStar = $avgRating - $fullStars >= 0.5;
                        
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $fullStars) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i == $fullStars + 1 && $halfStar) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="far fa-star"></i>';
                            }
                        }
                        ?>
                    </div>
                    <div class="rating-count"><?= $ratingCount ?> <?= $ratingCount === 1 ? 'rating' : 'ratings' ?></div>
                </div>
            <?php else: ?>
                <div class="no-ratings">
                    <p>No ratings yet. Be the first to rate this event!</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="rating-form-container">
                <h3><?= $userRating ? 'Update Your Rating' : 'Rate This Event' ?></h3>
                
                <form method="post" action="<?= BASE_URL ?>pages/add_rating.php" class="rating-form">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="rating-stars">
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $userRating && $userRating['rating'] == $i ? 'checked' : '' ?> required>
                                <label for="rating-<?= $i ?>">
                                    <i class="fas fa-star"></i>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="review">Review (Optional)</label>
                        <textarea name="review" id="review" class="form-control" placeholder="Share your experience about this event..."><?= $userRating ? htmlspecialchars($userRating['review']) : '' ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary"><?= $userRating ? 'Update Rating' : 'Submit Rating' ?></button>
                </form>
            </div>
        <?php else: ?>
            <div class="login-to-rate">
                <p>Please <a href="<?= BASE_URL ?>pages/login.php?redirect=event_details.php?id=<?= $event_id ?>">log in</a> to rate this event.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($ratings)): ?>
            <div class="ratings-list">
                <h3>Reviews</h3>
                
                <?php foreach ($ratings as $rating): ?>
                    <div class="rating-item">
                        <div class="rating-header">
                            <div class="rating-author">
                                <i class="fas fa-user-circle"></i>
                                <span class="author-name"><?= htmlspecialchars($rating['user_name']) ?></span>
                            </div>
                            <div class="rating-stars-small">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $rating['rating']): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($rating['review'])): ?>
                            <div class="rating-review">
                                <?= nl2br(htmlspecialchars($rating['review'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="rating-date">
                            <i class="far fa-calendar-alt"></i>
                            <?= date('M j, Y', strtotime($rating['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Base Styles */
.event-details-page {
    max-width: 1200px;
    margin: 2rem auto;
}

.event-header {
    margin-bottom: 2rem;
}

.event-header h1 {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
    font-weight: 700;
}

.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-bottom: 1rem;
    color: #666;
}

.event-meta span {
    display: flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    background-color: #f8f9fa;
    border-radius: 4px;
}

.event-meta i {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.event-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
}

.event-category {
    background-color: #f0f0f0;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    color: #555;
    transition: all 0.2s ease;
}

.event-category:hover {
    background-color: var(--primary-light);
    color: var(--primary-color);
}

/* Content Layout */
.event-content {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
}

.event-main {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: box-shadow 0.3s ease;
}

.event-main:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.event-image-container {
    width: 100%;
    height: 400px;
    overflow: hidden;
    background-color: #f0f0f0;
    position: relative;
}

.event-detail-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.event-image-container:hover .event-detail-image {
    transform: scale(1.03);
}

.event-description,
.event-venue {
    padding: 2rem;
}

.event-description h2,
.event-venue h2 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-light);
    padding-bottom: 0.5rem;
}

.description-content,
.venue-content {
    line-height: 1.7;
    color: #333;
}

/* Sidebar Styles */
.event-sidebar > div {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: box-shadow 0.3s ease;
}

.event-sidebar > div:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.event-sidebar h3 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-light);
    padding-bottom: 0.5rem;
}

.btn-block {
    display: block;
    width: 100%;
    margin-top: 1rem;
    padding: 0.75rem;
    text-align: center;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.btn-primary:hover {
    background-color: #0062cc;
    transform: translateY(-2px);
}

.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
    transform: translateY(-2px);
}

.btn-outline-primary {
    background-color: transparent;
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline-primary:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

.share-buttons {
    display: flex;
    justify-content: space-around;
    margin-top: 1rem;
}

.share-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    color: white;
    text-decoration: none;
    transition: transform 0.2s ease;
}

.share-btn:hover {
    transform: scale(1.1);
}

.share-btn.facebook {
    background-color: #3b5998;
}

.share-btn.twitter {
    background-color: #1da1f2;
}

.share-btn.email {
    background-color: #777;
}

/* Alert Styles */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.registration-status {
    padding: 1rem;
    background-color: #e9f7ef;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.registration-status p {
    color: #27ae60;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.registration-status i {
    margin-right: 0.5rem;
}

.mt-3 {
    margin-top: 1rem;
}

/* Responsive Styles */
@media (max-width: 992px) {
    .event-content {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .event-header h1 {
        font-size: 2rem;
    }
    
    .event-meta {
        gap: 0.75rem;
    }
    
    .event-image-container {
        height: 250px;
    }
    
    .event-description,
    .event-venue {
        padding: 1.5rem;
    }
}

@media (max-width: 576px) {
    .event-header h1 {
        font-size: 1.75rem;
    }
    
    .event-meta span {
        width: 100%;
    }
}

.event-comments-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.event-comments-section h2 {
    margin-bottom: 1.5rem;
}

.comment-form-container {
    margin-bottom: 2rem;
}

.comment-form textarea, 
.reply-form textarea {
    width: 100%;
    min-height: 100px;
    padding: 0.75rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
    border-radius: 8px;
}

.login-to-comment {
    margin-bottom: 2rem;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.comments-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.comment {
    background-color: white;
    border-radius: 8px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.comment.reply {
    background-color: #f8f9fa;
    box-shadow: none;
    margin-top: 1rem;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}

.comment-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

.comment-date {
    color: #6c757d;
    font-size: 0.9rem;
}

.comment-content {
    margin-bottom: 1rem;
    line-height: 1.5;
}

.comment-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.btn-link {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    padding: 0;
    font-size: 0.9rem;
    text-decoration: none;
}

.btn-link:hover {
    text-decoration: underline;
}

.reply-form-container {
    margin: 1rem 0;
}

.reply-form {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
}

.form-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
}

.comment-replies {
    margin-top: 1rem;
    margin-left: 1.5rem;
    border-left: 2px solid var(--border-color);
    padding-left: 1.5rem;
}

.no-comments {
    padding: 2rem;
    text-align: center;
    background-color: #f8f9fa;
    border-radius: 8px;
    color: #6c757d;
}

.event-ratings-section {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-color);
}

.event-ratings-section h2 {
    margin-bottom: 1.5rem;
}

.rating-summary {
    display: flex;
    justify-content: center;
    margin-bottom: 2rem;
}

.avg-rating {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.rating-number {
    font-size: 3rem;
    font-weight: bold;
    color: var(--primary-color);
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stars {
    margin-bottom: 0.5rem;
    color: #ffc107;
    font-size: 1.5rem;
}

.rating-count {
    color: #6c757d;
    font-size: 0.9rem;
}

.no-ratings {
    text-align: center;
    color: #6c757d;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.rating-form-container {
    max-width: 600px;
    margin: 0 auto 2rem;
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.rating-form-container h3 {
    margin-bottom: 1rem;
    text-align: center;
}

.rating-stars {
    margin-bottom: 1.5rem;
}

.star-rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    font-size: 2rem;
}

.star-rating input {
    display: none;
}

.star-rating label {
    cursor: pointer;
    color: #ddd;
    padding: 0 0.15rem;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #ffc107;
}

.login-to-rate {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 8px;
}

.ratings-list {
    margin-top: 2rem;
}

.ratings-list h3 {
    margin-bottom: 1rem;
}

.rating-item {
    background-color: white;
    padding: 1.25rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.rating-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.rating-author {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rating-stars-small {
    color: #ffc107;
    font-size: 1rem;
}

.rating-review {
    margin-bottom: 1rem;
    line-height: 1.5;
}

.rating-date {
    color: #6c757d;
    font-size: 0.85rem;
    text-align: right;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reply form toggle
    const replyToggles = document.querySelectorAll('.reply-toggle');
    const cancelReplies = document.querySelectorAll('.cancel-reply');
    
    replyToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const parentId = this.dataset.parent;
            const replyForm = document.getElementById(`reply-form-${parentId}`);
            replyForm.style.display = 'block';
            replyForm.querySelector('textarea').focus();
        });
    });
    
    cancelReplies.forEach(cancel => {
        cancel.addEventListener('click', function() {
            const parentId = this.dataset.parent;
            const replyForm = document.getElementById(`reply-form-${parentId}`);
            replyForm.style.display = 'none';
            replyForm.querySelector('textarea').value = '';
        });
    });
});
</script>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?>