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
// Get question type from query parameter if provided
$questionType = isset($_GET['question_type']) ? sanitize($_GET['question_type']) : 'all';

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
        <div class="card category-card">
            <div class="card-body">
                <h5 class="card-title"><?php echo $category->name; ?></h5>
                <p class="card-text"><?php echo $category->description; ?></p>
                
                <!-- Question type selector -->
                <p class="mb-2 fw-bold">Question Type:</p>
                <div class="btn-group w-100 mb-3" role="group">
                    <a href="?category=<?php echo $category->category_id; ?>&question_type=all" class="btn btn-outline-primary">All Types</a>
                    <a href="?category=<?php echo $category->category_id; ?>&question_type=text" class="btn btn-outline-primary">Short Answer</a>
                    <a href="?category=<?php echo $category->category_id; ?>&question_type=multiple_choice" class="btn btn-outline-primary">Multiple Choice</a>
                </div>
                
                <a href="?category=<?php echo $category->category_id; ?>" class="btn btn-primary w-100">Study All Questions</a>
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
    
    // Get question type indicator
    $questionTypeText = "All Questions";
    if ($questionType === "text") {
        $questionTypeText = "Short Answer Questions";
    } elseif ($questionType === "multiple_choice") {
        $questionTypeText = "Multiple Choice Questions";
    }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Studying: <?php echo $category->name; ?></h1>
    <div class="badge bg-primary"><?php echo $questionTypeText; ?></div>
</div>

<!-- Hidden input for question type -->
<input type="hidden" id="questionType" value="<?php echo $questionType; ?>">
<input type="hidden" id="categoryId" value="<?php echo $categoryId; ?>">

<!-- Progress bar instead of card counter -->
<div class="progress mb-4" style="height: 10px;">
    <div id="studyProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;" 
         aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" data-progress="0"></div>
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
            <input type="hidden" id="startTime" value="" />
            <input type="hidden" id="currentAnswerType" value="" />
            
            <!-- Text-based response controls -->
            <div id="textResponseControls" class="response-controls">
                <div class="mb-3">
                    <label for="userResponse" class="form-label">Your Response:</label>
                    <textarea id="userResponse" class="form-control" rows="5" placeholder="Type your answer here..."></textarea>
                </div>
                
                <button id="submitTextResponse" class="btn btn-primary">
                    <span id="responseLoading" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    Submit Answer
                </button>
            </div>
            
            <!-- Multiple choice response controls -->
            <div id="multipleChoiceControls" class="response-controls d-none">
                <div class="mb-3">
                    <label class="form-label">Choose the correct answer:</label>
                    <div id="optionsContainer" class="d-grid gap-2"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="feedbackContainer" class="d-none">
        <!-- Feedback content will be inserted here via JavaScript -->
    </div>
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