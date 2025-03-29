<?php
/**
 * API endpoint for tracking user progress and evaluating responses
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

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle POST requests (submit response)
if ($method === 'POST') {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if action is specified
    if (!isset($data['action']) || $data['action'] !== 'submit_response') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        exit;
    }
    
    // Get card ID, user response, and response time
    $cardId = isset($data['card_id']) ? intval($data['card_id']) : 0;
    $response = isset($data['response']) ? $data['response'] : '';
    $responseTime = isset($data['response_time']) ? intval($data['response_time']) : 0;
    
    // Validate input
    if ($cardId <= 0 || empty($response)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input data'
        ]);
        exit;
    }
    
    // Get user ID
    $userId = $_SESSION['user_id'];
    
    // Get card details
    $db->query("SELECT * FROM flashcards WHERE card_id = :card_id");
    $db->bind(':card_id', $cardId);
    $card = $db->single();
    
    if (!$card) {
        echo json_encode([
            'success' => false,
            'message' => 'Card not found'
        ]);
        exit;
    }
    
    // Evaluate the response using AI
    $evaluation = evaluateResponse($card->question, $card->answer, $response, $card->answer_type);
    
    // Store the response and evaluation in the database
    $db->query("INSERT INTO user_responses (user_id, card_id, user_response, ai_evaluation, understood, response_time)
                VALUES (:user_id, :card_id, :user_response, :ai_evaluation, :understood, :response_time)");
    $db->bind(':user_id', $userId);
    $db->bind(':card_id', $cardId);
    $db->bind(':user_response', $response);
    $db->bind(':ai_evaluation', json_encode($evaluation));
    $db->bind(':understood', $evaluation['understood'] ? 1 : 0);
    $db->bind(':response_time', $responseTime);
    
    if (!$db->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to store response'
        ]);
        exit;
    }
    
    // Update user progress based on response correctness
    // Check if a progress record already exists
    $db->query("SELECT * FROM user_progress WHERE user_id = :user_id AND card_id = :card_id");
    $db->bind(':user_id', $userId);
    $db->bind(':card_id', $cardId);
    $progress = $db->single();
    
    // Calculate new interval and ease factor based on SM-2 algorithm
    // (A simplified version of the Anki spaced repetition algorithm)
    
    // Default values
    $interval = 1; // Days until next review
    $easeFactor = 2.5; // Initial ease factor
    $timesReviewed = 1;
    $timesCorrect = $evaluation['understood'] ? 1 : 0;
    
    if ($progress) {
        // Update existing progress
        $timesReviewed = $progress->times_reviewed + 1;
        $timesCorrect = $progress->times_correct + ($evaluation['understood'] ? 1 : 0);
        $easeFactor = $progress->ease_factor;
        
        // Adjust ease factor based on understanding
        if ($evaluation['understood']) {
            $easeFactor = max(1.3, $easeFactor + 0.1);
            
            // Calculate new interval
            if ($progress->interval === 1) {
                $interval = 6; // First successful review: 6 days
            } else {
                $interval = ceil($progress->interval * $easeFactor);
            }
        } else {
            // If not understood, reduce ease factor and reset interval
            $easeFactor = max(1.3, $easeFactor - 0.2);
            $interval = 1; // Back to 1 day
        }
        
        // Calculate next review date
        $nextReview = date('Y-m-d H:i:s', strtotime("+$interval days"));
        
        // Update progress
        $db->query("UPDATE user_progress SET 
                    last_reviewed = NOW(), 
                    next_review = :next_review, 
                    ease_factor = :ease_factor, 
                    `interval` = :interval, 
                    times_reviewed = :times_reviewed, 
                    times_correct = :times_correct
                    WHERE progress_id = :progress_id");
        $db->bind(':next_review', $nextReview);
        $db->bind(':ease_factor', $easeFactor);
        $db->bind(':interval', $interval);
        $db->bind(':times_reviewed', $timesReviewed);
        $db->bind(':times_correct', $timesCorrect);
        $db->bind(':progress_id', $progress->progress_id);
    } else {
        // Create new progress record
        // Calculate next review date based on understanding
        if ($evaluation['understood']) {
            $interval = 1; // First review: 1 day if understood
        } else {
            $interval = 0; // Same day if not understood
        }
        
        $nextReview = date('Y-m-d H:i:s', strtotime("+$interval days"));
        
        $db->query("INSERT INTO user_progress (user_id, card_id, last_reviewed, next_review, ease_factor, `interval`, times_reviewed, times_correct)
                    VALUES (:user_id, :card_id, NOW(), :next_review, :ease_factor, :interval, :times_reviewed, :times_correct)");
        $db->bind(':user_id', $userId);
        $db->bind(':card_id', $cardId);
        $db->bind(':next_review', $nextReview);
        $db->bind(':ease_factor', $easeFactor);
        $db->bind(':interval', $interval);
        $db->bind(':times_reviewed', $timesReviewed);
        $db->bind(':times_correct', $timesCorrect);
    }
    
    if (!$db->execute()) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update progress'
        ]);
        exit;
    }
    
    // Determine recall message based on understanding and interval
    $recallMessage = '';
    if ($evaluation['understood']) {
        if ($interval > 7) {
            $recallMessage = "You seem to understand this concept well. We won't show you this card again for a while.";
        } else {
            $recallMessage = "Good job! You'll see this card again soon to reinforce your memory.";
        }
    } else {
        $recallMessage = "You'll see this card again soon to help you learn this concept.";
    }
    
    // Prepare the correct answer to return
    $correctAnswer = $card->answer;
    
    // Return success response with evaluation
    echo json_encode([
        'success' => true,
        'evaluation' => $evaluation,
        'correct_answer' => $correctAnswer,
        'recall_message' => $recallMessage,
        'understood' => $evaluation['understood']
    ]);
    
} elseif ($method === 'GET') {
    // Get action from query parameter
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    if ($action === 'get_progress') {
        // Get user ID
        $userId = $_SESSION['user_id'];
        
        // Get category ID if specified
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        
        // Get user progress
        if ($categoryId > 0) {
            // Get progress for specific category
            $db->query("SELECT c.name as category_name, 
                        COUNT(f.card_id) as total_cards,
                        COUNT(up.progress_id) as reviewed_cards,
                        SUM(CASE WHEN up.times_correct > 0 THEN 1 ELSE 0 END) as correct_cards
                        FROM categories c
                        JOIN flashcards f ON c.category_id = f.category_id
                        LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                        WHERE c.category_id = :category_id
                        GROUP BY c.category_id");
            $db->bind(':user_id', $userId);
            $db->bind(':category_id', $categoryId);
            $progress = $db->single();
            
            if (!$progress) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Category not found or no progress data available'
                ]);
                exit;
            }
            
            // Get due cards count
            $db->query("SELECT COUNT(*) as due_cards
                        FROM flashcards f
                        LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                        WHERE f.category_id = :category_id
                        AND (up.next_review IS NULL OR up.next_review <= NOW())");
            $db->bind(':user_id', $userId);
            $db->bind(':category_id', $categoryId);
            $dueCards = $db->single()->due_cards;
            
            $progress->due_cards = $dueCards;
            
            // Return category progress
            echo json_encode([
                'success' => true,
                'progress' => $progress
            ]);
            
        } else {
            // Get progress for all categories
            $db->query("SELECT c.category_id, c.name as category_name, 
                        COUNT(f.card_id) as total_cards,
                        COUNT(up.progress_id) as reviewed_cards,
                        SUM(CASE WHEN up.times_correct > 0 THEN 1 ELSE 0 END) as correct_cards
                        FROM categories c
                        JOIN flashcards f ON c.category_id = f.category_id
                        LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                        GROUP BY c.category_id
                        ORDER BY c.name ASC");
            $db->bind(':user_id', $userId);
            $categories = $db->resultSet();
            
            // Get due cards count for each category
            foreach ($categories as $category) {
                $db->query("SELECT COUNT(*) as due_cards
                            FROM flashcards f
                            LEFT JOIN user_progress up ON f.card_id = up.card_id AND up.user_id = :user_id
                            WHERE f.category_id = :category_id
                            AND (up.next_review IS NULL OR up.next_review <= NOW())");
                $db->bind(':user_id', $userId);
                $db->bind(':category_id', $category->category_id);
                $category->due_cards = $db->single()->due_cards;
            }
            
            // Return all categories progress
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
        }
        
    } else {
        // Invalid action
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
    }
    
} else {
    // Invalid request method
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>