<?php
// Use the correct path to the includes directory
require_once dirname(__FILE__) . '/../includes/config.php';
require_once dirname(__FILE__) . '/../includes/db_connect.php';
session_start();

// Parse query parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// Get all categories for filter
$categories = [];
try {
    $db = Database::getInstance();
    $categoriesStmt = $db->query("SELECT * FROM categories ORDER BY name");
    $categories = $categoriesStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

// Get events from database with filtering
$events = [];
try {
    $db = Database::getInstance();
    
    // Base query
    $query = "SELECT DISTINCT e.*, u.name as organizer_name 
              FROM events e 
              JOIN users u ON e.organizer_id = u.id";
    
    // Add category join if filtering by category
    if ($category_id > 0) {
        $query .= " JOIN event_categories ec ON e.id = ec.event_id";
    }
    
    // Add where clauses
    $whereClauses = ["e.status = 'published'"];
    $params = [];
    
    if (!empty($search)) {
        $whereClauses[] = "(e.name LIKE ? OR e.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category_id > 0) {
        $whereClauses[] = "ec.category_id = ?";
        $params[] = $category_id;
    }
    
    if (!empty($whereClauses)) {
        $query .= " WHERE " . implode(" AND ", $whereClauses);
    }
    
    // Add sorting
    switch ($sort) {
        case 'date_asc':
            $query .= " ORDER BY e.event_date ASC";
            break;
        case 'name_asc':
            $query .= " ORDER BY e.name ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY e.name DESC";
            break;
        case 'date_desc':
        default:
            $query .= " ORDER BY e.event_date DESC";
            break;
    }
    
    $stmt = $db->query($query, $params);
    $events = $stmt->fetchAll();
    
    // For each event, get its categories
    foreach ($events as &$event) {
        $catStmt = $db->query(
            "SELECT c.id, c.name 
             FROM categories c
             JOIN event_categories ec ON c.id = ec.category_id
             WHERE ec.event_id = ?
             ORDER BY c.name",
            [$event['id']]
        );
        $event['categories'] = $catStmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching events: " . $e->getMessage());
}

// Include header
$pageTitle = "Events";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Campus Events</h1>
        <p>Discover and join upcoming events happening around campus</p>
        
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'organizer'): ?>
            <a href="create_event.php" class="btn btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Create Event
            </a>
        <?php endif; ?>
    </div>
    
    <div class="events-filter">
        <form action="" method="get" class="filter-form">
            <div class="filter-row">
                <div class="filter-group search-group">
                    <input type="text" name="search" placeholder="Search events..." class="form-control" 
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                
                <div class="filter-group">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                    </select>
                </div>
            </div>
            
            <?php if (!empty($search) || $category_id > 0): ?>
                <div class="filter-tags">
                    <?php if (!empty($search)): ?>
                        <div class="filter-tag">
                            <span>"<?= htmlspecialchars($search) ?>"</span>
                            <a href="?<?= http_build_query(array_merge($_GET, ['search' => ''])) ?>" class="remove-filter">×</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($category_id > 0): 
                        $selectedCategory = '';
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $category_id) {
                                $selectedCategory = $cat['name'];
                                break;
                            }
                        }
                    ?>
                        <div class="filter-tag">
                            <span>Category: <?= htmlspecialchars($selectedCategory) ?></span>
                            <a href="?<?= http_build_query(array_merge($_GET, ['category' => 0])) ?>" class="remove-filter">×</a>
                        </div>
                    <?php endif; ?>
                    
                    <a href="events.php" class="clear-all">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (isset($_GET['created']) && $_GET['created'] == 1): ?>
        <div class="alert alert-success">
            <p>Your event has been created successfully!</p>
        </div>
    <?php endif; ?>
    
    <?php if (empty($events)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Events Found</h3>
            <p>There are no events matching your criteria. Try adjusting your filters or create your own event!</p>
            
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'organizer'): ?>
                <a href="create_event.php" class="btn btn-primary">Create Event</a>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn btn-primary">Login to Create Event</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="events-list">
            <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-image-container">
                        <img src="<?= !empty($event['image']) ? BASE_URL . $event['image'] : BASE_URL . 'assets/images/placeholders/event-placeholder.php' ?>" 
                             alt="<?= htmlspecialchars($event['name']) ?>"
                             class="event-image"
                             onerror="this.src='<?= BASE_URL ?>assets/images/placeholders/event-placeholder.php'">
                    </div>
                    <div class="event-card-content">
                        <div class="event-meta">
                            <span class="event-date">
                                <i class="far fa-calendar-alt"></i> 
                                <?= date('F j, Y', strtotime($event['event_date'])) ?>
                            </span>
                            <span class="event-time">
                                <i class="far fa-clock"></i> 
                                <?= date('g:i A', strtotime($event['event_date'])) ?>
                            </span>
                        </div>
                        
                        <h3 class="event-title"><?= htmlspecialchars($event['name']) ?></h3>
                        
                        <div class="event-categories">
                            <?php foreach ($event['categories'] as $cat): ?>
                                <a href="?category=<?= $cat['id'] ?>" class="event-category"><?= htmlspecialchars($cat['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                        
                        <p class="event-description"><?= nl2br(htmlspecialchars(substr($event['description'], 0, 150))) ?>...</p>
                        
                        <div class="event-footer">
                            <div class="event-organizer">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($event['organizer_name']) ?>
                            </div>
                            <a href="event_details.php?id=<?= $event['id'] ?>" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.page-header {
    margin-bottom: 2rem;
}

.events-filter {
    margin-bottom: 2rem;
    background-color: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--box-shadow);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: center;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.search-group {
    display: flex;
    gap: 0.5rem;
    flex: 2;
}

.search-group input {
    flex: 1;
}

.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    background-color: #e9ecef;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

.remove-filter {
    margin-left: 0.5rem;
    color: #6c757d;
    font-weight: bold;
    text-decoration: none;
}

.clear-all {
    color: var(--primary-color);
    font-size: 0.9rem;
    text-decoration: none;
    margin-left: auto;
}

.events-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
}

.event-card {
    display: flex;
    flex-direction: column;
    background-color: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: var(--box-shadow);
    transition: transform 0.3s ease;
    height: 100%;
}

.event-card:hover {
    transform: translateY(-5px);
}

.event-image-container {
    height: 180px;
    overflow: hidden;
}

.event-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.event-card-content {
    display: flex;
    flex-direction: column;
    padding: 1.5rem;
    flex: 1;
}

.event-meta {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
}

.event-date, .event-time {
    color: var(--primary-color);
    font-size: 0.9rem;
}

.event-title {
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
}

.event-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.event-category {
    background-color: #e9ecef;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    color: var(--text-color);
    text-decoration: none;
    transition: background-color 0.2s ease;
}

.event-category:hover {
    background-color: var(--primary-color);
    color: white;
}

.event-description {
    color: #6c757d;
    margin-bottom: 1.5rem;
    flex: 1;
}

.event-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}

.event-organizer {
    color: #6c757d;
    font-size: 0.9rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--box-shadow);
}

.empty-state i {
    font-size: 3rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin-bottom: 1rem;
}

.empty-state p {
    max-width: 500px;
    margin: 0 auto 2rem;
    color: #6c757d;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .events-list {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include dirname(__FILE__) . '/../includes/footer.php'; ?> 