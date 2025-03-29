<?php
// Set page title
$pageTitle = 'Home';

// Include header
include_once 'includes/header.php';

// Initialize database
$db = new Database();

// Get stats if user is logged in
if (isLoggedIn()) {
    // Get user ID
    $userId = $_SESSION['user_id'];
    
    // Get total cards studied
    $db->query("SELECT COUNT(*) as total_studied FROM user_responses WHERE user_id = :user_id");
    $db->bind(':user_id', $userId);
    $totalStudied = $db->single()->total_studied;
    
    // Get cards studied today
    $db->query("SELECT COUNT(*) as cards_today FROM user_responses 
                WHERE user_id = :user_id 
                AND DATE(created_at) = CURRENT_DATE()");
    $db->bind(':user_id', $userId);
    $cardsToday = $db->single()->cards_today;
    
    // Get study streak (consecutive days)
    $db->query("SELECT COUNT(DISTINCT DATE(created_at)) as days
                FROM user_responses 
                WHERE user_id = :user_id
                AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)");
    $db->bind(':user_id', $userId);
    $studyStreak = $db->single()->days;
    
    // Get mastery progress (cards with high ease factor)
    $db->query("SELECT COUNT(*) as mastered FROM user_progress 
                WHERE user_id = :user_id AND ease_factor >= 2.5 AND times_correct > 1");
    $db->bind(':user_id', $userId);
    $masteredCards = $db->single()->mastered;
    
    // Get total unique cards seen
    $db->query("SELECT COUNT(DISTINCT card_id) as total_unique FROM user_progress WHERE user_id = :user_id");
    $db->bind(':user_id', $userId);
    $uniqueCardsSeen = $db->single()->total_unique;
    
    // Calculate mastery percentage
    $masteryPercentage = $uniqueCardsSeen > 0 ? round(($masteredCards / $uniqueCardsSeen) * 100) : 0;
        
    // Get recent categories studied
    $db->query("SELECT DISTINCT c.category_id, c.name 
                FROM categories c
                JOIN flashcards f ON c.category_id = f.category_id
                JOIN user_responses ur ON f.card_id = ur.card_id
                WHERE ur.user_id = :user_id
                ORDER BY ur.created_at DESC
                LIMIT 3");
    $db->bind(':user_id', $userId);
    $recentCategories = $db->resultSet();
}

// Get total stats (for all users)
$db->query("SELECT COUNT(*) as total_cards FROM flashcards");
$totalCards = $db->single()->total_cards;

$db->query("SELECT COUNT(*) as total_categories FROM categories");
$totalCategories = $db->single()->total_categories;
?>

<div class="px-4 py-5 my-5 text-center">
    <h1 class="display-5 fw-bold">Welcome to Quizlight</h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4">Enhance your learning with AI-powered flashcards that adapt to your understanding and provide intelligent feedback.</p>
        
        <?php if (!isLoggedIn()): ?>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="register.php" class="btn btn-primary btn-lg px-4 gap-3">Sign Up</a>
            <a href="login.php" class="btn btn-outline-secondary btn-lg px-4">Sign In</a>
        </div>
        <?php else: ?>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="study.php" class="btn btn-primary btn-lg px-4 gap-3">Start Studying</a>
            <a href="categories.php" class="btn btn-outline-secondary btn-lg px-4">Browse Categories</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<!-- Study Overview -->
<div class="container py-4">
    <h2 class="mb-4">Your Study Overview</h2>
    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Study Streak</h5>
                    <p class="display-4"><?php echo $studyStreak; ?></p>
                    <p class="card-text text-muted">days this week</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Cards Studied Today</h5>
                    <p class="display-4"><?php echo $cardsToday; ?></p>
                    <a href="study.php" class="btn btn-primary">Study More</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Mastery Progress</h5>
                    <div class="progress mb-3" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $masteryPercentage; ?>%;" 
                             aria-valuenow="<?php echo $masteryPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $masteryPercentage; ?>%
                        </div>
                    </div>
                    <p class="card-text text-muted"><?php echo $masteredCards; ?> cards mastered</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($recentCategories)): ?>
    <h3 class="mb-3">Continue Studying</h3>
    <div class="row g-4 mb-5">
        <?php foreach ($recentCategories as $category): ?>
        <div class="col-md-4">
            <div class="card category-card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $category->name; ?></h5>
                    <?php
                    // Get due cards count for this category
                    $db->query("SELECT COUNT(*) as due FROM flashcards f
                                LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                                WHERE f.category_id = :category_id
                                AND (up.next_review IS NULL OR up.next_review <= NOW())");
                    $db->bind(':user_id', $userId);
                    $db->bind(':category_id', $category->category_id);
                    $categoryDueCards = $db->single()->due;
                    ?>
                    <p class="text-muted mb-3"><?php echo $categoryDueCards; ?> cards due</p>
                    <a href="study.php?category=<?php echo $category->category_id; ?>" class="btn btn-outline-primary mt-auto">Continue</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- Features section for non-logged in users -->
<div class="container py-5">
    <h2 class="text-center mb-5">Features</h2>
    
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="h5 card-title">AI-Powered Evaluation</h3>
                    <p class="card-text">Our system uses advanced AI to analyze your responses and determine if you truly understand the concepts.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="h5 card-title">Intelligent Feedback</h3>
                    <p class="card-text">Receive personalized feedback that fills knowledge gaps and reinforces your understanding.</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h3 class="h5 card-title">Spaced Repetition</h3>
                    <p class="card-text">Cards are scheduled for review at optimal intervals based on your performance, maximizing learning efficiency.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-5">
        <p class="lead">Currently serving <?php echo $totalCards; ?> flashcards across <?php echo $totalCategories; ?> categories.</p>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include_once 'includes/footer.php';
?>