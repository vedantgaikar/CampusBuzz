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

$user_id = $_SESSION['user_id'];
$user = [];
$errors = [];
$success = false;

try {
    $db = Database::getInstance();
    
    // Get user details with profile
    $userStmt = $db->query(
        "SELECT u.id, u.name, u.email, u.user_type, 
         p.phone, p.bio, p.institution, p.department, p.profile_picture
         FROM users u
         LEFT JOIN user_profiles p ON u.id = p.user_id
         WHERE u.id = ?", 
        [$user_id]
    );
    $user = $userStmt->fetch();
    
    // Handle form submission for profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $institution = trim($_POST['institution'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        if (empty($name)) {
            $errors[] = "Name is required";
        }
        
        if (!$email) {
            $errors[] = "Valid email address is required";
        }
        
        // Check if email already exists (if changed)
        if ($email !== $user['email']) {
            $emailCheck = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
            if ($emailCheck->fetch()) {
                $errors[] = "Email address is already in use";
            }
        }
        
        // Password validation - only if user wants to change it
        if (!empty($new_password)) {
            // Verify current password
            $passwordCheck = $db->query("SELECT password FROM users WHERE id = ?", [$user_id]);
            $currentHash = $passwordCheck->fetch()['password'];
            
            if (!password_verify($current_password, $currentHash)) {
                $errors[] = "Current password is incorrect";
            }
            
            if (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters";
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match";
            }
        }
        
        // Handle profile picture upload
        $profile_picture = $user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Validate file type and size
            if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
                $errors[] = "Invalid file type. Please upload JPEG, PNG, or GIF files.";
            } elseif ($_FILES['profile_picture']['size'] > $max_size) {
                $errors[] = "File size exceeds the maximum limit of 2MB.";
            } else {
                // Create directory if it doesn't exist
                $upload_dir = dirname(__FILE__) . '/../assets/images/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = time() . '_' . basename($_FILES['profile_picture']['name']);
                $upload_file = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_file)) {
                    $profile_picture = 'assets/images/profiles/' . $filename;
                    
                    // Delete old profile picture if exists
                    if (!empty($user['profile_picture']) && file_exists(dirname(__FILE__) . '/../' . $user['profile_picture'])) {
                        unlink(dirname(__FILE__) . '/../' . $user['profile_picture']);
                    }
                } else {
                    $errors[] = "Failed to upload profile picture. Please try again.";
                }
            }
        }
        
        // Update profile if no errors
        if (empty($errors)) {
            // Start transaction
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            
            try {
                // Update basic user info
                $updateUserSql = "UPDATE users SET name = ?, email = ?";
                $userParams = [$name, $email];
                
                // Add password update if needed
                if (!empty($new_password)) {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateUserSql .= ", password = ?";
                    $userParams[] = $hashedPassword;
                }
                
                $updateUserSql .= " WHERE id = ?";
                $userParams[] = $user_id;
                
                // Execute user update
                $updateUserStmt = $db->query($updateUserSql, $userParams);
                
                // Check if profile already exists
                $checkProfileStmt = $db->query("SELECT id FROM user_profiles WHERE user_id = ?", [$user_id]);
                $profileExists = $checkProfileStmt->fetch();
                
                if ($profileExists) {
                    // Update existing profile
                    $updateProfileSql = "UPDATE user_profiles SET phone = ?, bio = ?, institution = ?, department = ?, profile_picture = ? WHERE user_id = ?";
                    $profileParams = [$phone, $bio, $institution, $department, $profile_picture, $user_id];
                    
                    $updateProfileStmt = $db->query($updateProfileSql, $profileParams);
                } else {
                    // Create new profile
                    $insertProfileSql = "INSERT INTO user_profiles (user_id, phone, bio, institution, department, profile_picture) VALUES (?, ?, ?, ?, ?, ?)";
                    $profileParams = [$user_id, $phone, $bio, $institution, $department, $profile_picture];
                    
                    $insertProfileStmt = $db->query($insertProfileSql, $profileParams);
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success = true;
                
                // Update session data
                $_SESSION['email'] = $email;
                
                // Refresh user data
                $userStmt = $db->query(
                    "SELECT u.id, u.name, u.email, u.user_type, 
                     p.phone, p.bio, p.institution, p.department, p.profile_picture
                     FROM users u
                     LEFT JOIN user_profiles p ON u.id = p.user_id
                     WHERE u.id = ?", 
                    [$user_id]
                );
                $user = $userStmt->fetch();
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to update profile. Please try again.";
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    $errors[] = "An error occurred. Please try again.";
    error_log("Profile error: " . $e->getMessage());
}

// Include header
$pageTitle = "My Profile";
include dirname(__FILE__) . '/../includes/header.php';
?>

<div class="container">
    <div class="page-header" style="margin-bottom: 2rem;">
        <h1>My Profile</h1>
        <p>Update your personal information and preferences.</p>
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
            <p>Profile updated successfully!</p>
        </div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="profile-sidebar">
            <div class="profile-picture-container">
                <?php if (!empty($user['profile_picture'])): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-picture-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                <h3><?= htmlspecialchars($user['name'] ?? 'User') ?></h3>
                <p class="profile-type"><?= ucfirst(htmlspecialchars($user['user_type'] ?? 'student')) ?></p>
            </div>
            
            <div class="profile-quick-links">
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="profile-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="<?= BASE_URL ?>pages/interests.php" class="profile-link">
                    <i class="fas fa-tags"></i> My Interests
                </a>
                <?php if ($user['user_type'] == 'organizer'): ?>
                    <a href="<?= BASE_URL ?>pages/create_event.php" class="profile-link">
                        <i class="fas fa-plus-circle"></i> Create Event
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="profile-content">
            <form method="post" action="" enctype="multipart/form-data" class="profile-form">
                <div class="form-section">
                    <h3 class="section-title">Basic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="profile_picture">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            <small>Maximum size: 2MB. Supported formats: JPEG, PNG, GIF</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">Academic Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="institution">Institution/University</label>
                            <input type="text" class="form-control" id="institution" name="institution" value="<?= htmlspecialchars($user['institution'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="department">Department/Major</label>
                            <input type="text" class="form-control" id="department" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">Bio</h3>
                    
                    <div class="form-group">
                        <label for="bio">About Me</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        <small>Share a little about yourself with the community</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="section-title">Change Password</h3>
                    <p>Leave blank if you don't want to change your password.</p>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <small>Password must be at least 8 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                    <a href="<?= BASE_URL ?>pages/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.profile-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
    margin-bottom: 2rem;
}

.profile-sidebar {
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    height: fit-content;
}

.profile-picture-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.profile-picture {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 1rem;
    border: 3px solid var(--primary-color);
}

.profile-picture-placeholder {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    border: 3px solid var(--primary-color);
}

.profile-picture-placeholder i {
    font-size: 3rem;
    color: var(--primary-color);
}

.profile-sidebar h3 {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
    text-align: center;
}

.profile-type {
    color: #6c757d;
    font-size: 0.9rem;
    text-align: center;
}

.profile-quick-links {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.profile-link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    background-color: #f8f9fa;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.2s ease;
}

.profile-link:hover {
    background-color: var(--primary-color);
    color: white;
}

.profile-link i {
    margin-right: 0.75rem;
    width: 20px;
    text-align: center;
}

.profile-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: var(--box-shadow);
    padding: 2rem;
}

.form-section {
    margin-bottom: 2rem;
}

.section-title {
    font-size: 1.2rem;
    color: var(--primary-color);
    margin-bottom: 1.25rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .profile-container {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
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