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
    
    // Get cards due today
    $db->query("SELECT COUNT(*) as cards_due FROM flashcards f
                LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                WHERE up.next_review IS NULL OR up.next_review <= NOW()");
    $db->bind(':user_id', $userId);
    $cardsDue = $db->single()->cards_due;
    
    // Get understanding percentage
    $db->query("SELECT 
                    COUNT(*) as total_responses,
                    SUM(CASE WHEN understood = 1 THEN 1 ELSE 0 END) as understood_responses 
                FROM user_responses 
                WHERE user_id = :user_id");
    $db->bind(':user_id', $userId);
    $responseStats = $db->single();
    
    $understandingPercentage = $responseStats->total_responses > 0 ? 
        round(($responseStats->understood_responses / $responseStats->total_responses) * 100) : 0;
        
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
    <h1 class="display-5 fw-bold">Welcome to <?php echo APP_NAME; ?></h1>
    <div class="col-lg-6 mx-auto">
        <p class="lead mb-4">Enhance your learning with AI-powered flashcards that adapt to your understanding and provide intelligent feedback.</p>
        
        <?php if (!isLoggedIn()): ?>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="register.php" class="btn btn-primary btn-lg px-4 gap-3">Get Started</a>
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
<!-- User dashboard -->
<div class="container py-4">
    <h2 class="mb-4">Your Dashboard</h2>
    
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Cards Studied</h5>
                    <p class="display-4"><?php echo $totalStudied; ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Cards Due Today</h5>
                    <p class="display-4"><?php echo $cardsDue; ?></p>
                    <?php if ($cardsDue > 0): ?>
                    <a href="study.php" class="btn btn-primary">Study Now</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h5 class="card-title">Understanding Rate</h5>
                    <div class="progress mb-3" style="height: 30px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $understandingPercentage; ?>%;" 
                             aria-valuenow="<?php echo $understandingPercentage; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo $understandingPercentage; ?>%
                        </div>
                    </div>
                    <p class="card-text">Based on your recent responses</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($recentCategories)): ?>
    <h3 class="mb-3">Recently Studied</h3>
    <div class="row g-4 mb-5">
        <?php foreach ($recentCategories as $category): ?>
        <div class="col-md-4">
            <div class="card category-card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $category->name; ?></h5>
                    <a href="study.php?category=<?php echo $category->category_id; ?>" class="btn btn-outline-primary mt-auto">Continue Studying</a>
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