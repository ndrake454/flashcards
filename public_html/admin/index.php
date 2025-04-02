<?php
// Set page title
$pageTitle = 'Admin Dashboard';

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

// Get stats for dashboard
// Total users
$db->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $db->single()->total;

// Total cards
$db->query("SELECT COUNT(*) as total FROM flashcards");
$totalCards = $db->single()->total;

// Total categories
$db->query("SELECT COUNT(*) as total FROM categories");
$totalCategories = $db->single()->total;

// Total responses
$db->query("SELECT COUNT(*) as total FROM user_responses");
$totalResponses = $db->single()->total;

// Understanding rate
$db->query("SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN understood = 1 THEN 1 ELSE 0 END) as understood 
            FROM user_responses");
$responseStats = $db->single();
$understandingRate = $responseStats->total > 0 ? 
    round(($responseStats->understood / $responseStats->total) * 100) : 0;

// Recent users
$db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $db->resultSet();

// Recent responses
$db->query("SELECT ur.*, u.username, f.question
            FROM user_responses ur
            JOIN users u ON ur.user_id = u.user_id
            JOIN flashcards f ON ur.card_id = f.card_id
            ORDER BY ur.created_at DESC
            LIMIT 10");
$recentResponses = $db->resultSet();

// Most used categories
$db->query("SELECT c.name, COUNT(ur.response_id) as response_count
            FROM categories c
            JOIN flashcards f ON c.category_id = f.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            GROUP BY c.category_id
            ORDER BY response_count DESC
            LIMIT 5");
$popularCategories = $db->resultSet();

// Include admin header
include_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $pageTitle; ?></h1>
    <span class="text-muted">Welcome, <?php echo $_SESSION['username']; ?></span>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Users</h5>
                <p class="display-4"><?php echo $totalUsers; ?></p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <span>Manage Users</span>
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Flashcards</h5>
                <p class="display-4"><?php echo $totalCards; ?></p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="cards.php" class="text-white text-decoration-none">Manage Cards</a>
                <i class="bi bi-card-text"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h5 class="card-title">Total Categories</h5>
                <p class="display-4"><?php echo $totalCategories; ?></p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="../categories.php" class="text-white text-decoration-none">Manage Categories</a>
                <i class="bi bi-folder2-open"></i>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning h-100">
            <div class="card-body">
                <h5 class="card-title">Understanding Rate</h5>
                <p class="display-4"><?php echo $understandingRate; ?>%</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <span>Based on <?php echo $totalResponses; ?> responses</span>
                <i class="bi bi-graph-up"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Users -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Recent Users</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user->username); ?></td>
                            <td><?php echo htmlspecialchars($user->email); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user->created_at)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentUsers)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No users found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Popular Categories -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Most Used Categories</h5>
            </div>
            <div class="card-body">
                <?php if (empty($popularCategories)): ?>
                    <p class="text-center">No data available.</p>
                <?php else: ?>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('categoryChart').getContext('2d');
                            const categoryChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: [
                                        <?php foreach ($popularCategories as $category): ?>
                                        "<?php echo htmlspecialchars($category->name); ?>",
                                        <?php endforeach; ?>
                                    ],
                                    datasets: [{
                                        label: 'Responses',
                                        data: [
                                            <?php foreach ($popularCategories as $category): ?>
                                            <?php echo $category->response_count; ?>,
                                            <?php endforeach; ?>
                                        ],
                                        backgroundColor: [
                                            'rgba(75, 192, 192, 0.2)',
                                            'rgba(54, 162, 235, 0.2)',
                                            'rgba(153, 102, 255, 0.2)',
                                            'rgba(255, 159, 64, 0.2)',
                                            'rgba(255, 99, 132, 0.2)'
                                        ],
                                        borderColor: [
                                            'rgba(75, 192, 192, 1)',
                                            'rgba(54, 162, 235, 1)',
                                            'rgba(153, 102, 255, 1)',
                                            'rgba(255, 159, 64, 1)',
                                            'rgba(255, 99, 132, 1)'
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Responses -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Recent Responses</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Question</th>
                        <th>Understood</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentResponses as $response): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($response->username); ?></td>
                        <td>
                            <?php 
                                // Truncate question if too long
                                echo strlen($response->question) > 50 ? 
                                    htmlspecialchars(substr($response->question, 0, 50) . '...') : 
                                    htmlspecialchars($response->question); 
                            ?>
                        </td>
                        <td>
                            <?php echo $response->understood ? 
                                '<span class="badge bg-success">Yes</span>' : 
                                '<span class="badge bg-warning text-dark">No</span>'; 
                            ?>
                        </td>
                        <td><?php echo date('M j, g:i a', strtotime($response->created_at)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($recentResponses)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No responses found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Include admin footer
include_once 'includes/footer.php';
?>