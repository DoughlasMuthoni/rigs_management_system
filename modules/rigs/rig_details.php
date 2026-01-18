<?php
// 1. Load config.php - TWO levels up from modules/rigs/
require_once '../../config.php';

// 2. Load init.php
require_once ROOT_PATH . '/includes/init.php';

// 3. Load functions.php
require_once ROOT_PATH . '/includes/functions.php';

// 4. Load header.php
require_once ROOT_PATH . '/includes/header.php';

// Check if rig ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_rigs.php');
    exit();
}

$rig_id = intval($_GET['id']);

// Rest of your code...

// Get rig details
$rig = fetchOne("SELECT * FROM rigs WHERE id = $rig_id");
if (!$rig) {
    header('Location: view_rigs.php');
    exit();
}

// Get selected month/year from GET or use current
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month
if ($month < 1 || $month > 12) {
    $month = date('m');
}

// Validate year
if ($year < 2020 || $year > 2100) {
    $year = date('Y');
}

// Get rig performance data
$performance = getRigMonthlyPerformance($rig_id, $month, $year);

// Get all projects for this rig in selected month
$projects = fetchAll("SELECT p.* FROM projects p 
                     WHERE p.rig_id = $rig_id 
                     AND YEAR(p.completion_date) = $year 
                     AND MONTH(p.completion_date) = $month
                     ORDER BY p.completion_date DESC");

// Get monthly trends (last 6 months)
$trends_sql = "SELECT 
                DATE_FORMAT(p.completion_date, '%Y-%m') as month_year,
                COUNT(p.id) as project_count,
                SUM(p.payment_received) as revenue,
                COUNT(p.id) as total_projects
               FROM projects p
               WHERE p.rig_id = $rig_id 
               AND p.completion_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
               GROUP BY DATE_FORMAT(p.completion_date, '%Y-%m')
               ORDER BY month_year DESC
               LIMIT 6";

$trends = fetchAll($trends_sql);

// Get expense breakdown for the month
$expense_breakdown = [
    'salaries' => 0,
    'fuel' => 0,
    'casings' => 0,
    'consumables' => 0,
    'miscellaneous' => 0
];

foreach ($projects as $project) {
    $expenses = getProjectExpenses($project['id']);
    $fixed_expenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = {$project['id']}");
    
    if ($fixed_expenses) {
        $expense_breakdown['salaries'] += $fixed_expenses['salaries'];
        $expense_breakdown['fuel'] += $fixed_expenses['fuel_rig'] + 
                                     $fixed_expenses['fuel_truck'] + 
                                     $fixed_expenses['fuel_pump'] + 
                                     $fixed_expenses['fuel_hired'];
        $expense_breakdown['casings'] += $fixed_expenses['casing_surface'] + 
                                        $fixed_expenses['casing_screened'] + 
                                        $fixed_expenses['casing_plain'];
    }
    
    $expense_breakdown['consumables'] += $expenses['consumables'];
    $expense_breakdown['miscellaneous'] += $expenses['miscellaneous'];
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Rig Performance Details</h1>
                <p class="text-muted mb-0"><?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>) - <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="edit_rig.php?id=<?php echo $rig_id; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil me-2"></i>Edit Rig
                </a>
                <a href="view_rigs.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>All Rigs
                </a>
            </div>
        </div>
    </div>

    <!-- Month Selector -->
    <div class="month-selector mb-4">
        <div>
            <h3>Performance Analysis</h3>
            <p class="text-muted mb-0">Select month to view rig performance</p>
        </div>
        <form method="GET" class="date-form">
            <input type="hidden" name="id" value="<?php echo $rig_id; ?>">
            <select name="month" class="form-select" onchange="this.form.submit()">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year" class="form-select" onchange="this.form.submit()">
                <?php for ($y = 2023; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Monthly Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($performance['revenue']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success mr-2"><i class="fas fa-arrow-up"></i> Revenue</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-exchange fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Monthly Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($performance['expenses']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-warning mr-2"><i class="fas fa-arrow-down"></i> Costs</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cash-stack fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?php echo $performance['profit'] >= 0 ? 'success' : 'danger'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $performance['profit'] >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Monthly Profit</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($performance['profit']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-<?php echo $performance['profit'] >= 0 ? 'success' : 'danger'; ?> mr-2">
                                    <i class="fas fa-<?php echo $performance['profit'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                                    <?php echo $performance['profit'] >= 0 ? 'Profit' : 'Loss'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-up-arrow fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Projects Completed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $performance['project_count']; ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-info mr-2"><i class="fas fa-tasks"></i> This Month</span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clipboard-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Performance Charts -->
        <div class="col-lg-8">
            <!-- Performance Trends Chart -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-graph-up me-2"></i>6-Month Performance Trend
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="performanceTrendChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Expense Breakdown -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-pie-chart me-2"></i>Expense Breakdown - <?php echo date('F Y', strtotime("$year-$month-01")); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="expenseChart" height="250"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Salaries</span>
                                    <span class="text-primary fw-bold"><?php echo formatCurrency($expense_breakdown['salaries']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Fuel</span>
                                    <span class="text-warning fw-bold"><?php echo formatCurrency($expense_breakdown['fuel']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Casings</span>
                                    <span class="text-info fw-bold"><?php echo formatCurrency($expense_breakdown['casings']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Consumables</span>
                                    <span class="text-success fw-bold"><?php echo formatCurrency($expense_breakdown['consumables']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="fw-bold">Miscellaneous</span>
                                    <span class="text-secondary fw-bold"><?php echo formatCurrency($expense_breakdown['miscellaneous']); ?></span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center pt-3 border-top">
                                    <span class="fw-bold">Total Expenses</span>
                                    <span class="text-danger fw-bold"><?php echo formatCurrency($performance['expenses']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Rig Info & Projects -->
        <div class="col-lg-4">
            <!-- Rig Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-truck me-2"></i>Rig Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-truck text-white" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="mt-3 mb-1"><?php echo $rig['rig_name']; ?></h5>
                        <p class="text-muted"><?php echo $rig['rig_code']; ?></p>
                        <span class="badge bg-<?php echo $rig['status'] == 'active' ? 'success' : 'secondary'; ?> fs-6">
                            <?php echo ucfirst($rig['status']); ?>
                        </span>
                    </div>

                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-calendar me-2"></i>Purchase Date</span>
                            <span>
                                <?php 
                                if (isset($rig['purchase_date']) && !empty($rig['purchase_date']) && $rig['purchase_date'] != '0000-00-00') {
                                    echo date('M d, Y', strtotime($rig['purchase_date']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-calendar-plus me-2"></i>Registered</span>
                            <span><?php echo date('M d, Y', strtotime($rig['created_at'])); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-speedometer2 me-2"></i>Profit Margin</span>
                            <span class="fw-bold text-<?php echo $performance['profit_margin'] >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo number_format($performance['profit_margin'], 2); ?>%
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($rig['description'])): ?>
                    <div class="mt-3">
                        <h6 class="text-muted mb-2">Description</h6>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($rig['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Projects Card -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-clipboard-data me-2"></i>Monthly Projects
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (count($projects) == 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clipboard display-4 text-muted mb-3"></i>
                            <p class="text-muted">No projects for <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
                            <a href="../projects/add_project.php?rig_id=<?php echo $rig_id; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Add Project
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($projects as $project): 
                                $profit = calculateProjectProfit($project['id']);
                                $margin = $project['payment_received'] > 0 ? ($profit / $project['payment_received']) * 100 : 0;
                            ?>
                            <a href="../projects/project_details.php?id=<?php echo $project['id']; ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo $project['project_code']; ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($project['project_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($profit); ?>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($margin, 1); ?>%</small>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="../projects/add_project.php?rig_id=<?php echo $rig_id; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle me-1"></i>Add New Project
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Performance Trends Chart
    const trendCtx = document.getElementById('performanceTrendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_reverse(array_column($trends, 'month_year'))); ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?php echo json_encode(array_reverse(array_column($trends, 'revenue'))); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.05)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Projects',
                    data: <?php echo json_encode(array_reverse(array_column($trends, 'project_count'))); ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.05)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Ksh ' + value.toLocaleString();
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (label.includes('Revenue')) {
                                label += 'Ksh ' + context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Expense Breakdown Chart
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: ['Salaries', 'Fuel', 'Casings', 'Consumables', 'Miscellaneous'],
            datasets: [{
                data: [
                    <?php echo $expense_breakdown['salaries']; ?>,
                    <?php echo $expense_breakdown['fuel']; ?>,
                    <?php echo $expense_breakdown['casings']; ?>,
                    <?php echo $expense_breakdown['consumables']; ?>,
                    <?php echo $expense_breakdown['miscellaneous']; ?>
                ],
                backgroundColor: [
                    '#4e73df', // Primary
                    '#f6c23e', // Warning
                    '#36b9cc', // Info
                    '#1cc88a', // Success
                    '#6c757d'  // Secondary
                ],
                borderWidth: 2,
                borderColor: '#fff'
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
                            let label = context.label || '';
                            if (label) {
                                label += ': Ksh ';
                            }
                            label += context.parsed.toLocaleString();
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<style>
    .card {
        border: 1px solid #e3e6f0;
        border-radius: 10px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0,0,0,.125);
        border-radius: 10px 10px 0 0 !important;
    }
    
    .month-selector {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .month-selector h3 {
        margin: 0;
        color: #1e3c72;
        font-size: 1.3rem;
        font-weight: 600;
    }
    
    .date-form {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .date-form select {
        padding: 8px 15px;
        border: 1px solid #e3e6f0;
        border-radius: 6px;
        background: white;
        color: #5a5c69;
        font-weight: 500;
        cursor: pointer;
    }
    
    .list-group-item {
        border-left: none;
        border-right: none;
        padding: 1rem 0;
    }
    
    .list-group-item:first-child {
        border-top: none;
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-danger { border-left: 4px solid #e74a3b !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
</style>

<?php 
// Load footer.php
require_once ROOT_PATH . '/includes/footer.php'; 
?>