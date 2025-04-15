<?php
// Use full path for includes
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

// Get upcoming events for display on homepage
$upcomingEvents = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query(
        "SELECT e.*, u.name as organizer_name 
         FROM events e 
         JOIN users u ON e.organizer_id = u.id
         WHERE e.event_date > NOW() 
         AND e.status = 'published' 
         ORDER BY e.event_date ASC 
         LIMIT 3"
    );
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching upcoming events: " . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h2>Welcome to <?= SITE_NAME ?></h2>
        <p>Your all-in-one platform for discovering, creating, and managing campus events. Never miss out on what's happening around your campus!</p>
        <a href="<?= BASE_URL ?>pages/signup.php" class="btn">Get Started</a>
    </div>
</section>

<section class="container">
    <h2 class="text-center" style="margin-bottom: 2rem; text-align: center;">What We Offer</h2>
    
    <div class="features">
        <div class="feature-card">
            <i class="fas fa-calendar-alt"></i>
            <h3>Event Discovery</h3>
            <p>Find and explore all the events happening around your campus in one place.</p>
        </div>
        
        <div class="feature-card">
            <i class="fas fa-bullhorn"></i>
            <h3>Event Creation</h3>
            <p>Create and promote your own events with our easy-to-use event creation tools.</p>
        </div>
        
        <div class="feature-card">
            <i class="fas fa-bell"></i>
            <h3>Event Reminders</h3>
            <p>Set reminders for events you're interested in and never miss out again.</p>
        </div>
    </div>
</section>

<section class="container" style="margin-top: 4rem;">
    <h2 class="text-center" style="margin-bottom: 2rem; text-align: center;">Upcoming Events</h2>
    
    <div class="events-list">
        <?php if (empty($upcomingEvents)): ?>
            <div class="no-events">
                <p>No upcoming events at this time. Check back soon or <a href="<?= BASE_URL ?>pages/login.php">log in</a> to create your own!</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingEvents as $event): ?>
                <div class="event-card">
                    <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : BASE_URL . 'assets/images/placeholders/event-placeholder.php' ?>" 
                         alt="<?= htmlspecialchars($event['name']) ?>"
                         onerror="this.src='<?= BASE_URL ?>assets/images/placeholders/event-placeholder.php'">
                    <div class="event-card-content">
                        <span class="event-date"><i class="far fa-calendar-alt"></i> <?= date('F j, Y', strtotime($event['event_date'])) ?></span>
                        <h3><?= htmlspecialchars($event['name']) ?></h3>
                        <p><?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...</p>
                        <a href="<?= BASE_URL ?>pages/event_details.php?id=<?= $event['id'] ?>" class="btn" style="margin-top: 1rem;">Learn More</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="text-center" style="margin-top: 2rem;">
        <a href="<?= BASE_URL ?>pages/events.php" class="btn btn-outline">View All Events</a>
    </div>
</section>

<style>
.no-events {
    background-color: #f8f9fa;
    padding: 2rem;
    border-radius: 8px;
    text-align: center;
    margin: 1rem 0;
}

.btn-outline {
    background-color: transparent;
    border: 2px solid var(--primary-color);
    color: var(--primary-color);
}

.btn-outline:hover {
    background-color: var(--primary-color);
    color: white;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?> 