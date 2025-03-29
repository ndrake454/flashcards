<?php
// Set page title
$pageTitle = 'Reset Progress';

// Include required files
require_once 'config/config.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isLoggedIn()) {
    $_SESSION['error_msg'] = 'Please log in to access this page.';
    redirect(APP_URL . '/login.php');
}

// Initialize database
$db = new Database();

// Get user ID
$userId = $_SESSION['user_id'];

// Check if this is a category reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_category_reset']) && isset($_POST['category_id'])) {
    
    $categoryId = intval($_POST['category_id']);
    
    // Validate category ID
    if ($categoryId <= 0) {
        $_SESSION['error_msg'] = 'Invalid category selected.';
        redirect(APP_URL . '/profile.php');
    }
    
    // Get category name for message
    $db->query("SELECT name FROM categories WHERE category_id = :category_id");
    $db->bind(':category_id', $categoryId);
    $category = $db->single();
    
    if (!$category) {
        $_SESSION['error_msg'] = 'Category not found.';
        redirect(APP_URL . '/profile.php');
    }
    
    // Begin transaction
    $db->query("START TRANSACTION");
    
    try {
        // Delete user's progress for this category
        $db->query("DELETE FROM user_progress 
                    WHERE user_id = :user_id 
                    AND card_id IN (
                        SELECT card_id FROM flashcards WHERE category_id = :category_id
                    )");
        $db->bind(':user_id', $userId);
        $db->bind(':category_id', $categoryId);
        $db->execute();
        
        // Delete user's responses for this category
        $db->query("DELETE FROM user_responses 
                    WHERE user_id = :user_id 
                    AND card_id IN (
                        SELECT card_id FROM flashcards WHERE category_id = :category_id
                    )");
        $db->bind(':user_id', $userId);
        $db->bind(':category_id', $categoryId);
        $db->execute();
        
        // Clear related session counters
        if (isset($_SESSION['additional_review_counter'][$categoryId])) {
            unset($_SESSION['additional_review_counter'][$categoryId]);
        }
        
        // Commit transaction
        $db->query("COMMIT");
        
        // Set success message
        $_SESSION['success_msg'] = 'Your progress for the category "' . htmlspecialchars($category->name) . '" has been reset successfully.';
        
    } catch (Exception $e) {
        // Rollback on error
        $db->query("ROLLBACK");
        logActivity('Category progress reset error: ' . $e->getMessage(), 'error');
        $_SESSION['error_msg'] = 'An error occurred while resetting category progress. Please try again.';
    }
    
    // Redirect back
    redirect(APP_URL . '/profile.php');
}
// Check if this is a full profile reset
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    
    // Begin transaction
    $db->query("START TRANSACTION");
    
    try {
        // Delete all user's progress
        $db->query("DELETE FROM user_progress WHERE user_id = :user_id");
        $db->bind(':user_id', $userId);
        $db->execute();
        
        // Delete all user's responses
        $db->query("DELETE FROM user_responses WHERE user_id = :user_id");
        $db->bind(':user_id', $userId);
        $db->execute();
        
        // Clear all additional session counters
        if (isset($_SESSION['additional_review_counter'])) {
            unset($_SESSION['additional_review_counter']);
        }
        
        // Commit transaction
        $db->query("COMMIT");
        
        // Set success message
        $_SESSION['success_msg'] = 'All your progress has been successfully reset. You can now start fresh!';
        
    } catch (Exception $e) {
        // Rollback on error
        $db->query("ROLLBACK");
        logActivity('Full progress reset error: ' . $e->getMessage(), 'error');
        $_SESSION['error_msg'] = 'An error occurred while resetting your progress. Please try again.';
    }
    
    // Redirect back
    redirect(APP_URL . '/profile.php');
} else {
    // If someone tries to access this page directly, redirect to profile
    redirect(APP_URL . '/profile.php');
}
?>