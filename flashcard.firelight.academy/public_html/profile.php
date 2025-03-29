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

// Get basic user stats
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

// Get study streak data
$db->query("SELECT COUNT(DISTINCT DATE(created_at)) as total_days 
            FROM user_responses 
            WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$totalStudyDays = $db->single()->total_days;

$db->query("SELECT COUNT(DISTINCT DATE(created_at)) as streak 
            FROM user_responses 
            WHERE user_id = :user_id 
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)");
$db->bind(':user_id', $userId);
$weekStreak = $db->single()->streak;

// Get cards due soon
$db->query("SELECT COUNT(*) as due_today 
            FROM user_progress 
            WHERE user_id = :user_id 
            AND DATE(next_review) = CURDATE()");
$db->bind(':user_id', $userId);
$dueToday = $db->single()->due_today;

$db->query("SELECT COUNT(*) as due_tomorrow 
            FROM user_progress 
            WHERE user_id = :user_id 
            AND DATE(next_review) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
$db->bind(':user_id', $userId);
$dueTomorrow = $db->single()->due_tomorrow;

// Get category performance
$db->query("SELECT c.name, 
            COUNT(DISTINCT f.card_id) as total_cards,
            COUNT(DISTINCT up.card_id) as studied_cards,
            ROUND(AVG(CASE WHEN ur.understood = 1 THEN 100 ELSE 0 END)) as understanding_rate
            FROM categories c
            JOIN flashcards f ON c.category_id = f.category_id
            LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
            LEFT JOIN user_responses ur ON f.card_id = ur.card_id AND ur.user_id = :user_id
            WHERE EXISTS (SELECT 1 FROM user_responses WHERE user_id = :user_id AND card_id = f.card_id)
            GROUP BY c.category_id
            ORDER BY understanding_rate DESC");
$db->bind(':user_id', $userId);
$categoryPerformance = $db->resultSet();

// Get difficulty level performance
$db->query("SELECT f.difficulty, 
            COUNT(DISTINCT ur.response_id) as responses,
            SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as correct_responses,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE ur.user_id = :user_id
            GROUP BY f.difficulty
            ORDER BY f.difficulty");
$db->bind(':user_id', $userId);
$difficultyPerformance = $db->resultSet();

// Get study activity by day of week
$db->query("SELECT 
            DAYNAME(created_at) as day_name,
            COUNT(*) as response_count,
            ROUND(AVG(CASE WHEN understood = 1 THEN 100 ELSE 0 END)) as understanding_rate
            FROM user_responses
            WHERE user_id = :user_id
            GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at)
            ORDER BY DAYOFWEEK(created_at)");
$db->bind(':user_id', $userId);
$weekdayActivity = $db->resultSet();

// Get most challenging cards
$db->query("SELECT f.question, c.name as category_name, f.difficulty,
            COUNT(ur.response_id) as attempts,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN categories c ON f.category_id = c.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE ur.user_id = :user_id
            GROUP BY f.card_id
            HAVING attempts >= 3
            ORDER BY understanding_rate ASC
            LIMIT 5");
$db->bind(':user_id', $userId);
$challengingCards = $db->resultSet();

// Get most mastered cards
$db->query("SELECT f.question, c.name as category_name, f.difficulty,
            COUNT(ur.response_id) as attempts,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN categories c ON f.category_id = c.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE ur.user_id = :user_id
            GROUP BY f.card_id
            HAVING attempts >= 3 AND understanding_rate = 100
            ORDER BY attempts DESC
            LIMIT 5");
$db->bind(':user_id', $userId);
$masteredCards = $db->resultSet();

// Get detailed stats for reset section
$db->query("SELECT COUNT(DISTINCT card_id) as total_cards_studied FROM user_responses WHERE user_id = :user_id");
$db->bind(':user_id', $userId);
$totalCardsStudied = $db->single()->total_cards_studied;

// Get total categories studied
$db->query("SELECT COUNT(DISTINCT c.category_id) as total_categories 
            FROM categories c 
            JOIN flashcards f ON c.category_id = f.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE ur.user_id = :user_id");
$db->bind(':user_id', $userId);
$totalCategoriesStudied = $db->single()->total_categories;

// Get categories that user has studied
$db->query("SELECT DISTINCT c.category_id, c.name 
            FROM categories c 
            JOIN flashcards f ON c.category_id = f.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE ur.user_id = :user_id
            ORDER BY c.name");
$db->bind(':user_id', $userId);
$studiedCategories = $db->resultSet();
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
        
        <!-- Upcoming Reviews Card -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Upcoming Reviews</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span>Due today:</span>
                    <span class="badge bg-primary rounded-pill"><?php echo $dueToday; ?> cards</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span>Due tomorrow:</span>
                    <span class="badge bg-secondary rounded-pill"><?php echo $dueTomorrow; ?> cards</span>
                </div>
                
                <?php if ($dueToday > 0): ?>
                <div class="d-grid mt-3">
                    <a href="<?php echo APP_URL; ?>/study.php" class="btn btn-outline-primary">Start Reviewing</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Stats Overview Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Your Learning Stats</h5>
            </div>
            <div class="card-body">
                <div class="row g-4 text-center">
                    <div class="col-md-3">
                        <div class="border rounded py-3">
                            <h3 class="h2 mb-0"><?php echo $totalResponses; ?></h3>
                            <p class="text-muted mb-0">Total Responses</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="border rounded py-3">
                            <h3 class="h2 mb-0"><?php echo $understandingRate; ?>%</h3>
                            <p class="text-muted mb-0">Understanding Rate</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="border rounded py-3">
                            <h3 class="h2 mb-0"><?php echo $weekStreak; ?>/7</h3>
                            <p class="text-muted mb-0">Weekly Streak</p>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="border rounded py-3">
                            <h3 class="h2 mb-0"><?php echo $totalStudyDays; ?></h3>
                            <p class="text-muted mb-0">Study Days</p>
                        </div>
                    </div>
                </div>
                
                <?php if($totalResponses > 0): ?>
                <div class="mt-4">
                    <h6>Study Progress Overview</h6>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $understandingRate; ?>%" 
                             aria-valuenow="<?php echo $understandingRate; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $understandingRate; ?>% Understood
                        </div>
                        <div class="progress-bar bg-warning" role="progressbar" 
                             style="width: <?php echo 100 - $understandingRate; ?>%" 
                             aria-valuenow="<?php echo 100 - $understandingRate; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo 100 - $understandingRate; ?>% Needs Work
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Category Performance Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Category Performance</h5>
            </div>
            <div class="card-body">
                <?php if(empty($categoryPerformance)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        You haven't studied any categories yet.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Cards Studied</th>
                                    <th>Understanding</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categoryPerformance as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category->name); ?></td>
                                    <td><?php echo $category->studied_cards; ?> / <?php echo $category->total_cards; ?></td>
                                    <td>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar <?php echo $category->understanding_rate >= 70 ? 'bg-success' : ($category->understanding_rate >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                 role="progressbar" style="width: <?php echo $category->understanding_rate; ?>%" 
                                                 aria-valuenow="<?php echo $category->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <small><?php echo $category->understanding_rate; ?>%</small>
                                    </td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/study.php?category=<?php echo $category->category_id; ?>" class="btn btn-sm btn-outline-primary">Study</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Performance by Difficulty Card -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Performance by Difficulty</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($difficultyPerformance)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No difficulty data available yet.
                            </div>
                        <?php else: ?>
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="difficultyChart"></canvas>
                            </div>
                            
                            <div class="table-responsive mt-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Difficulty</th>
                                            <th>Responses</th>
                                            <th>Understanding</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($difficultyPerformance as $level): ?>
                                        <tr>
                                            <td>Level <?php echo $level->difficulty; ?></td>
                                            <td><?php echo $level->responses; ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar <?php echo $level->understanding_rate >= 70 ? 'bg-success' : ($level->understanding_rate >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         role="progressbar" style="width: <?php echo $level->understanding_rate; ?>%" 
                                                         aria-valuenow="<?php echo $level->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small><?php echo $level->understanding_rate; ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Study Activity by Day</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($weekdayActivity)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No activity data available yet.
                            </div>
                        <?php else: ?>
                            <div class="chart-container" style="height: 200px;">
                                <canvas id="weekdayChart"></canvas>
                            </div>
                            
                            <div class="table-responsive mt-3">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Day</th>
                                            <th>Responses</th>
                                            <th>Understanding</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($weekdayActivity as $day): ?>
                                        <tr>
                                            <td><?php echo $day->day_name; ?></td>
                                            <td><?php echo $day->response_count; ?></td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar <?php echo $day->understanding_rate >= 70 ? 'bg-success' : ($day->understanding_rate >= 40 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                         role="progressbar" style="width: <?php echo $day->understanding_rate; ?>%" 
                                                         aria-valuenow="<?php echo $day->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small><?php echo $day->understanding_rate; ?>%</small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Challenging and Mastered Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Most Challenging Cards</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($challengingCards)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Not enough data to determine challenging cards yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach($challengingCards as $card): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars(substr($card->question, 0, 60)) . (strlen($card->question) > 60 ? '...' : ''); ?></h6>
                                        <small class="text-danger"><?php echo $card->understanding_rate; ?>%</small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($card->category_name); ?> • 
                                            Difficulty: <?php echo $card->difficulty; ?> •
                                            Attempts: <?php echo $card->attempts; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Mastered Cards</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($masteredCards)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No mastered cards yet. Keep studying!
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach($masteredCards as $card): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars(substr($card->question, 0, 60)) . (strlen($card->question) > 60 ? '...' : ''); ?></h6>
                                        <small class="text-success"><?php echo $card->understanding_rate; ?>%</small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($card->category_name); ?> • 
                                            Difficulty: <?php echo $card->difficulty; ?> •
                                            Attempts: <?php echo $card->attempts; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Progress Section -->
<div class="col-md-12 mt-4">
    <div class="card">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Reset Your Progress</h5>
        </div>
        <div class="card-body">
            <!-- Section for resetting a specific category -->
            <div class="row mb-4 pb-4 border-bottom">
                <div class="col-md-8">
                    <h6>Reset Category Progress</h6>
                    <p class="card-text">
                        Reset your progress for a specific category while keeping everything else.
                    </p>
                    
                    <?php if (!empty($studiedCategories)): ?>
                        <form action="reset_progress.php" method="post" class="row g-3">
                            <div class="col-md-8">
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($studiedCategories as $category): ?>
                                        <option value="<?php echo $category->category_id; ?>">
                                            <?php echo htmlspecialchars($category->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#resetCategoryModal">
                                    Reset Category
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            You haven't studied any categories yet.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-center">
                    <div class="stats-to-reset">
                        <div class="mb-2">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        </div>
                        <p class="text-muted small">
                            This will reset your progress, responses, and review schedule only for the selected category.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Section for full reset -->
            <div class="row mt-3">
                <div class="col-md-8">
                    <h6>Reset All Progress</h6>
                    <p class="card-text">
                        This will permanently delete all your flashcard progress, responses, and study history across all categories. This action cannot be undone.
                    </p>
                    
                    <!-- Stats to reset -->
                    <div class="stats-to-reset mb-4">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4><?php echo $totalResponses; ?></h4>
                                <p class="small">Total Responses</p>
                            </div>
                            <div class="col-md-4">
                                <h4><?php echo $totalCardsStudied; ?></h4>
                                <p class="small">Cards Studied</p>
                            </div>
                            <div class="col-md-4">
                                <h4><?php echo $totalStudyDays; ?></h4>
                                <p class="small">Days Studied</p>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetConfirmModal">
                        Reset All Progress
                    </button>
                </div>
                <div class="col-md-4 text-center">
                    <div class="alert alert-danger py-4">
                        <i class="bi bi-exclamation-triangle-fill mb-3" style="font-size: 2rem;"></i>
                        <p class="mb-0"><strong>WARNING:</strong> This will delete all your study history and progress across <strong>ALL</strong> categories.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reset Category Confirmation Modal -->
<div class="modal fade" id="resetCategoryModal" tabindex="-1" aria-labelledby="resetCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="resetCategoryModalLabel">Reset Category Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reset your progress for the selected category?</p>
                <p><strong>This will delete:</strong></p>
                <ul>
                    <li>Your spaced repetition schedules for this category</li>
                    <li>All your responses to flashcards in this category</li>
                    <li>Your understanding statistics for this category</li>
                </ul>
                <p class="text-warning"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="reset_progress.php" method="post">
                    <input type="hidden" name="category_id" id="resetCategoryId">
                    <input type="hidden" name="confirm_category_reset" value="1">
                    <button type="submit" class="btn btn-warning">Reset Category Progress</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset All Confirmation Modal -->
<div class="modal fade" id="resetConfirmModal" tabindex="-1" aria-labelledby="resetConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetConfirmModalLabel">Confirm Full Reset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you absolutely sure you want to reset ALL your progress?</p>
                <p><strong>This will permanently delete:</strong></p>
                <ul>
                    <li>Your spaced repetition schedules for all categories</li>
                    <li>All flashcard responses</li>
                    <li>Your understanding statistics</li>
                    <li>All study history</li>
                </ul>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="reset_progress.php" method="post">
                    <input type="hidden" name="confirm_reset" value="1">
                    <button type="submit" class="btn btn-danger">Yes, Reset Everything</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Charts Initialization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // When category reset modal is about to be shown
    document.getElementById('resetCategoryModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const form = button.closest('form');
        const categorySelect = form.querySelector('select[name="category_id"]');
        
        if (!categorySelect.value) {
            // Prevent modal from opening if no category is selected
            event.preventDefault();
            alert('Please select a category to reset.');
            return;
        }
        
        // Set the category ID in the modal form
        document.getElementById('resetCategoryId').value = categorySelect.value;
    });
    
    // Initialize difficulty chart if element exists
    const difficultyChartEl = document.getElementById('difficultyChart');
    if (difficultyChartEl) {
        const difficultyData = <?php echo json_encode($difficultyPerformance ?? []); ?>;
        
        if (difficultyData.length > 0) {
            new Chart(difficultyChartEl, {
                type: 'bar',
                data: {
                    labels: difficultyData.map(d => 'Level ' + d.difficulty),
                    datasets: [{
                        label: 'Understanding Rate',
                        data: difficultyData.map(d => d.understanding_rate),
                        backgroundColor: difficultyData.map(d => {
                            const rate = d.understanding_rate;
                            return rate >= 70 ? 'rgba(40, 167, 69, 0.7)' : 
                                  (rate >= 40 ? 'rgba(255, 193, 7, 0.7)' : 'rgba(220, 53, 69, 0.7)');
                        }),
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    }
    
    // Initialize weekday chart if element exists
    const weekdayChartEl = document.getElementById('weekdayChart');
    if (weekdayChartEl) {
        const weekdayData = <?php echo json_encode($weekdayActivity ?? []); ?>;
        
        if (weekdayData.length > 0) {
            new Chart(weekdayChartEl, {
                type: 'bar',
                data: {
                    labels: weekdayData.map(d => d.day_name),
                    datasets: [{
                        label: 'Responses',
                        data: weekdayData.map(d => d.response_count),
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Understanding Rate',
                        data: weekdayData.map(d => d.understanding_rate),
                        backgroundColor: 'rgba(40, 167, 69, 0.2)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Responses'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            title: {
                                display: true,
                                text: 'Understanding Rate'
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>