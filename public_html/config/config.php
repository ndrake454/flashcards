<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', '-');
define('DB_PASS', '-');
define('DB_NAME', '-');

// API Configuration for Claude
define('AI_API_URL', 'https://api.anthropic.com/v1/messages');
define('AI_API_KEY', '-'); // Replace with your actual API key
define('AI_MODEL', 'claude-3-haiku-20240307'); // Lowest cost option, still very capable

// Application settings
define('APP_NAME', 'Flashcards');
define('APP_URL', 'https://flashcard.firelight.academy');
define('DEBUG_MODE', true); // Set to false in production

// Security
define('SALT', 'v9F2s!a$B7#pLt3x@M1e'); // For additional password security
?>