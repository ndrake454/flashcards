<?php
// Include config files with absolute paths
require_once __DIR__ . '/../../config/config.php';

// Check if session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    // Redirect to home page
    header('Location: ' . APP_URL);
    exit;
}

// Get current page for active menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME . ' Admin' : APP_NAME . ' Admin'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/admin/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper d-flex">
        <!-- Sidebar -->
        <nav id="sidebar" class="bg-dark text-white">
            <div class="sidebar-header">
                <h3><?php echo APP_NAME; ?></h3>
                <p class="text-muted">Admin Panel</p>
            </div>
            
            <ul class="list-unstyled components">
                <li class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/admin/index.php">
                        <i class="bi bi-speedometer2 me-2"></i> Dashboard
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'cards.php' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/admin/cards.php">
                        <i class="bi bi-card-text me-2"></i> Flashcards
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'categories.php' || basename(dirname($_SERVER['PHP_SELF'])) === 'categories.php' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/categories.php">
                        <i class="bi bi-folder2 me-2"></i> Categories
                    </a>
                </li>
                <li class="<?php echo $currentPage === 'stats.php' ? 'active' : ''; ?>">
                    <a href="<?php echo APP_URL; ?>/admin/stats.php">
                        <i class="bi bi-graph-up me-2"></i> Statistics
                    </a>
                </li>
                <li>
                    <a href="<?php echo APP_URL; ?>">
                        <i class="bi bi-house me-2"></i> Main Site
                    </a>
                </li>
                <li>
                    <a href="<?php echo APP_URL; ?>/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Page Content -->
        <div id="content" class="flex-grow-1">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-dark">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <div class="ms-auto d-flex">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> <?php echo $_SESSION['username']; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo APP_URL; ?>/logout.php">Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <div class="container-fluid p-4">
                <?php
                // Display flash messages if any
                if(isset($_SESSION['success_msg'])) {
                    echo '<div class="alert alert-success">' . $_SESSION['success_msg'] . '</div>';
                    unset($_SESSION['success_msg']);
                }
                
                if(isset($_SESSION['error_msg'])) {
                    echo '<div class="alert alert-danger">' . $_SESSION['error_msg'] . '</div>';
                    unset($_SESSION['error_msg']);
                }
                ?>