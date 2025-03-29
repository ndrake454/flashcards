<?php
// Set page title
$pageTitle = 'Profile';

// Include header
include_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    $_SESSION['error_msg'] = 'Please log in to view your profile.';
    $_SESSION['redirect_after_login'] = APP_URL . '/profile.php';
    redirect(APP_URL . '/login.php');
}

// Initialize database
$db = new Database();

// Get user data
$userId = $_SESSION['user_id'];
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$user = $db->single();

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);
    
    if ($action === 'update_profile') {
        // Update profile
        $email = sanitize($_POST['email']);
        
        // Validate email
        if (empty($email)) {
            $_SESSION['error_msg'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error_msg'] = 'Please enter a valid email.';
        } else {
            // Check if email is already in use by another user
            $db->query("SELECT * FROM users WHERE email = :email AND user_id != :user_id");
            $db->bind(':email', $email);
            $db->bind(':user_id', $userId);
            
            if ($db->rowCount() > 0) {
                $_SESSION['error_msg'] = 'Email is already in use by another account.';
            } else {
                // Update email
                $db->query("UPDATE users SET email = :email WHERE user_id = :user_id");
                $db->bind(':email', $email);
                $db->bind(':user_id', $userId);
                
                if ($db->execute()) {
                    $_SESSION['success_msg'] = 'Profile updated successfully.';
                    redirect(APP_URL . '/profile.php');
                } else {
                    $_SESSION['error_msg'] = 'Failed to update profile.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        // Change password
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Validate passwords
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error_msg'] = 'All password fields are required.';
        } elseif (!verifyPassword($currentPassword, $user->password)) {
            $_SESSION['error_msg'] = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['error_msg'] = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error_msg'] = 'New passwords do not match.';
        } else {
            // Hash new password
            $hashedPassword = hashPassword($newPassword);
            
            // Update password
            $db->query("UPDATE users SET password = :password WHERE user_id = :user_id");
            $db->bind(':password', $hashedPassword);
            $db->bind(':user_id', $userId);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Password changed successfully.';
                redirect(APP_URL . '/profile.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to change password.';
            }
        }
    }
}

// Get user stats
$db->query("SELECT COUNT(*) as total_responses FROM user_responses WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$totalResponses = $db->single()->total_responses;

$db->query("SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN understood = 1 THEN 1 ELSE 0 END) as understood 
            FROM user_responses 
            WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$responseStats = $db->single();
$understandingRate = $responseStats->total > 0 ? 
    round(($responseStats->understood / $responseStats->total) * 100) : 0;

// Get category progress
$db->query("SELECT c.name, 
            COUNT(f.card_id) as total_cards,
            COUNT(up.card_id) as studied_cards,
            SUM(CASE WHEN up.times_correct > 0 THEN 1 ELSE 0 END) as mastered_cards
            FROM categories c
            JOIN flashcards f ON c.category_id = f.category_id
            LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
            GROUP BY c.category_id
            HAVING studied_cards > 0
            ORDER BY studied_cards DESC
            LIMIT 5");
$db->bind(':user_id', $userId);
$categoryProgress = $db->resultSet();

// Get recent activity
$db->query("SELECT ur.created_at, f.question, ur.understood, c.name as category_name
            FROM user_responses ur
            JOIN flashcards f ON ur.card_id = f.card_id
            JOIN categories c ON f.category_id = c.category_id
            WHERE ur.user_id = :user_id
            ORDER BY ur.created_at DESC
            LIMIT 10");
$db->bind(':user_id', $userId);
$recentActivity = $db->resultSet();
?>

<div class="row">
    <div class="col-md-4">
        <!-- Profile Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Your Profile</h5>
            </div>
            <div class="card-body">
                <h2 class="card-title h4"><?php echo htmlspecialchars($user->username); ?></h2>
                <p class="card-text">Member since: <?php echo date('F j, Y', strtotime($user->created_at)); ?></p>
                
                <form action="profile.php" method="post" class="mt-4 needs-validation" novalidate>
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user->email); ?>" required>
                        <div class="invalid-feedback">
                            Please enter a valid email.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                        <div class="invalid-feedback">
                            Please enter your current password.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="invalid-feedback">
                            New password must be at least 6 characters.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">
                            Please confirm your new password.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-secondary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Stats Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Your Stats</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="h5">Total Responses</h3>
                            <p class="display-6"><?php echo $totalResponses; ?></p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="h5">Understanding Rate</h3>
                            <p class="display-6"><?php echo $understandingRate; ?>%</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center">
                            <h3 class="h5">Streak</h3>
                            <p class="display-6">
                                <?php
                                // Calculate streak
                                $db->query("SELECT COUNT(DISTINCT DATE(created_at)) as days
                                            FROM user_responses
                                            WHERE user_id = :user_id
                                            AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)");
                                $db->bind(':user_id', $userId);
                                echo $db->single()->days;
                                ?>
                                /7
                            </p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($categoryProgress)): ?>
                <div class="mt-4">
                    <h6>Category Progress</h6>
                    
                    <?php foreach ($categoryProgress as $category): ?>
                        <?php 
                        $progressPercent = round(($category->studied_cards / $category->total_cards) * 100);
                        $masteredPercent = $category->studied_cards > 0 ? 
                            round(($category->mastered_cards / $category->studied_cards) * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span><?php echo htmlspecialchars($category->name); ?></span>
                                <span><?php echo $category->studied_cards; ?> / <?php echo $category->total_cards; ?> cards studied</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $masteredPercent; ?>%" 
                                     aria-valuenow="<?php echo $masteredPercent; ?>" aria-valuemin="0" aria-valuemax="100" 
                                     data-bs-toggle="tooltip" title="<?php echo $masteredPercent; ?>% mastered">
                                </div>
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progressPercent - $masteredPercent; ?>%" 
                                     aria-valuenow="<?php echo $progressPercent - $masteredPercent; ?>" aria-valuemin="0" aria-valuemax="100"
                                     data-bs-toggle="tooltip" title="<?php echo $progressPercent; ?>% studied">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recentActivity)): ?>
                        <div class="list-group-item">
                            <p class="mb-0">No recent activity.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php echo $activity->understood ? 
                                            '<span class="badge bg-success">Understood</span>' : 
                                            '<span class="badge bg-warning text-dark">Needs Review</span>'; ?>
                                        <?php echo htmlspecialchars(
                                            strlen($activity->question) > 70 ? 
                                            substr($activity->question, 0, 70) . '...' : 
                                            $activity->question
                                        ); ?>
                                    </h6>
                                    <small class="text-muted"><?php echo date('M j, g:i a', strtotime($activity->created_at)); ?></small>
                                </div>
                                <small class="text-muted">Category: <?php echo htmlspecialchars($activity->category_name); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer">
                <a href="study.php" class="btn btn-primary">Study More</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>