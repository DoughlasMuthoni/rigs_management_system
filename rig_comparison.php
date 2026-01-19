<?php
// rig_comparison.php
// Rig Performance Comparison Dashboard

// Load required files
require_once 'config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

// Get parameters
$month = isset($_GET['month']) ? intval($_GET['month']) : $selected_month;
$year = isset($_GET['year']) ? intval($_GET['year']) : $selected_year;
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly'; // monthly, quarterly, yearly

// Date ranges based on period
$date_range = getDateRange($period, $month, $year);
$start_date = $date_range['start_date'];
$end_date = $date_range['end_date'];

// Get all active rigs
$rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");

// Initialize comparison data array
$comparison_data = [];
$all_projects = [];

// Get performance data for each rig
foreach ($rigs as $rig) {
    $rig_id = $rig['id'];
    
    
   // Get projects for this rig in the period
    $projects = fetchAll("
        SELECT p.id, p.* 
        FROM projects p 
        WHERE p.rig_id = $rig_id 
        AND p.completion_date BETWEEN '$start_date' AND '$end_date'
        ORDER BY p.completion_date
    ");
    
    $total_revenue = 0;
    $total_expenses = 0;
    $total_profit = 0;
    $total_days = 0;
    $project_count = count($projects);
    
    foreach ($projects as $project) {
        $expenses = getProjectExpenses($project['id']);
        $profit = $project['payment_received'] - $expenses['total'];
        
        $total_revenue += $project['payment_received'];
        $total_expenses += $expenses['total'];
        $total_profit += $profit;
        
        // Calculate project duration in days
        if ($project['start_date'] && $project['completion_date']) {
            $start = new DateTime($project['start_date']);
            $end = new DateTime($project['completion_date']);
            $total_days += $end->diff($start)->days + 1;
        }
        
        // Store project details for detailed view
       // Store project details for detailed view
        $all_projects[] = [
            'id' => $project['id'],  // ADD THIS LINE
            'rig_id' => $rig_id,
            'rig_name' => $rig['rig_name'],
            'project_code' => $project['project_code'],
            'project_name' => $project['project_name'],
            'revenue' => $project['payment_received'],
            'expenses' => $expenses['total'],
            'profit' => $profit,
            'completion_date' => $project['completion_date']
        ];
    }
    
    // Calculate metrics
    $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
    $avg_daily_profit = $total_days > 0 ? $total_profit / $total_days : 0;
    $avg_project_profit = $project_count > 0 ? $total_profit / $project_count : 0;
    $efficiency = $total_days > 0 ? $total_revenue / $total_days : 0;
    
    $comparison_data[$rig_id] = [
        'rig_name' => $rig['rig_name'],
        'total_revenue' => $total_revenue,
        'total_expenses' => $total_expenses,
        'total_profit' => $total_profit,
        'profit_margin' => $profit_margin,
        'project_count' => $project_count,
        'total_days' => $total_days,
        'avg_daily_profit' => $avg_daily_profit,
        'avg_project_profit' => $avg_project_profit,
        'efficiency' => $efficiency,
        'utilization_rate' => $total_days > 0 ? min(($total_days / (30 * $project_count)) * 100, 100) : 0
    ];
}

// Calculate rankings
$rankings = [];
foreach (['total_profit', 'profit_margin', 'efficiency', 'avg_daily_profit'] as $metric) {
    uasort($comparison_data, function($a, $b) use ($metric) {
        return $b[$metric] <=> $a[$metric];
    });
    
    $rank = 1;
    foreach ($comparison_data as $rig_id => $data) {
        if (!isset($rankings[$rig_id])) {
            $rankings[$rig_id] = [];
        }
        $rankings[$rig_id][$metric] = $rank++;
    }
}

// Helper function to get date range
function getDateRange($period, $month, $year) {
    switch ($period) {
        case 'quarterly':
            $quarter = ceil($month / 3);
            $start_month = (($quarter - 1) * 3) + 1;
            $end_month = $start_month + 2;
            $start_date = date("$year-$start_month-01");
            $end_date = date("$year-$end_month-t");
            break;
            
        case 'yearly':
            $start_date = "$year-01-01";
            $end_date = "$year-12-31";
            break;
            
        case 'monthly':
        default:
            $start_date = date("$year-$month-01");
            $end_date = date("$year-$month-t");
            break;
    }
    
    return [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'period_name' => $period
    ];
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-bar-chart-line me-2"></i>Rig Performance Comparison
                    </h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?php 
                        echo ucfirst($period) . ' Report: ';
                        if ($period === 'monthly') {
                            echo date('F Y', strtotime("$year-$month-01"));
                        } elseif ($period === 'quarterly') {
                            $quarter = ceil($month / 3);
                            echo "Q$quarter $year";
                        } else {
                            echo "Year $year";
                        }
                        ?>
                    </p>
                </div>
                <div class="btn-group">
                    <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Period Selector -->
    <div class="card shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Period Type</label>
                    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $period === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo $period === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                
                <?php if ($period === 'monthly'): ?>
                <div class="col-md-3">
                    <label class="form-label small">Month</label>
                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php elseif ($period === 'quarterly'): ?>
                <div class="col-md-3">
                    <label class="form-label small">Quarter</label>
                    <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="3" <?php echo $month <= 3 ? 'selected' : ''; ?>>Q1 (Jan-Mar)</option>
                        <option value="6" <?php echo $month > 3 && $month <= 6 ? 'selected' : ''; ?>>Q2 (Apr-Jun)</option>
                        <option value="9" <?php echo $month > 6 && $month <= 9 ? 'selected' : ''; ?>>Q3 (Jul-Sep)</option>
                        <option value="12" <?php echo $month > 9 ? 'selected' : ''; ?>>Q4 (Oct-Dec)</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label class="form-label small">Year</label>
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-filter me-1"></i>Apply Filter
                    </button>
                    <a href="rig_comparison.php" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Metrics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start-primary h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Total Rigs</div>
                            <div class="h4 mb-0"><?php echo count($rigs); ?></div>
                        </div>
                        <div class="text-primary">
                            <i class="bi bi-truck fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start-success h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Total Projects</div>
                            <div class="h4 mb-0"><?php echo count($all_projects); ?></div>
                        </div>
                        <div class="text-success">
                            <i class="bi bi-clipboard-check fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start-info h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Total Revenue</div>
                            <div class="h4 mb-0">
                                <?php 
                                $total_revenue_all = array_sum(array_column($comparison_data, 'total_revenue'));
                                echo 'Ksh ' . number_format($total_revenue_all);
                                ?>
                            </div>
                        </div>
                        <div class="text-info">
                            <i class="bi bi-cash-coin fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card border-start-warning h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Avg Profit Margin</div>
                            <div class="h4 mb-0">
                                <?php 
                                $avg_margin = count($comparison_data) > 0 ? 
                                    array_sum(array_column($comparison_data, 'profit_margin')) / count($comparison_data) : 0;
                                echo number_format($avg_margin, 1) . '%';
                                ?>
                            </div>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-percent fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Comparison Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-2">
            <h6 class="m-0 fw-bold">
                <i class="bi bi-table me-2"></i>Rig Performance Comparison
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Rig</th>
                            <th class="text-end">Projects</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Profit</th>
                            <th class="text-end">Margin</th>
                            <th class="text-end">Daily Profit</th>
                            <th class="text-end">Efficiency</th>
                            <th class="text-center">Ranking</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison_data as $rig_id => $data): 
                            $profit_class = $data['total_profit'] >= 0 ? 'text-success' : 'text-danger';
                            $margin_class = $data['profit_margin'] >= 0 ? 'text-success' : 'text-danger';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo $data['rig_name']; ?></div>
                            
                            </td>
                            <td class="text-end">
                                <span class="badge bg-info"><?php echo $data['project_count']; ?></span>
                            </td>
                            <td class="text-end">
                                <span class="text-primary fw-bold"><?php echo formatCurrency($data['total_revenue']); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="text-warning"><?php echo formatCurrency($data['total_expenses']); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold <?php echo $profit_class; ?>">
                                    <?php echo formatCurrency($data['total_profit']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-<?php echo $data['profit_margin'] >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($data['profit_margin'], 1); ?>%
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="<?php echo $data['avg_daily_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo formatCurrency($data['avg_daily_profit']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="text-info">
                                    <?php echo formatCurrency($data['efficiency']); ?>/day
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <?php if (isset($rankings[$rig_id]['total_profit'])): ?>
                                    <span class="badge bg-primary" data-bs-toggle="tooltip" title="Profit Rank">
                                        #<?php echo $rankings[$rig_id]['total_profit']; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (isset($rankings[$rig_id]['efficiency'])): ?>
                                    <span class="badge bg-success" data-bs-toggle="tooltip" title="Efficiency Rank">
                                        #<?php echo $rankings[$rig_id]['efficiency']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo BASE_URL; ?>/modules/reports/monthly_report.php?rig=<?php echo $rig_id; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                       class="btn btn-outline-info" data-bs-toggle="tooltip" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/modules/projects/add_project.php?rig_id=<?php echo $rig_id; ?>" 
                                       class="btn btn-outline-success" data-bs-toggle="tooltip" title="Add Project">
                                        <i class="bi bi-plus-circle"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (count($comparison_data) > 0): ?>
                    <tfoot class="table-light">
                        <tr>
                            <th>TOTAL/AVERAGE</th>
                            <th class="text-end"><?php echo array_sum(array_column($comparison_data, 'project_count')); ?></th>
                            <th class="text-end text-primary"><?php echo formatCurrency(array_sum(array_column($comparison_data, 'total_revenue'))); ?></th>
                            <th class="text-end text-warning"><?php echo formatCurrency(array_sum(array_column($comparison_data, 'total_expenses'))); ?></th>
                            <th class="text-end <?php echo array_sum(array_column($comparison_data, 'total_profit')) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo formatCurrency(array_sum(array_column($comparison_data, 'total_profit'))); ?>
                            </th>
                            <th class="text-end">
                                <?php 
                                $avg_margin = count($comparison_data) > 0 ? 
                                    array_sum(array_column($comparison_data, 'profit_margin')) / count($comparison_data) : 0;
                                $margin_class = $avg_margin >= 0 ? 'bg-success' : 'bg-danger';
                                ?>
                                <span class="badge <?php echo $margin_class; ?>">
                                    <?php echo number_format($avg_margin, 1); ?>%
                                </span>
                            </th>
                            <th class="text-end <?php echo array_sum(array_column($comparison_data, 'avg_daily_profit')) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php 
                                $avg_daily = count($comparison_data) > 0 ? 
                                    array_sum(array_column($comparison_data, 'avg_daily_profit')) / count($comparison_data) : 0;
                                echo formatCurrency($avg_daily);
                                ?>
                            </th>
                            <th class="text-end text-info">
                                <?php 
                                $avg_efficiency = count($comparison_data) > 0 ? 
                                    array_sum(array_column($comparison_data, 'efficiency')) / count($comparison_data) : 0;
                                echo formatCurrency($avg_efficiency);
                                ?>/day
                            </th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-bar-chart me-2"></i>Profit Comparison
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="profitChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-speedometer2 me-2"></i>Efficiency Ranking
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="efficiencyChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Projects List -->
    <?php if (count($all_projects) > 0): ?>
    <div class="card shadow-sm">
        <div class="card-header py-2">
            <h6 class="m-0 fw-bold">
                <i class="bi bi-list-check me-2"></i>All Projects in Period
                <span class="badge bg-primary ms-2"><?php echo count($all_projects); ?> projects</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Project</th>
                            <th>Rig</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Profit</th>
                            <th class="text-end">Margin</th>
                            <th>Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_projects as $project): 
                            $margin = $project['revenue'] > 0 ? ($project['profit'] / $project['revenue']) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo $project['project_code']; ?></div>
                                <small class="text-muted"><?php echo substr($project['project_name'], 0, 30) . '...'; ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?php echo $project['rig_name']; ?></span>
                            </td>
                            <td class="text-end">
                                <span class="text-primary"><?php echo formatCurrency($project['revenue']); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="text-warning"><?php echo formatCurrency($project['expenses']); ?></span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-<?php echo $project['profit'] >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo formatCurrency($project['profit']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-<?php echo $margin >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($margin, 1); ?>%
                                </span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($project['completion_date'])); ?>
                            </td>
                            <td class="text-center">
                               <a href="<?php echo BASE_URL; ?>/modules/projects/project_details.php?id=<?php 
                                    echo $project['id'];  // Use the actual project ID
                                ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                        
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Profit Comparison Chart
    const profitCtx = document.getElementById('profitChart').getContext('2d');
    const profitChart = new Chart(profitCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo '"' . implode('","', array_column($comparison_data, 'rig_name')) . '"'; ?>],
            datasets: [
                {
                    label: 'Revenue',
                    data: [<?php echo implode(',', array_column($comparison_data, 'total_revenue')); ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Profit',
                    data: [<?php echo implode(',', array_column($comparison_data, 'total_profit')); ?>],
                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
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
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Ksh ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Efficiency Chart (Radar)
    const efficiencyCtx = document.getElementById('efficiencyChart').getContext('2d');
    const efficiencyChart = new Chart(efficiencyCtx, {
        type: 'radar',
        data: {
            labels: ['Profit Margin', 'Daily Profit', 'Revenue Efficiency', 'Project Count', 'Utilization'],
            datasets: [
                <?php 
                $colors = ['255, 99, 132', '54, 162, 235', '255, 206, 86', '75, 192, 192', '153, 102, 255'];
                $i = 0;
                foreach ($comparison_data as $rig_id => $data): 
                ?>
                {
                    label: '<?php echo $data['rig_name']; ?>',
                    data: [
                        <?php echo $data['profit_margin']; ?>,
                        <?php echo $data['avg_daily_profit'] / 1000; ?>, // Scale down for radar
                        <?php echo $data['efficiency'] / 1000; ?>, // Scale down
                        <?php echo $data['project_count']; ?>,
                        <?php echo $data['utilization_rate']; ?>
                    ],
                    backgroundColor: 'rgba(<?php echo $colors[$i % count($colors)]; ?>, 0.2)',
                    borderColor: 'rgba(<?php echo $colors[$i % count($colors)]; ?>, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(<?php echo $colors[$i % count($colors)]; ?>, 1)'
                },
                <?php 
                $i++;
                endforeach; 
                ?>
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    ticks: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>

<style>
    .card {
        border-radius: 8px;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0,0,0,.125);
    }
    
    .table th {
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        color: #6c757d;
    }
    
    .table td {
        vertical-align: middle;
        font-size: 0.9rem;
    }
    
    .badge {
        font-size: 0.75em;
    }
    
    @media print {
        .btn, .no-print {
            display: none !important;
        }
        
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        
        .table {
            font-size: 10px !important;
        }
    }
</style>

<?php 
require_once ROOT_PATH . '/includes/footer.php';
?>