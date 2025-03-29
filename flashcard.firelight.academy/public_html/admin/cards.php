<?php
// Set page title
$pageTitle = 'Manage Flashcards';

// Start session and include functions
session_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['error_msg'] = 'You do not have permission to access this page.';
    redirect(APP_URL);
}

// Initialize database
$db = new Database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form data
    $action = sanitize($_POST['action']);
    
    if ($action === 'create_card') {
        // Create new flashcard
        $categoryId = intval($_POST['category_id']);
        $question = sanitize($_POST['question']);
        $answer = sanitize($_POST['answer']);
        $answerType = sanitize($_POST['answer_type']);
        $difficulty = intval($_POST['difficulty']);
        
        // Validate input
        if (empty($question) || empty($answer) || empty($answerType)) {
            $_SESSION['error_msg'] = 'All fields are required.';
        } else {
            // Insert into database
            $db->query("INSERT INTO flashcards (category_id, question, answer, answer_type, difficulty, created_by) 
                        VALUES (:category_id, :question, :answer, :answer_type, :difficulty, :created_by)");
            $db->bind(':category_id', $categoryId);
            $db->bind(':question', $question);
            $db->bind(':answer', $answer);
            $db->bind(':answer_type', $answerType);
            $db->bind(':difficulty', $difficulty);
            $db->bind(':created_by', $_SESSION['user_id']);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Flashcard created successfully.';
                redirect(APP_URL . '/admin/cards.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to create flashcard.';
            }
        }
    } elseif ($action === 'update_card') {
        // Update existing flashcard
        $cardId = intval($_POST['card_id']);
        $categoryId = intval($_POST['category_id']);
        $question = sanitize($_POST['question']);
        $answer = sanitize($_POST['answer']);
        $answerType = sanitize($_POST['answer_type']);
        $difficulty = intval($_POST['difficulty']);
        
        // Validate input
        if (empty($question) || empty($answer) || empty($answerType)) {
            $_SESSION['error_msg'] = 'All fields are required.';
        } else {
            // Update database
            $db->query("UPDATE flashcards 
                        SET category_id = :category_id, 
                            question = :question, 
                            answer = :answer, 
                            answer_type = :answer_type, 
                            difficulty = :difficulty
                        WHERE card_id = :card_id");
            $db->bind(':category_id', $categoryId);
            $db->bind(':question', $question);
            $db->bind(':answer', $answer);
            $db->bind(':answer_type', $answerType);
            $db->bind(':difficulty', $difficulty);
            $db->bind(':card_id', $cardId);
            
            if ($db->execute()) {
                $_SESSION['success_msg'] = 'Flashcard updated successfully.';
                redirect(APP_URL . '/admin/cards.php');
            } else {
                $_SESSION['error_msg'] = 'Failed to update flashcard.';
            }
        }
    } elseif ($action === 'delete_card') {
        // Delete flashcard
        $cardId = intval($_POST['card_id']);
        
        // First delete related user_responses records
        $db->query("DELETE FROM user_responses WHERE card_id = :card_id");
        $db->bind(':card_id', $cardId);
        $db->execute();
        
        // Then delete related user_progress records
        $db->query("DELETE FROM user_progress WHERE card_id = :card_id");
        $db->bind(':card_id', $cardId);
        $db->execute();
        
        // Finally delete the flashcard
        $db->query("DELETE FROM flashcards WHERE card_id = :card_id");
        $db->bind(':card_id', $cardId);
        
        if ($db->execute()) {
            $_SESSION['success_msg'] = 'Flashcard deleted successfully.';
            redirect(APP_URL . '/admin/cards.php');
        } else {
            $_SESSION['error_msg'] = 'Failed to delete flashcard.';
        }
    }
}

// Get categories for dropdown
$db->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $db->resultSet();

// Get action from URL
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle different actions
if ($action === 'create') {
    // Show create form
    $pageTitle = 'Create Flashcard';
} elseif ($action === 'edit') {
    // Get card details for editing
    $cardId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $db->query("SELECT * FROM flashcards WHERE card_id = :card_id");
    $db->bind(':card_id', $cardId);
    $card = $db->single();
    
    if (!$card) {
        $_SESSION['error_msg'] = 'Flashcard not found.';
        redirect(APP_URL . '/admin/cards.php');
    }
    
    $pageTitle = 'Edit Flashcard';
} elseif ($action === 'delete') {
    // Get card details for confirmation
    $cardId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    $db->query("SELECT * FROM flashcards WHERE card_id = :card_id");
    $db->bind(':card_id', $cardId);
    $card = $db->single();
    
    if (!$card) {
        $_SESSION['error_msg'] = 'Flashcard not found.';
        redirect(APP_URL . '/admin/cards.php');
    }
    
    $pageTitle = 'Delete Flashcard';
} else {
    // List all flashcards
    $categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
    
    if ($categoryFilter > 0) {
        $db->query("SELECT f.*, c.name as category_name 
                    FROM flashcards f 
                    JOIN categories c ON f.category_id = c.category_id
                    WHERE f.category_id = :category_id
                    ORDER BY f.created_at DESC");
        $db->bind(':category_id', $categoryFilter);
    } else {
        $db->query("SELECT f.*, c.name as category_name 
                    FROM flashcards f 
                    JOIN categories c ON f.category_id = c.category_id
                    ORDER BY f.created_at DESC");
    }
    
    $flashcards = $db->resultSet();
}

// Include admin header
include_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $pageTitle; ?></h1>
    <?php if ($action === 'list'): ?>
        <a href="?action=create" class="btn btn-primary">Create New Flashcard</a>
    <?php endif; ?>
</div>

<?php if ($action === 'list'): ?>
    <!-- Category filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-6">
                    <label for="category" class="form-label">Filter by Category:</label>
                    <select name="category" id="category" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category->category_id; ?>" <?php echo $categoryFilter === $category->category_id ? 'selected' : ''; ?>>
                                <?php echo $category->name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Flashcards list -->
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category</th>
                    <th>Question</th>
                    <th>Answer Type</th>
                    <th>Difficulty</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($flashcards)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No flashcards found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($flashcards as $flashcard): ?>
                        <tr>
                            <td><?php echo $flashcard->card_id; ?></td>
                            <td><?php echo $flashcard->category_name; ?></td>
                            <td>
                                <?php 
                                    // Truncate question if too long
                                    echo strlen($flashcard->question) > 50 ? 
                                        substr($flashcard->question, 0, 50) . '...' : 
                                        $flashcard->question; 
                                ?>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $flashcard->answer_type)); ?></td>
                            <td><?php echo $flashcard->difficulty; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($flashcard->created_at)); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="?action=edit&id=<?php echo $flashcard->card_id; ?>" class="btn btn-primary">Edit</a>
                                    <a href="?action=delete&id=<?php echo $flashcard->card_id; ?>" class="btn btn-danger">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <!-- Create/Edit form -->
    <div class="card">
        <div class="card-body">
            <form action="cards.php" method="post" class="needs-validation" novalidate>
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="action" value="update_card">
                    <input type="hidden" name="card_id" value="<?php echo $card->card_id; ?>">
                <?php else: ?>
                    <input type="hidden" name="action" value="create_card">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category:</label>
                    <select name="category_id" id="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category->category_id; ?>" <?php echo (isset($card) && $card->category_id === $category->category_id) ? 'selected' : ''; ?>>
                                <?php echo $category->name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a category.</div>
                </div>
                
                <div class="mb-3">
                    <label for="question" class="form-label">Question:</label>
                    <textarea name="question" id="question" class="form-control" rows="3" required><?php echo isset($card) ? $card->question : ''; ?></textarea>
                    <div class="invalid-feedback">Please enter a question.</div>
                </div>
                
                <div class="mb-3">
                    <label for="answer_type" class="form-label">Answer Type:</label>
                    <select name="answer_type" id="answer_type" class="form-select" required>
                        <option value="">Select Answer Type</option>
                        <option value="text" <?php echo (isset($card) && $card->answer_type === 'text') ? 'selected' : ''; ?>>Text (Explanation)</option>
                        <option value="key_points" <?php echo (isset($card) && $card->answer_type === 'key_points') ? 'selected' : ''; ?>>Key Points</option>
                        <option value="mathematical" <?php echo (isset($card) && $card->answer_type === 'mathematical') ? 'selected' : ''; ?>>Mathematical</option>
                        <option value="definition" <?php echo (isset($card) && $card->answer_type === 'definition') ? 'selected' : ''; ?>>Definition</option>
                    </select>
                    <div class="invalid-feedback">Please select an answer type.</div>
                    <small id="answerTypeHelp" class="form-text text-muted">This determines how the AI will evaluate student responses.</small>
                </div>
                
                <div class="mb-3">
                    <label for="answer" class="form-label">Correct Answer:</label>
                    <textarea name="answer" id="answer" class="form-control" rows="5" required><?php echo isset($card) ? $card->answer : ''; ?></textarea>
                    <div class="invalid-feedback">Please enter the correct answer.</div>
                    <small id="answerHelp" class="form-text text-muted">The ideal answer that will be used to evaluate student responses.</small>
                </div>
                
                <div class="mb-3">
                    <label for="difficulty" class="form-label">Difficulty (1-5):</label>
                    <input type="number" name="difficulty" id="difficulty" class="form-control" min="1" max="5" value="<?php echo isset($card) ? $card->difficulty : '1'; ?>" required>
                    <div class="invalid-feedback">Please enter a difficulty level between 1 and 5.</div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="cards.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'Update' : 'Create'; ?> Flashcard</button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($action === 'delete'): ?>
    <!-- Delete confirmation -->
    <div class="card">
        <div class="card-body">
            <p>Are you sure you want to delete this flashcard?</p>
            
            <div class="alert alert-warning">
                <strong>Question:</strong> <?php echo $card->question; ?>
            </div>
            
            <form action="cards.php" method="post">
                <input type="hidden" name="action" value="delete_card">
                <input type="hidden" name="card_id" value="<?php echo $card->card_id; ?>">
                
                <div class="d-flex justify-content-between">
                    <a href="cards.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">Delete Flashcard</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
// Include admin footer
include_once 'includes/footer.php';
?>