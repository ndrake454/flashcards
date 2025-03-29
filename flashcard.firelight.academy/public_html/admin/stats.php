<?php
// Set page title
$pageTitle = 'Statistics & Analytics';

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

// Get date range for filtering
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Handle filter submission
$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Get all categories for filter dropdown
$db->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $db->resultSet();

// Get system-wide stats
// Total responses in date range
$db->query("SELECT COUNT(*) as total FROM user_responses 
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$totalResponses = $db->single()->total;

// Understanding rate in date range
$db->query("SELECT 
            COUNT(*) as total, 
            SUM(CASE WHEN understood = 1 THEN 1 ELSE 0 END) as understood 
            FROM user_responses
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$responseStats = $db->single();
$understandingRate = $responseStats->total > 0 ? 
    round(($responseStats->understood / $responseStats->total) * 100) : 0;

// Average response time in seconds
$db->query("SELECT AVG(response_time) as avg_time FROM user_responses
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$avgResponseTime = round($db->single()->avg_time);

// Active users in date range
$db->query("SELECT COUNT(DISTINCT user_id) as active_users FROM user_responses
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$activeUsers = $db->single()->active_users;

// Get daily stats for chart
$db->query("SELECT DATE(created_at) as date, 
            COUNT(*) as responses,
            SUM(CASE WHEN understood = 1 THEN 1 ELSE 0 END) as understood
            FROM user_responses
            WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
$dailyStats = $db->resultSet();

// Category performance
if ($filterCategory > 0) {
    $db->query("SELECT c.name, 
                COUNT(ur.response_id) as total_responses,
                SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
                ROUND(AVG(ur.response_time)) as avg_response_time
                FROM categories c
                JOIN flashcards f ON c.category_id = f.category_id
                JOIN user_responses ur ON f.card_id = ur.card_id
                WHERE c.category_id = :category_id
                AND DATE(ur.created_at) BETWEEN :start_date AND :end_date
                GROUP BY c.category_id");
    $db->bind(':category_id', $filterCategory);
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
} else {
    $db->query("SELECT c.name, 
                COUNT(ur.response_id) as total_responses,
                SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
                ROUND(AVG(ur.response_time)) as avg_response_time
                FROM categories c
                JOIN flashcards f ON c.category_id = f.category_id
                JOIN user_responses ur ON f.card_id = ur.card_id
                WHERE DATE(ur.created_at) BETWEEN :start_date AND :end_date
                GROUP BY c.category_id
                ORDER BY total_responses DESC");
    $db->bind(':start_date', $startDate);
    $db->bind(':end_date', $endDate);
}
$categoryStats = $db->resultSet();

// Difficulty analysis
$db->query("SELECT f.difficulty, 
            COUNT(ur.response_id) as total_responses,
            SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE DATE(ur.created_at) BETWEEN :start_date AND :end_date
            " . ($filterCategory > 0 ? "AND f.category_id = :category_id" : "") . "
            GROUP BY f.difficulty
            ORDER BY f.difficulty");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
if ($filterCategory > 0) {
    $db->bind(':category_id', $filterCategory);
}
$difficultyStats = $db->resultSet();

// Answer type analysis
$db->query("SELECT f.answer_type, 
            COUNT(ur.response_id) as total_responses,
            SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE DATE(ur.created_at) BETWEEN :start_date AND :end_date
            " . ($filterCategory > 0 ? "AND f.category_id = :category_id" : "") . "
            GROUP BY f.answer_type
            ORDER BY understanding_rate DESC");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
if ($filterCategory > 0) {
    $db->bind(':category_id', $filterCategory);
}
$answerTypeStats = $db->resultSet();

// Top performing cards
$db->query("SELECT f.question, c.name as category_name, f.difficulty,
            COUNT(ur.response_id) as total_responses,
            SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN categories c ON f.category_id = c.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE DATE(ur.created_at) BETWEEN :start_date AND :end_date
            " . ($filterCategory > 0 ? "AND f.category_id = :category_id" : "") . "
            GROUP BY f.card_id
            HAVING total_responses >= 5
            ORDER BY understanding_rate DESC
            LIMIT 10");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
if ($filterCategory > 0) {
    $db->bind(':category_id', $filterCategory);
}
$topCards = $db->resultSet();

// Difficult cards (low understanding rate)
$db->query("SELECT f.question, c.name as category_name, f.difficulty,
            COUNT(ur.response_id) as total_responses,
            SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) as understood_responses,
            ROUND((SUM(CASE WHEN ur.understood = 1 THEN 1 ELSE 0 END) / COUNT(ur.response_id)) * 100) as understanding_rate
            FROM flashcards f
            JOIN categories c ON f.category_id = c.category_id
            JOIN user_responses ur ON f.card_id = ur.card_id
            WHERE DATE(ur.created_at) BETWEEN :start_date AND :end_date
            " . ($filterCategory > 0 ? "AND f.category_id = :category_id" : "") . "
            GROUP BY f.card_id
            HAVING total_responses >= 5
            ORDER BY understanding_rate ASC
            LIMIT 10");
$db->bind(':start_date', $startDate);
$db->bind(':end_date', $endDate);
if ($filterCategory > 0) {
    $db->bind(':category_id', $filterCategory);
}
$difficultyCards = $db->resultSet();

// Include admin header
include_once 'includes/header.php';
?>

<!-- Page title and filter controls -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?php echo $pageTitle; ?></h1>
    <div>
        <span class="text-muted me-2">Date Range: <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?></span>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="bi bi-sliders"></i> Filter
        </button>
    </div>
</div>

<!-- Filter form -->
<div class="collapse mb-4" id="filterCollapse">
    <div class="card">
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                
                <div class="col-md-4">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category->category_id; ?>" <?php echo $filterCategory === $category->category_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                </div>
                
                <div class="col-12">
                    <div class="btn-group" role="group">
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&category=<?php echo $filterCategory; ?>" class="btn btn-sm btn-outline-secondary">Last 7 Days</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-30 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&category=<?php echo $filterCategory; ?>" class="btn btn-sm btn-outline-secondary">Last 30 Days</a>
                        <a href="?start_date=<?php echo date('Y-m-d', strtotime('-90 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&category=<?php echo $filterCategory; ?>" class="btn btn-sm btn-outline-secondary">Last 90 Days</a>
                        <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>&category=<?php echo $filterCategory; ?>" class="btn btn-sm btn-outline-secondary">This Month</a>
                        <a href="?start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>&category=<?php echo $filterCategory; ?>" class="btn btn-sm btn-outline-secondary">This Year</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Overview Stats -->
<div class="row g-4 mb-5">
    <div class="col-md-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Total Responses</h5>
                <p class="display-4"><?php echo number_format($totalResponses); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Understanding Rate</h5>
                <p class="display-4"><?php echo $understandingRate; ?>%</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Avg. Response Time</h5>
                <p class="display-4"><?php echo $avgResponseTime; ?>s</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center">
                <h5 class="card-title">Active Users</h5>
                <p class="display-4"><?php echo $activeUsers; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Daily Activity Chart -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daily Activity</h5>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary active" id="viewResponses">Responses</button>
            <button type="button" class="btn btn-outline-secondary" id="viewUnderstanding">Understanding Rate</button>
        </div>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="dailyActivityChart"></canvas>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Difficulty Analysis -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Difficulty Analysis</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="difficultyChart"></canvas>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Difficulty</th>
                                <th>Responses</th>
                                <th>Understanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($difficultyStats as $stat): ?>
                                <tr>
                                    <td>Level <?php echo $stat->difficulty; ?></td>
                                    <td><?php echo number_format($stat->total_responses); ?></td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stat->understanding_rate; ?>%;" 
                                                aria-valuenow="<?php echo $stat->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small><?php echo $stat->understanding_rate; ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Answer Type Analysis -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Answer Type Analysis</h5>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height: 250px;">
                    <canvas id="answerTypeChart"></canvas>
                </div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Answer Type</th>
                                <th>Responses</th>
                                <th>Understanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answerTypeStats as $stat): ?>
                                <tr>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $stat->answer_type)); ?></td>
                                    <td><?php echo number_format($stat->total_responses); ?></td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stat->understanding_rate; ?>%;" 
                                                aria-valuenow="<?php echo $stat->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small><?php echo $stat->understanding_rate; ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Performance -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Category Performance</h5>
    </div>
    <div class="card-body">
        <?php if (empty($categoryStats)): ?>
            <p class="text-center">No data available for the selected date range and filters.</p>
        <?php else: ?>
            <div class="chart-container mb-4">
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Responses</th>
                            <th>Understanding Rate</th>
                            <th>Avg. Response Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categoryStats as $stat): ?>
                            <?php 
                                $understandingRatePercent = $stat->total_responses > 0 ? 
                                    round(($stat->understood_responses / $stat->total_responses) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat->name); ?></td>
                                <td><?php echo number_format($stat->total_responses); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $understandingRatePercent; ?>%;" 
                                                aria-valuenow="<?php echo $understandingRatePercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span><?php echo $understandingRatePercent; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo $stat->avg_response_time; ?> seconds</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Top Performing Cards -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Top Performing Cards</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topCards)): ?>
                    <p class="text-center p-4">No data available for the selected date range and filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Category</th>
                                    <th>Difficulty</th>
                                    <th>Understanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topCards as $card): ?>
                                    <tr>
                                        <td><?php echo strlen($card->question) > 50 ? htmlspecialchars(substr($card->question, 0, 50)) . '...' : htmlspecialchars($card->question); ?></td>
                                        <td><?php echo htmlspecialchars($card->category_name); ?></td>
                                        <td><?php echo $card->difficulty; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $card->understanding_rate; ?>%;" 
                                                        aria-valuenow="<?php echo $card->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span><?php echo $card->understanding_rate; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Most Difficult Cards -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Most Difficult Cards</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($difficultyCards)): ?>
                    <p class="text-center p-4">No data available for the selected date range and filters.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Category</th>
                                    <th>Difficulty</th>
                                    <th>Understanding</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($difficultyCards as $card): ?>
                                    <tr>
                                        <td><?php echo strlen($card->question) > 50 ? htmlspecialchars(substr($card->question, 0, 50)) . '...' : htmlspecialchars($card->question); ?></td>
                                        <td><?php echo htmlspecialchars($card->category_name); ?></td>
                                        <td><?php echo $card->difficulty; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                    <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $card->understanding_rate; ?>%;" 
                                                        aria-valuenow="<?php echo $card->understanding_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span><?php echo $card->understanding_rate; ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ChartJS Initialization -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Common chart options
        Chart.defaults.font.family = getComputedStyle(document.body).getPropertyValue('--body-font') || "'Nunito', sans-serif";
        
        // Parse data from PHP
        const dailyData = <?php echo json_encode($dailyStats); ?>;
        const difficultyData = <?php echo json_encode($difficultyStats); ?>;
        const answerTypeData = <?php echo json_encode($answerTypeStats); ?>;
        const categoryData = <?php echo json_encode($categoryStats); ?>;
        
        // Daily Activity Chart
        const dailyLabels = dailyData.map(item => formatDate(item.date));
        const dailyResponses = dailyData.map(item => item.responses);
        const dailyUnderstood = dailyData.map(item => item.understood);
        const dailyUnderstandingRate = dailyData.map(item => {
            return item.responses > 0 ? (item.understood / item.responses) * 100 : 0;
        });
        
        const chartColors = {
            primary: getComputedStyle(document.body).getPropertyValue('--admin-primary') || '#4361ee',
            success: getComputedStyle(document.body).getPropertyValue('--admin-success') || '#4CAF50',
            warning: getComputedStyle(document.body).getPropertyValue('--admin-warning') || '#ff9800',
            danger: getComputedStyle(document.body).getPropertyValue('--admin-danger') || '#f44336',
            info: getComputedStyle(document.body).getPropertyValue('--admin-info') || '#03a9f4'
        };
        
        const dailyChart = new Chart(document.getElementById('dailyActivityChart'), {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Total Responses',
                    data: dailyResponses,
                    borderColor: chartColors.primary,
                    backgroundColor: hexToRGBA(chartColors.primary, 0.1),
                    tension: 0.3,
                    fill: true
                }, {
                    label: 'Understood',
                    data: dailyUnderstood,
                    borderColor: chartColors.success,
                    backgroundColor: hexToRGBA(chartColors.success, 0.1),
                    tension: 0.3,
                    fill: true,
                    hidden: true
                }, {
                    label: 'Understanding Rate (%)',
                    data: dailyUnderstandingRate,
                    borderColor: chartColors.info,
                    backgroundColor: hexToRGBA(chartColors.info, 0.1),
                    tension: 0.3,
                    fill: true,
                    hidden: true,
                    yAxisID: 'percentage'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    },
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 4]
                        }
                    },
                    percentage: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        // Toggle dataset visibility
        document.getElementById('viewResponses').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('viewUnderstanding').classList.remove('active');
            dailyChart.data.datasets[0].hidden = false;
            dailyChart.data.datasets[1].hidden = false;
            dailyChart.data.datasets[2].hidden = true;
            dailyChart.update();
        });
        
        document.getElementById('viewUnderstanding').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('viewResponses').classList.remove('active');
            dailyChart.data.datasets[0].hidden = true;
            dailyChart.data.datasets[1].hidden = true;
            dailyChart.data.datasets[2].hidden = false;
            dailyChart.update();
        });
        
        // Difficulty Chart
        const difficultyChart = new Chart(document.getElementById('difficultyChart'), {
            type: 'bar',
            data: {
                labels: difficultyData.map(item => 'Level ' + item.difficulty),
                datasets: [{
                    label: 'Understanding Rate (%)',
                    data: difficultyData.map(item => item.understanding_rate),
                    backgroundColor: difficultyData.map(item => {
                        const rate = item.understanding_rate;
                        if (rate > 75) return hexToRGBA(chartColors.success, 0.7);
                        if (rate > 50) return hexToRGBA(chartColors.info, 0.7);
                        if (rate > 25) return hexToRGBA(chartColors.warning, 0.7);
                        return hexToRGBA(chartColors.danger, 0.7);
                    }),
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + '% understanding rate';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        // Answer Type Chart
        const answerTypeChart = new Chart(document.getElementById('answerTypeChart'), {
            type: 'polarArea',
            data: {
                labels: answerTypeData.map(item => formatAnswerType(item.answer_type)),
                datasets: [{
                    data: answerTypeData.map(item => item.total_responses),
                    backgroundColor: [
                        hexToRGBA(chartColors.primary, 0.7),
                        hexToRGBA(chartColors.success, 0.7),
                        hexToRGBA(chartColors.warning, 0.7),
                        hexToRGBA(chartColors.info, 0.7),
                        hexToRGBA(chartColors.danger, 0.7)
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const index = context.dataIndex;
                                return [
                                    context.chart.data.labels[index] + ': ' + context.raw + ' responses',
                                    'Understanding rate: ' + answerTypeData[index].understanding_rate + '%'
                                ];
                            }
                        }
                    }
                }
            }
        });
        
        // Category Chart
        const categoryChart = new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: categoryData.map(item => item.name),
                datasets: [{
                    label: 'Total Responses',
                    data: categoryData.map(item => item.total_responses),
                    backgroundColor: hexToRGBA(chartColors.primary, 0.7),
                    borderColor: chartColors.primary,
                    borderWidth: 1,
                    borderRadius: 4,
                    yAxisID: 'y'
                }, {
                    label: 'Understanding Rate (%)',
                    data: categoryData.map(item => {
                        return item.total_responses > 0 ? Math.round((item.understood_responses / item.total_responses) * 100) : 0;
                    }),
                    backgroundColor: hexToRGBA(chartColors.success, 0.7),
                    borderColor: chartColors.success,
                    borderWidth: 1,
                    borderRadius: 4,
                    type: 'line',
                    fill: false,
                    yAxisID: 'percentage'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Responses'
                        }
                    },
                    percentage: {
                        position: 'right',
                        beginAtZero: true,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Understanding (%)'
                        },
                        grid: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
        
        // Helper Functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
        
        function formatAnswerType(type) {
            return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        }
        
        function hexToRGBA(hex, alpha) {
            // If hex is already in rgb format
            if (hex.startsWith('rgb')) {
                return hex.replace(')', ', ' + alpha + ')').replace('rgb', 'rgba');
            }
            
            // Default color if conversion fails
            if (!hex || hex === 'transparent') {
                return `rgba(0, 123, 255, ${alpha})`;
            }
            
            // Remove # if present
            hex = hex.replace('#', '');
            
            // Convert shorthand hex to full hex
            if (hex.length === 3) {
                hex = hex.split('').map(c => c + c).join('');
            }
            
            // Convert hex to rgb
            const r = parseInt(hex.substring(0, 2), 16);
            const g = parseInt(hex.substring(2, 4), 16);
            const b = parseInt(hex.substring(4, 6), 16);
            
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
    });
</script>

<?php
// Include admin footer
include_once 'includes/footer.php';
?>