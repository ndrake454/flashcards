<?php
// Set page title
$pageTitle = 'Register';

// Include header
include_once 'includes/header.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL);
}

// Initialize database
$db = new Database();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password']; // Will be hashed
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check if username already exists
    $db->query("SELECT * FROM users WHERE username = :username");
    $db->bind(':username', $username);
    if ($db->rowCount() > 0) {
        $errors[] = 'Username already exists.';
    }
    
    // Check if email already exists
    $db->query("SELECT * FROM users WHERE email = :email");
    $db->bind(':email', $email);
    if ($db->rowCount() > 0) {
        $errors[] = 'Email already exists.';
    }
    
    // If there are errors, save them to session
    if (!empty($errors)) {
        $_SESSION['error_msg'] = implode('<br>', $errors);
    } else {
        // Hash password
        $hashedPassword = hashPassword($password);
        
        // Insert user into database
        $db->query("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
        $db->bind(':username', $username);
        $db->bind(':email', $email);
        $db->bind(':password', $hashedPassword);
        
        if ($db->execute()) {
            // Registration successful
            $_SESSION['success_msg'] = 'Registration successful! You can now login.';
            redirect(APP_URL . '/login.php');
        } else {
            $_SESSION['error_msg'] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h1 class="h4 mb-0">Register</h1>
            </div>
            <div class="card-body">
                <form action="register.php" method="post" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <div class="invalid-feedback">
                            Please choose a username (3-50 characters).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <div class="invalid-feedback">
                            Please enter a valid email.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        <div class="invalid-feedback">
                            Password must be at least 6 characters.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">
                            Please confirm your password.
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>