<?php
// Set page title
$pageTitle = 'Study Flashcards';

// Add extra CSS and JS
$extraCSS = ['study.css'];
$extraJS = ['study.js', 'speech-features.js']; // Keep the speech-features.js for TTS

// Include header
include_once 'includes/header.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    $_SESSION['error_msg'] = 'Please log in to access the study area.';
    redirect(APP_URL . '/login.php');
}

// Get category ID from query parameter
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : 0;

// If no category is selected, show category selection
if ($categoryId === 0) {
    // Get categories
    $db = new Database();
    $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $db->resultSet();
?>

<h1>Study Flashcards</h1>
<p class="lead">Select a category to begin studying:</p>

<div class="row">
    <?php foreach ($categories as $category): ?>
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><?php echo $category->name; ?></h5>
                <p class="card-text"><?php echo $category->description; ?></p>
                <a href="?category=<?php echo $category->category_id; ?>" class="btn btn-primary">Study This Category</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
} else {
    // Category is selected, show study interface
    $db = new Database();
    
    // Get category info
    $db->query("SELECT * FROM categories WHERE category_id = :category_id");
    $db->bind(':category_id', $categoryId);
    $category = $db->single();
    
    // Check if category exists
    if (!$category) {
        $_SESSION['error_msg'] = 'Category not found.';
        redirect(APP_URL . '/study.php');
    }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Studying: <?php echo $category->name; ?></h1>
    <span id="progressIndicator" class="badge bg-primary">Loading...</span>
</div>

<div id="studyContainer">
    <div id="flashcardContainer" class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Question</h5>
        </div>
        <div class="card-body">
            <div id="cardLoading" class="text-center py-5 d-none">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading next card...</p>
            </div>
            
            <p id="questionText" class="card-text"></p>
            
            <input type="hidden" id="currentCardId" value="" />
            <input type="hidden" id="categoryId" value="<?php echo $categoryId; ?>" />
            <input type="hidden" id="startTime" value="" />
            
            <div id="responseControls">
                <div class="mb-3">
                    <label for="userResponse" class="form-label">Your Response:</label>
                    <textarea id="userResponse" class="form-control" rows="5" placeholder="Type your answer here..."></textarea>
                </div>
                
                <button id="submitResponse" class="btn btn-primary">
                    <span id="responseLoading" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    Submit Answer
                </button>
            </div>
            
            <div id="nextControls" class="d-none">
                <button id="nextCard" onclick="loadNextCard()" class="btn btn-success">Next Card</button>
            </div>
        </div>
    </div>
    
    <div id="feedbackContainer" class="d-none">
        <!-- Feedback content will be inserted here via JavaScript -->
    </div>
    
    <!-- Text-to-Speech Button Template - keep this for TTS functionality -->
    <template id="ttsButtonTemplate">
        <div class="text-center mt-3">
            <button id="readFeedbackBtn" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-volume-up me-1"></i> Read Feedback Aloud
            </button>
        </div>
    </template>
</div>

<script>
    // Load the first card when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadNextCard();
    });
</script>

<?php
}

// Include footer
include_once 'includes/footer.php';
?>