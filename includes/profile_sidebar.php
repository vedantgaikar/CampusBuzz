<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="profile-sidebar">
    <h2>My Account</h2>
    <nav class="profile-nav">
        <ul>
            <li class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <a href="profile.php"><i class="fas fa-user"></i> Profile Details</a>
            </li>
            <li class="<?php echo $current_page === 'interests.php' ? 'active' : ''; ?>">
                <a href="interests.php"><i class="fas fa-tags"></i> My Interests</a>
            </li>
            <li class="<?php echo $current_page === 'events.php' ? 'active' : ''; ?>">
                <a href="events.php"><i class="fas fa-calendar-alt"></i> My Events</a>
            </li>
            <li class="<?php echo $current_page === 'password.php' ? 'active' : ''; ?>">
                <a href="password.php"><i class="fas fa-lock"></i> Change Password</a>
            </li>
            <?php if ($_SESSION['user_type'] === 'organizer'): ?>
            <li class="<?php echo $current_page === 'manage_events.php' ? 'active' : ''; ?>">
                <a href="manage_events.php"><i class="fas fa-tasks"></i> Manage Events</a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <div class="profile-sidebar-footer">
        <a href="../logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<style>
.profile-sidebar {
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    height: fit-content;
    min-width: 260px;
}

.sidebar-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.sidebar-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary-color);
    margin: 0;
}

.sidebar-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #555;
    text-decoration: none;
    border-radius: 6px;
    transition: background-color 0.2s, color 0.2s;
}

.sidebar-link:hover {
    background-color: #f5f5f5;
    color: var(--primary-color);
}

.sidebar-link.active {
    background-color: var(--primary-light);
    color: var(--primary-color);
    font-weight: 500;
}

.sidebar-link i {
    width: 20px;
    margin-right: 10px;
    text-align: center;
}

.sidebar-divider {
    height: 1px;
    background-color: #eee;
    margin: 0.75rem 0;
}

@media (max-width: 768px) {
    .profile-sidebar {
        margin-bottom: 1.5rem;
        min-width: 100%;
    }
}
</style> 