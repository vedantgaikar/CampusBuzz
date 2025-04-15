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

// Get user info and their events
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$user = [];
$userEvents = [];
$registeredEvents = [];
$recommendedEvents = [];
$upcomingEvents = [];
$notifications = [];

try {
    $db = Database::getInstance();
    
    // Get user details with profile
    $userStmt = $db->query(
        "SELECT u.id, u.name, u.email, u.user_type, u.last_login, 
        p.phone, p.institution, p.profile_picture
        FROM users u
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE u.id = ?", 
        [$user_id]
    );
    $user = $userStmt->fetch();
    
    // Update session with current user information if needed
    if ($user && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== $user['user_type'])) {
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_name'] = $user['name'];
        $user_type = $user['user_type'];
    }
    
    // For organizers - get their events
    if ($user_type == 'organizer') {
        $eventsStmt = $db->query(
            "SELECT * FROM events WHERE organizer_id = ? ORDER BY event_date DESC", 
            [$user_id]
        );
        $userEvents = $eventsStmt->fetchAll();
    }
    
    // Get events user has registered for
    $registrationsStmt = $db->query(
        "SELECT e.*, er.registration_date, er.status 
         FROM events e
         JOIN event_registrations er ON e.id = er.event_id
         WHERE er.user_id = ? AND e.event_date >= NOW()
         ORDER BY e.event_date ASC", 
        [$user_id]
    );
    $registeredEvents = $registrationsStmt->fetchAll();
    
    // Get upcoming events (next 7 days)
    $upcomingStmt = $db->query(
        "SELECT e.*, u.name as organizer_name
         FROM events e
         JOIN users u ON e.organizer_id = u.id
         WHERE e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
         AND e.status = 'published'
         AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id = ?)
         ORDER BY e.event_date ASC
         LIMIT 6", 
        [$user_id]
    );
    $upcomingEvents = $upcomingStmt->fetchAll();
    
    // Get user interests to recommend events
    $interestsStmt = $db->query(
        "SELECT category_id FROM user_interests WHERE user_id = ?", 
        [$user_id]
    );
    $interests = $interestsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // If user has interests, recommend events based on them
    if (!empty($interests)) {
        $placeholders = implode(',', array_fill(0, count($interests), '?'));
        $recommendedStmt = $db->query(
            "SELECT DISTINCT e.*, u.name as organizer_name
             FROM events e
             JOIN users u ON e.organizer_id = u.id
             JOIN event_categories ec ON e.id = ec.event_id
             WHERE ec.category_id IN ($placeholders)
             AND e.event_date > NOW()
             AND e.status = 'published'
             AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id = ?)
             ORDER BY e.event_date ASC
             LIMIT 3",
            array_merge($interests, [$user_id])
        );
        $recommendedEvents = $recommendedStmt->fetchAll();
    } else {
        // If no interests, recommend featured events
        $recommendedStmt = $db->query(
            "SELECT e.*, u.name as organizer_name
             FROM events e
             JOIN users u ON e.organizer_id = u.id
             WHERE e.is_featured = 1
             AND e.event_date > NOW()
             AND e.status = 'published'
             AND e.id NOT IN (SELECT event_id FROM event_registrations WHERE user_id = ?)
             ORDER BY e.event_date ASC
             LIMIT 3",
            [$user_id]
        );
        $recommendedEvents = $recommendedStmt->fetchAll();
    }
    
    // Get user notifications
    $notificationsStmt = $db->query(
        "SELECT * FROM notifications 
         WHERE user_id = ? 
         ORDER BY created_at DESC LIMIT 5",
        [$user_id]
    );
    $notifications = $notificationsStmt->fetchAll();
    
    // Mark all notifications as read
    $db->query(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
        [$user_id]
    );
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Include header
$pageTitle = "My Dashboard";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap;">
        <div>
            <h1>My Dashboard</h1>
            <p>Welcome back, <?= htmlspecialchars($_SESSION['user_name'] ?? $user['name'] ?? 'User') ?>!</p>
        </div>
        
        <div class="user-quick-actions">
            <?php if ($user_type == 'organizer'): ?>
                <a href="<?= BASE_URL ?>pages/create_event.php" class="btn">
                    <i class="fas fa-plus"></i> Create New Event
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>pages/events.php" class="btn">
                    <i class="fas fa-search"></i> Browse Events
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>pages/profile.php" class="btn" style="background-color: #6c757d; margin-left: 0.5rem;">
                <i class="fas fa-user-edit"></i> Edit Profile
            </a>
            <a href="<?= BASE_URL ?>pages/interests.php" class="btn" style="background-color: #4a6fdc; margin-left: 0.5rem;">
                <i class="fas fa-tags"></i> Manage Interests
            </a>
        </div>
    </div>
    
    <!-- Notifications Section -->
    <?php if (!empty($notifications)): ?>
        <div class="dashboard-notifications" style="margin-bottom: 2rem; padding: 1rem; background-color: #f8f9fa; border-radius: 8px;">
            <h4><i class="fas fa-bell"></i> Notifications</h4>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($notifications as $notification): ?>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid #e9ecef;">
                        <p style="margin: 0;"><?= htmlspecialchars($notification['content']) ?></p>
                        <small style="color: #6c757d;"><?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-sections" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        <!-- Stats Section -->
        <div class="dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
            <div class="stat-card" style="background-color: white; border-radius: 8px; padding: 1.5rem; box-shadow: var(--box-shadow);">
                <h3><?= $user_type == 'organizer' ? 'My Events' : 'Registered Events' ?></h3>
                <p style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">
                    <?= $user_type == 'organizer' ? count($userEvents) : count($registeredEvents) ?>
                </p>
                <p><?= $user_type == 'organizer' ? 'Events you\'ve organized' : 'Events you\'ve registered for' ?></p>
            </div>
            
            <div class="stat-card" style="background-color: white; border-radius: 8px; padding: 1.5rem; box-shadow: var(--box-shadow);">
                <h3>Account Type</h3>
                <p style="font-size: 2rem; font-weight: bold; color: var(--primary-color);"><?= ucfirst($user_type ?? 'User') ?></p>
                <p>Your account status</p>
            </div>
            
            <div class="stat-card" style="background-color: white; border-radius: 8px; padding: 1.5rem; box-shadow: var(--box-shadow);">
                <h3>Institution</h3>
                <p style="font-size: 1.5rem; font-weight: bold; color: var(--primary-color);">
                    <?= !empty($user['institution']) ? htmlspecialchars($user['institution']) : 'Not specified' ?>
                </p>
                <p>Your school or institution</p>
            </div>
        </div>
        
        <!-- For students: Upcoming Registered Events -->
        <?php if ($user_type == 'student' && !empty($registeredEvents)): ?>
            <div class="dashboard-section">
                <h2>Your Upcoming Events</h2>
                <div class="events-list">
                    <?php foreach ($registeredEvents as $event): ?>
                        <div class="event-card">
                            <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : 'https://via.placeholder.com/350x180?text=Event+Image' ?>" 
                                 alt="<?= htmlspecialchars($event['name']) ?>">
                            <div class="event-card-content">
                                <span class="event-date">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('F j, Y g:i A', strtotime($event['event_date'])) ?>
                                </span>
                                <h3><?= htmlspecialchars($event['name']) ?></h3>
                                <p><?= nl2br(htmlspecialchars(substr($event['description'], 0, 100))) ?>...</p>
                                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                    <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn">View Details</a>
                                    <a href="<?= BASE_URL ?>pages/cancel_registration.php?id=<?= $event['id'] ?>" class="btn" style="background-color: #dc3545;">Cancel Registration</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- For students: Recommended Events based on interests -->
        <?php if ($user_type == 'student' && !empty($recommendedEvents)): ?>
            <div class="dashboard-section">
                <h2>Recommended For You</h2>
                <div class="events-list">
                    <?php foreach ($recommendedEvents as $event): ?>
                        <div class="event-card">
                            <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : 'https://via.placeholder.com/350x180?text=Event+Image' ?>" 
                                 alt="<?= htmlspecialchars($event['name']) ?>">
                            <div class="event-card-content">
                                <span class="event-date">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('F j, Y g:i A', strtotime($event['event_date'])) ?>
                                </span>
                                <span class="event-organizer" style="display: block; margin-bottom: 0.5rem; color: #6c757d;">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($event['organizer_name']) ?>
                                </span>
                                <h3><?= htmlspecialchars($event['name']) ?></h3>
                                <p><?= nl2br(htmlspecialchars(substr($event['description'], 0, 100))) ?>...</p>
                                <div style="margin-top: 1rem;">
                                    <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="<?= BASE_URL ?>pages/interests.php" class="btn">Update My Interests</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- For students: Upcoming Events (next 7 days) -->
        <?php if ($user_type == 'student' && !empty($upcomingEvents)): ?>
            <div class="dashboard-section">
                <h2>Events This Week</h2>
                <div class="events-list">
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="event-card">
                            <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : 'https://via.placeholder.com/350x180?text=Event+Image' ?>" 
                                 alt="<?= htmlspecialchars($event['name']) ?>">
                            <div class="event-card-content">
                                <span class="event-date">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('F j, Y g:i A', strtotime($event['event_date'])) ?>
                                </span>
                                <span class="event-organizer" style="display: block; margin-bottom: 0.5rem; color: #6c757d;">
                                    <i class="fas fa-user"></i> 
                                    <?= htmlspecialchars($event['organizer_name']) ?>
                                </span>
                                <h3><?= htmlspecialchars($event['name']) ?></h3>
                                <p><?= nl2br(htmlspecialchars(substr($event['description'], 0, 100))) ?>...</p>
                                <div style="margin-top: 1rem;">
                                    <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="<?= BASE_URL ?>pages/events.php" class="btn">See All Events</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- For organizers: My Events -->
        <?php if ($user_type == 'organizer'): ?>
            <div class="dashboard-section">
                <h2>My Events</h2>
                
                <?php if (empty($userEvents)): ?>
                    <div class="alert" style="background-color: #f8f9fa; padding: 2rem; text-align: center; border-radius: 8px;">
                        <p>You haven't created any events yet.</p>
                        <a href="<?= BASE_URL ?>pages/create_event.php" class="btn" style="margin-top: 1rem;">Create Your First Event</a>
                    </div>
                <?php else: ?>
                    <div class="events-list">
                        <?php foreach ($userEvents as $event): ?>
                            <div class="event-card">
                                <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : 'https://via.placeholder.com/350x180?text=Event+Image' ?>" 
                                     alt="<?= htmlspecialchars($event['name']) ?>">
                                <div class="event-card-content">
                                    <span class="event-date">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?= date('F j, Y g:i A', strtotime($event['event_date'])) ?>
                                    </span>
                                    <span class="event-status" style="display: inline-block; margin-left: 1rem; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; text-transform: uppercase; 
                                        <?php
                                            switch($event['status']) {
                                                case 'published':
                                                    echo 'background-color: #28a745; color: white;';
                                                    break;
                                                case 'draft':
                                                    echo 'background-color: #6c757d; color: white;';
                                                    break;
                                                case 'cancelled':
                                                    echo 'background-color: #dc3545; color: white;';
                                                    break;
                                                case 'completed':
                                                    echo 'background-color: #17a2b8; color: white;';
                                                    break;
                                            }
                                        ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                    <h3><?= htmlspecialchars($event['name']) ?></h3>
                                    <p><?= nl2br(htmlspecialchars(substr($event['description'], 0, 100))) ?>...</p>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn">View</a>
                                        <a href="<?= BASE_URL ?>pages/edit_event.php?id=<?= $event['id'] ?>" class="btn" style="background-color: #6c757d;">Edit</a>
                                        <a href="<?= BASE_URL ?>pages/manage_attendees.php?id=<?= $event['id'] ?>" class="btn" style="background-color: #17a2b8;">Attendees</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Organizer Actions Section - Only visible to organizers -->
        <?php if ($user_type === 'organizer'): ?>
        <div class="dashboard-section">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Organizer Actions</h2>
            </div>
            <div class="action-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <div class="action-card" style="background-color: white; border-radius: 8px; padding: 1.75rem; box-shadow: var(--box-shadow); display: flex; flex-direction: column;">
                    <div class="action-card-icon" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div class="action-card-content">
                        <h3 style="margin-bottom: 0.75rem; text-align: center;">Create Event</h3>
                        <p style="margin-bottom: 1.5rem; text-align: center;">Create a new event and invite students to participate.</p>
                        <a href="<?= BASE_URL ?>pages/create_event.php" class="btn" style="display: block; text-align: center;">Create New Event</a>
                    </div>
                </div>
                
                <div class="action-card" style="background-color: white; border-radius: 8px; padding: 1.75rem; box-shadow: var(--box-shadow); display: flex; flex-direction: column;">
                    <div class="action-card-icon" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-card-content">
                        <h3 style="margin-bottom: 0.75rem; text-align: center;">Event Analytics</h3>
                        <p style="margin-bottom: 1.5rem; text-align: center;">View statistics and attendee information for your events.</p>
                        <a href="<?= BASE_URL ?>pages/event_analytics.php" class="btn" style="display: block; text-align: center;">View Analytics</a>
                    </div>
                </div>
                
                <div class="action-card" style="background-color: white; border-radius: 8px; padding: 1.75rem; box-shadow: var(--box-shadow); display: flex; flex-direction: column;">
                    <div class="action-card-icon" style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-card-content">
                        <h3 style="margin-bottom: 0.75rem; text-align: center;">Manage Attendees</h3>
                        <p style="margin-bottom: 1.5rem; text-align: center;">View and manage attendees for all your events.</p>
                        <a href="<?= BASE_URL ?>pages/manage_attendees.php" class="btn" style="display: block; text-align: center;">Manage Attendees</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Your Created Events Section (for organizers) -->
        <?php if ($user_type === 'organizer'): ?>
        <div class="dashboard-section">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>My Events</h2>
                <a href="<?= BASE_URL ?>pages/create_event.php" class="btn">Create New Event</a>
            </div>
            
            <?php 
            try {
                $stmt = $db->query(
                    "SELECT e.* FROM events e WHERE e.organizer_id = ? ORDER BY e.event_date DESC LIMIT 5",
                    [$_SESSION['user_id']]
                );
                $createdEvents = $stmt->fetchAll();
            } catch (Exception $e) {
                error_log("Error fetching created events: " . $e->getMessage());
                $createdEvents = [];
            }
            ?>
            
            <?php if (!empty($createdEvents)): ?>
                <div class="event-cards" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <?php foreach ($createdEvents as $event): ?>
                        <div class="event-card" style="background-color: white; border-radius: 8px; box-shadow: var(--box-shadow); overflow: hidden; display: flex; flex-direction: column; height: 100%;">
                            <div class="event-image" style="height: 160px; overflow: hidden;">
                                <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : BASE_URL . 'assets/images/placeholders/event-placeholder.php' ?>" 
                                     alt="<?= htmlspecialchars($event['name']) ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;"
                                     onerror="this.src='<?= BASE_URL ?>assets/images/placeholders/event-placeholder.php'">
                            </div>
                            <div class="event-details" style="padding: 1.25rem; flex-grow: 1; display: flex; flex-direction: column;">
                                <span class="event-status" style="display: inline-block; margin-bottom: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; text-transform: uppercase; 
                                    <?php
                                        switch($event['status']) {
                                            case 'published':
                                                echo 'background-color: #28a745; color: white;';
                                                break;
                                            case 'draft':
                                                echo 'background-color: #6c757d; color: white;';
                                                break;
                                            case 'cancelled':
                                                echo 'background-color: #dc3545; color: white;';
                                                break;
                                            case 'completed':
                                                echo 'background-color: #17a2b8; color: white;';
                                                break;
                                        }
                                    ?>">
                                    <?= ucfirst($event['status']) ?>
                                </span>
                                <h3 style="margin-bottom: 0.75rem;"><?= htmlspecialchars($event['name']) ?></h3>
                                <p class="event-date" style="margin-bottom: 0.5rem; color: #6c757d;">
                                    <i class="far fa-calendar-alt" style="width: 16px; margin-right: 0.5rem;"></i>
                                    <?= date('F j, Y - g:i A', strtotime($event['event_date'])) ?>
                                </p>
                                <p class="event-location" style="margin-bottom: 0.75rem; color: #6c757d;">
                                    <i class="fas fa-map-marker-alt" style="width: 16px; margin-right: 0.5rem;"></i>
                                    <?= htmlspecialchars($event['location']) ?>
                                </p>
                                <div class="event-actions" style="margin-top: auto; display: flex; gap: 0.5rem;">
                                    <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn" style="flex: 1; text-align: center;">View</a>
                                    <a href="<?= BASE_URL ?>pages/edit_event.php?id=<?= $event['id'] ?>" class="btn" style="flex: 1; text-align: center; background-color: #6c757d;">Edit</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-footer" style="text-align: center; margin-top: 1rem; margin-bottom: 2rem;">
                    <a href="<?= BASE_URL ?>pages/organizer_events.php" class="btn-link" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">View all events you've created <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background-color: #f8f9fa; padding: 3rem 2rem; border-radius: 8px; text-align: center; margin-bottom: 2rem;">
                    <div class="empty-state-icon" style="font-size: 3rem; color: #6c757d; margin-bottom: 1rem;">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3 style="margin-bottom: 0.75rem;">No Events Created Yet</h3>
                    <p style="margin-bottom: 1.5rem; max-width: 500px; margin-left: auto; margin-right: auto;">You haven't created any events yet. Get started by creating your first event!</p>
                    <a href="<?= BASE_URL ?>pages/create_event.php" class="btn">Create Your First Event</a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Update session last activity time
$_SESSION['last_activity'] = time();

include dirname(__FILE__) . '/../includes/footer.php'; 
?> 