<?php
// Set page title
$pageTitle = 'Login';

// Include header
include_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    echo "<script>window.location.href = '" . APP_URL . "/index.php';</script>";
    exit;
}

// Initialize database
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $password = $_POST['password']; // Will be sanitized with password_verify
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error_msg'] = 'Please fill in all fields.';
    } else {
        // Check if username exists
        $db->query("SELECT * FROM users WHERE username = :username OR email = :email");
        $db->bind(':username', $username);
        $db->bind(':email', $username); // Allow login with email too
        $user = $db->single();
        
        if ($user && verifyPassword($password, $user->password)) {
            // Password is correct, create session
            $_SESSION['user_id'] = $user->user_id;
            $_SESSION['username'] = $user->username;
            $_SESSION['is_admin'] = (bool)$user->is_admin;
            
            // Ensure session data is saved
            session_write_close();
            
            // Redirect to home page or requested page
            $redirect = isset($_SESSION['redirect_after_login']) ? 
                $_SESSION['redirect_after_login'] : APP_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            
            // Use JavaScript redirect
            echo "<script>window.location.href = '" . $redirect . "';</script>";
            exit;
        } else {
            $_SESSION['error_msg'] = 'Invalid username or password.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h1 class="h4 mb-0">Login</h1>
            </div>
            <div class="card-body">
                <form action="login.php" method="post" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username or Email</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="invalid-feedback">
                            Please enter your username or email.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="invalid-feedback">
                            Please enter your password.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>