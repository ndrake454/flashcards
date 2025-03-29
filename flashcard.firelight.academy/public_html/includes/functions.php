<?php
// Get the document root path
$docRoot = $_SERVER['DOCUMENT_ROOT'];
require_once __DIR__ . '/../config/db.php';

/**
 * Clean and sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Hash a password
 */
function hashPassword($password) {
    return password_hash($password . SALT, PASSWORD_BCRYPT);
}

/**
 * Verify a password
 */
function verifyPassword($password, $hash) {
    return password_verify($password . SALT, $hash);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * Display error message
 */
function displayError($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

/**
 * Display success message
 */
function displaySuccess($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

/**
 * Log activity for debugging
 */
function logActivity($message, $type = 'info') {
    if (DEBUG_MODE) {
        $logFile = '../logs/' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp][$type]: $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}

/**
 * Call the AI API for response evaluation
 */
function evaluateResponse($question, $correctAnswer, $userResponse, $answerType) {
    // Create API request to AI service
    $prompt = constructPrompt($question, $correctAnswer, $userResponse, $answerType);
    $response = callAIAPI($prompt);
    
    return $response;
}

/**
 * Construct the prompt for the Claude API
 */
function constructPrompt($question, $correctAnswer, $userResponse, $answerType) {
    $prompt = "You are evaluating a student's response to a flashcard question.\n\n";
    $prompt .= "Question: $question\n\n";
    $prompt .= "Correct Answer: $correctAnswer\n\n";
    $prompt .= "Student's Response: $userResponse\n\n";
    $prompt .= "Answer Type: $answerType\n\n";
    $prompt .= "Please evaluate whether the student understands the concept. Respond with JSON in this format:\n";
    $prompt .= "{\n";
    $prompt .= "  \"understood\": true/false,\n";
    $prompt .= "  \"feedback\": \"Your constructive feedback here\",\n";
    $prompt .= "  \"missing_points\": [\"key point 1\", \"key point 2\"]\n";
    $prompt .= "}\n";
    
    return $prompt;
}

/**
 * Call the Claude API
 */
function callAIAPI($prompt) {
    $curl = curl_init();
    
    $postFields = array(
        "model" => AI_MODEL,
        "max_tokens" => 1024,
        "messages" => array(
            array(
                "role" => "user",
                "content" => $prompt
            )
        ),
        "system" => "You are an educational assistant that evaluates student responses to flashcards. Return your evaluation in JSON format with fields: understood (boolean), feedback (string), and missing_points (array of strings)."
    );
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => AI_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postFields),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'x-api-key: ' . AI_API_KEY,
            'anthropic-version: 2023-06-01'
        ),
    ));
    
    // For debugging
    error_log("Sending request to Claude API: " . json_encode($postFields));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    // Log response for debugging
    error_log("Claude API response code: " . $httpCode);
    error_log("Claude API response: " . $response);
    if ($err) {
        error_log("Claude API error: " . $err);
    }
    
    curl_close($curl);
    
    if ($err) {
        logActivity("Claude API Error: " . $err, 'error');
        return array(
            "understood" => null,
            "feedback" => "Error communicating with AI: " . $err,
            "missing_points" => []
        );
    }
    
    // Check for HTTP error
    if ($httpCode != 200) {
        logActivity("Claude API HTTP Error: " . $httpCode . " - " . $response, 'error');
        return array(
            "understood" => null,
            "feedback" => "Error communicating with AI service (HTTP " . $httpCode . "). Please try again later.",
            "missing_points" => []
        );
    }
    
    $decodedResponse = json_decode($response, true);
    
    // Extract the content from the response
    if (isset($decodedResponse['content']) && isset($decodedResponse['content'][0]['text'])) {
        $aiResponseContent = $decodedResponse['content'][0]['text'];
        
        // Try to extract JSON from the response
        preg_match('/\{.*\}/s', $aiResponseContent, $matches);
        
        if (!empty($matches)) {
            $jsonStr = $matches[0];
            $parsedResponse = json_decode($jsonStr, true);
            
            if ($parsedResponse && isset($parsedResponse['understood'])) {
                return $parsedResponse;
            }
        }
        
        // Fallback: If we couldn't extract JSON, try to create a simple response based on the text
        logActivity("Could not parse JSON from Claude response: " . $aiResponseContent, 'warning');
        
        // Check if the response contains words that suggest understanding
        $positiveIndicators = ['correct', 'good', 'excellent', 'right', 'well done', 'understand'];
        $understood = false;
        
        foreach ($positiveIndicators as $indicator) {
            if (stripos($aiResponseContent, $indicator) !== false) {
                $understood = true;
                break;
            }
        }
        
        return array(
            "understood" => $understood,
            "feedback" => substr($aiResponseContent, 0, 500), // Limit feedback length
            "missing_points" => []
        );
    }
    
    logActivity("Unexpected Claude API response format: " . $response, 'error');
    return array(
        "understood" => null,
        "feedback" => "Error processing AI response. Please try again.",
        "missing_points" => []
    );
}

/**
 * Fallback evaluation function if API fails
 * Will be used as a backup when the main AI evaluation fails
 */
function fallbackEvaluateResponse($question, $correctAnswer, $userResponse, $answerType) {
    // Simple matching algorithm
    $correctAnswer = strtolower($correctAnswer);
    $userResponse = strtolower($userResponse);
    
    // Extract important words from the correct answer
    $keyWords = array_filter(
        explode(' ', preg_replace('/[^\w\s]/', '', $correctAnswer)),
        function($word) { return strlen($word) > 3; }
    );
    
    // Count matching words
    $matchCount = 0;
    $missingPoints = [];
    foreach ($keyWords as $word) {
        if (strpos($userResponse, $word) !== false) {
            $matchCount++;
        } else {
            // Only add important words as missing points
            if (strlen($word) > 4 && !in_array($word, ['with', 'that', 'this', 'from', 'there', 'their', 'have', 'what'])) {
                $missingPoints[] = "Your answer should include the concept: '$word'";
            }
        }
    }
    
    // Calculate understanding percentage
    $keyWordCount = count($keyWords);
    $matchPercentage = $keyWordCount > 0 ? ($matchCount / $keyWordCount) * 100 : 0;
    
    // Determine understanding based on percentage
    $understood = $matchPercentage >= 60;
    
    if ($matchPercentage >= 80) {
        $feedback = "Excellent! Your answer demonstrates a solid understanding of the concept.";
    } elseif ($matchPercentage >= 60) {
        $feedback = "Good job! Your answer covers most key points about this concept.";
    } elseif ($matchPercentage >= 30) {
        $feedback = "You're on the right track, but missing some important concepts. Review the key points below.";
    } else {
        $feedback = "Your answer doesn't address many key concepts. Please review this material again.";
    }
    
    // Limit missing points to 3
    $missingPoints = array_slice($missingPoints, 0, 3);
    
    return array(
        "understood" => $understood,
        "feedback" => $feedback,
        "missing_points" => $missingPoints
    );
}
?>