<?php
/**
 * API endpoint for flashcard operations
 */

// Start session and include required files
session_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Initialize database
$db = new Database();

// Get action from query parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
if ($action === 'get_next_card') {
    // Get category ID from query parameter
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    
    // Get question type preference if specified
    $questionType = isset($_GET['question_type']) ? sanitize($_GET['question_type']) : 'all';
    
    // Validate category ID
    if ($categoryId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid category ID'
        ]);
        exit;
    }
    
    // Get user ID
    $userId = $_SESSION['user_id'];
    
    // Get study mode from query parameter (review mode is optional)
    $reviewMode = isset($_GET['review_mode']) && $_GET['review_mode'] === 'true';
    
    // Build the query based on question type preference
    $questionTypeCondition = "";
    if ($questionType === 'multiple_choice') {
        $questionTypeCondition = "AND f.answer_type = 'multiple_choice'";
    } elseif ($questionType === 'text') {
        $questionTypeCondition = "AND f.answer_type != 'multiple_choice'";
    }

    // First check if there are any cards of the requested type
    $db->query("SELECT COUNT(*) as count FROM flashcards f 
                WHERE f.category_id = :category_id 
                $questionTypeCondition");
    $db->bind(':category_id', $categoryId);
    $cardCount = $db->single()->count;

    if ($cardCount == 0) {
        // No cards of requested type exist at all
        $typeMessage = $questionType === 'multiple_choice' 
            ? 'No multiple choice questions available in this category. Try creating some in the admin panel!' 
            : ($questionType === 'text' 
                ? 'No short answer questions available in this category.' 
                : 'No flashcards found in this category.');
        
        echo json_encode([
            'success' => false,
            'message' => $typeMessage,
            'review_available' => false
        ]);
        exit;
    }
    
    if ($reviewMode) {
        // In review mode, get any card from the category without review date restrictions
        // We'll prioritize cards that haven't been seen in a while
        $db->query("SELECT f.card_id, f.question, f.answer, f.answer_type, f.difficulty,
                    up.progress_id, up.next_review, up.`interval`, up.ease_factor, up.times_reviewed
                    FROM flashcards f
                    LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                    WHERE f.category_id = :category_id
                    $questionTypeCondition
                    ORDER BY 
                        CASE WHEN up.next_review IS NULL THEN 0 ELSE 1 END, -- New cards first
                        up.next_review ASC, -- Then by due date
                        RAND() -- Add randomness
                    LIMIT 1");
        $db->bind(':user_id', $userId);
        $db->bind(':category_id', $categoryId);
    } else {
        // Normal mode - get cards that are due for review
        $db->query("SELECT f.card_id, f.question, f.answer, f.answer_type, f.difficulty,
                    up.progress_id, up.next_review, up.`interval`, up.ease_factor, up.times_reviewed
                    FROM flashcards f
                    LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                    WHERE f.category_id = :category_id
                    $questionTypeCondition
                    AND (up.next_review IS NULL OR up.next_review <= NOW())
                    ORDER BY 
                        CASE WHEN up.next_review IS NULL THEN 0 ELSE 1 END, -- New cards first
                        up.next_review ASC -- Then by due date
                    LIMIT 1");
        $db->bind(':user_id', $userId);
        $db->bind(':category_id', $categoryId);
    }
    
    $card = $db->single();
    
    if (!$card) {
        // Cards exist but none are due - we already checked if cards exist above
        echo json_encode([
            'success' => false,
            'message' => 'Great job! You\'ve completed all due flashcards in this category.',
            'review_available' => true,
            'category_id' => $categoryId
        ]);
        exit;
    }
    
    // Process the card based on its answer type
    // Debug: Log the card details before processing
    error_log("Card before processing: " . json_encode($card));
    
    // ONLY apply variations to multiple-choice questions as requested
    if ($card->answer_type === 'multiple_choice') {
        // Parse the multiple choice data
        $answerData = json_decode($card->answer, true);
        error_log("Multiple choice data: " . json_encode($answerData));
        
        if ($answerData && isset($answerData['correct']) && isset($answerData['options'])) {
            // Store the original data for reference
            $card->original_answer = $card->answer;
            
            // Create a varied version of the question
            $card->original_question = $card->question;
            $card->question = varyQuestionText($card->question);
            
            // Create varied versions of the options
            $originalOptions = $answerData['options'];
            $variedOptions = varyMultipleChoiceOptions($originalOptions);
            
            // Update the correct answer to match its varied version
            $correctIndex = array_search($answerData['correct'], $originalOptions);
            if ($correctIndex !== false && isset($variedOptions[$correctIndex])) {
                $answerData['correct'] = $variedOptions[$correctIndex];
                $answerData['options'] = $variedOptions;
                $card->answer = json_encode($answerData);
            }
        }
    }
    // Don't vary the text-based questions at all
    
    // Get progress stats for the UI
    $db->query("SELECT COUNT(*) as total FROM flashcards WHERE category_id = :category_id $questionTypeCondition");
    $db->bind(':category_id', $categoryId);
    $totalCards = $db->single()->total;
    
    // Get cards completed this session
    $sessionKey = 'studied_cards_' . $categoryId . '_' . $questionType;
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [];
    }
    
    // Add this card to the session studied list if not already there
    if (!in_array($card->card_id, $_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey][] = $card->card_id;
    }
    
    $completedCount = count($_SESSION[$sessionKey]);
    $progressPercent = ($completedCount / $totalCards) * 100;
    
    // Add progress information to the response
    $card->progress = [
        'total' => $totalCards,
        'completed' => $completedCount,
        'percent' => $progressPercent
    ];
    
    // Return the card
    echo json_encode([
        'success' => true,
        'card' => $card
    ]);
    
} elseif ($action === 'get_card') {
    // Get card ID from query parameter
    $cardId = isset($_GET['card_id']) ? intval($_GET['card_id']) : 0;
    
    // Validate card ID
    if ($cardId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid card ID'
        ]);
        exit;
    }
    
    // Get card details
    $db->query("SELECT f.*, c.name as category_name 
                FROM flashcards f 
                JOIN categories c ON f.category_id = c.category_id
                WHERE f.card_id = :card_id");
    $db->bind(':card_id', $cardId);
    $card = $db->single();
    
    if (!$card) {
        echo json_encode([
            'success' => false,
            'message' => 'Card not found'
        ]);
        exit;
    }
    
    // Return the card
    echo json_encode([
        'success' => true,
        'card' => $card
    ]);
    
} else {
    // Invalid action
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}
?>