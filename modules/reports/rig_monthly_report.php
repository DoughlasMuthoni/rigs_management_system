<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

// Get parameters
$rig_id = isset($_GET['rig_id']) ? intval($_GET['rig_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // month, quarter, year

// Validate inputs
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2020 || $year > 2100) $year = date('Y');

// Get rig information
$rig = $rig_id > 0 ? fetchOne("SELECT * FROM rigs WHERE id = $rig_id") : null;
$rig_name = $rig ? $rig['rig_name'] : 'All Rigs';

// Generate report data based on period
$report_data = [];
$period_label = '';

switch ($period) {
    case 'quarter':
        $quarter = ceil($month / 3);
        $start_month = (($quarter - 1) * 3) + 1;
        $end_month = $start_month + 2;
        
        $where_date = "MONTH(p.completion_date) BETWEEN $start_month AND $end_month 
                      AND YEAR(p.completion_date) = $year";
        $expense_date_where = "MONTH(e.expense_date) BETWEEN $start_month AND $end_month 
                              AND YEAR(e.expense_date) = $year";
        $period_label = "Q$quarter $year";
        break;
        
    case 'year':
        $where_date = "YEAR(p.completion_date) = $year";
        $expense_date_where = "YEAR(e.expense_date) = $year";
        $period_label = "Year $year";
        break;
        
    case 'month':
    default:
        $where_date = "MONTH(p.completion_date) = $month AND YEAR(p.completion_date) = $year";
        $expense_date_where = "MONTH(e.expense_date) = $month AND YEAR(e.expense_date) = $year";
        $period_label = date('F Y', strtotime("$year-$month-01"));
        break;
}

// Get rig filter
$rig_where = $rig_id > 0 ? "AND p.rig_id = $rig_id" : "";

// Get projects for the period
$projects_sql = "SELECT p.*, c.first_name, c.last_name, c.company_name
                FROM projects p
                LEFT JOIN customers c ON p.customer_id = c.id
                WHERE $where_date AND p.status = 'completed' $rig_where
                ORDER BY p.completion_date DESC";
$projects = fetchAll($projects_sql);

// Calculate totals
$total_revenue = 0;
$total_expenses = 0;
$total_profit = 0;
$project_count = count($projects);

foreach ($projects as $project) {
    $total_revenue += $project['payment_received'];
    
    // Get project expenses
    $expenses_sql = "SELECT SUM(amount) as total FROM expenses WHERE project_id = {$project['id']}";
    $expenses_data = fetchOne($expenses_sql);
    $project_expenses = $expenses_data['total'] ?? 0;
    $total_expenses += $project_expenses;
    
    $project_profit = $project['payment_received'] - $project_expenses;
    $total_profit += $project_profit;
}

$profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;

// Get expense breakdown
$expense_breakdown_sql = "SELECT 
                            et.category,
                            et.expense_name,
                            SUM(e.amount) as total_amount,
                            COUNT(e.id) as expense_count
                          FROM expenses e
                          JOIN projects p ON e.project_id = p.id
                          JOIN expense_types et ON e.expense_type_id = et.id
                          WHERE $expense_date_where $rig_where
                          GROUP BY et.category, et.expense_name
                          ORDER BY et.category, total_amount DESC";
$expense_breakdown = fetchAll($expense_breakdown_sql);

// Group by category
$expense_by_category = [];
$total_expenses_by_category = 0;

foreach ($expense_breakdown as $expense) {
    $category = $expense['category'];
    if (!isset($expense_by_category[$category])) {
        $expense_by_category[$category] = [
            'total' => 0,
            'items' => []
        ];
    }
    $expense_by_category[$category]['total'] += $expense['total_amount'];
    $expense_by_category[$category]['items'][] = $expense;
    $total_expenses_by_category += $expense['total_amount'];
}

// Get monthly trends (last 6 months)
$trends_sql = "SELECT 
                 DATE_FORMAT(p.completion_date, '%Y-%m') as month,
                 SUM(p.payment_received) as revenue,
                 COUNT(p.id) as project_count
               FROM projects p
               WHERE p.completion_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
               AND p.status = 'completed' $rig_where
               GROUP BY DATE_FORMAT(p.completion_date, '%Y-%m')
               ORDER BY month DESC";
$monthly_trends = fetchAll($trends_sql);

// Save report if generating
if (isset($_POST['generate_report'])) {
    $report_sql = "INSERT INTO rig_monthly_reports 
                  (rig_id, report_month, report_year, total_revenue, total_expenses, 
                   total_profit, project_count, notes, generated_by, generated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE
                  total_revenue = VALUES(total_revenue),
                  total_expenses = VALUES(total_expenses),
                  total_profit = VALUES(total_profit),
                  project_count = VALUES(project_count),
                  notes = VALUES(notes),
                  generated_by = VALUES(generated_by),
                  generated_at = NOW()";
    
    $stmt = $conn->prepare($report_sql);
    $stmt->bind_param("iiidddisi", 
        $rig_id, $month, $year, $total_revenue, $total_expenses, 
        $total_profit, $project_count, $_POST['notes'], $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $message = "Report saved successfully!";
        $message_type = "success";
    } else {
        $message = "Error saving report: " . $stmt->error;
        $message_type = "error";
    }
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Rig Monthly Report</h1>
                <p class="text-muted mb-0"><?php echo $rig_name; ?> - <?php echo $period_label; ?></p>
            </div>
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-outline-primary">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <a href="export_rig_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">
                    <i class="bi bi-file-excel me-1"></i> Export Excel
                </a>
            </div>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Report Filters -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Rig</label>
                    <select name="rig_id" class="form-select">
                        <option value="0">All Rigs</option>
                        <?php 
                        $rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
                        foreach ($rigs as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $rig_id == $r['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['rig_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <select name="month" class="form-select">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="year" class="form-select">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter me-1"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                    <h4 class="card-title fw-bold"><?php echo formatCurrency($total_revenue); ?></h4>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Expenses</h6>
                    <h4 class="card-title fw-bold"><?php echo formatCurrency($total_expenses); ?></h4>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Net Profit</h6>
                    <h4 class="card-title fw-bold <?php echo $total_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency($total_profit); ?>
                    </h4>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Profit Margin</h6>
                    <h4 class="card-title fw-bold <?php echo $profit_margin >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($profit_margin, 2); ?>%
                    </h4>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Projects Completed</h6>
                    <h4 class="card-title fw-bold"><?php echo $project_count; ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Analysis -->
    <div class="row mb-4">
        <!-- Expense Breakdown Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expense Breakdown by Category</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="expenseChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Monthly Trend Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Revenue Trend (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Expense Breakdown -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Expense Details by Category</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($expense_by_category)): ?>
                        <p class="text-muted text-center py-4">No expenses found for this period</p>
                    <?php else: ?>
                        <?php foreach ($expense_by_category as $category => $data): 
                            $category_percentage = $total_expenses_by_category > 0 ? 
                                ($data['total'] / $total_expenses_by_category) * 100 : 0;
                        ?>
                        <div class="mb-4">
                            <h6 class="d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <span class="badge bg-primary">
                                    <?php echo formatCurrency($data['total']); ?> 
                                    (<?php echo number_format($category_percentage, 1); ?>%)
                                </span>
                            </h6>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $category_percentage; ?>%">
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <?php foreach ($data['items'] as $item): 
                                    $item_percentage = $data['total'] > 0 ? 
                                        ($item['total_amount'] / $data['total']) * 100 : 0;
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <span class="small"><?php echo htmlspecialchars($item['expense_name']); ?></span>
                                        <span class="badge bg-light text-dark ms-2"><?php echo $item['expense_count']; ?> items</span>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?php echo formatCurrency($item['total_amount']); ?></div>
                                        <small class="text-muted"><?php echo number_format($item_percentage, 1); ?>% of category</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Projects List -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Projects in <?php echo $period_label; ?></h5>
                    <span class="badge bg-primary"><?php echo $project_count; ?> Projects</span>
                </div>
                <div class="card-body">
                    <?php if (empty($projects)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-folder-x display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No projects found for this period</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Project Code</th>
                                        <th>Project Name</th>
                                        <th>Customer</th>
                                        <th>Type</th>
                                        <th>Depth</th>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Expenses</th>
                                        <th class="text-end">Profit</th>
                                        <th class="text-end">Margin</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): 
                                        $project_expenses_sql = "SELECT SUM(amount) as total FROM expenses WHERE project_id = {$project['id']}";
                                        $project_expenses_data = fetchOne($project_expenses_sql);
                                        $project_expenses = $project_expenses_data['total'] ?? 0;
                                        $project_profit = $project['payment_received'] - $project_expenses;
                                        $project_margin = $project['payment_received'] > 0 ? 
                                            ($project_profit / $project['payment_received']) * 100 : 0;
                                        
                                        $customer_name = $project['company_name'] ?: 
                                                       $project['first_name'] . ' ' . $project['last_name'];
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></td>
                                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer_name); ?></td>
                                        <td><span class="badge bg-info"><?php echo $project['project_type']; ?></span></td>
                                        <td><?php echo $project['depth']; ?>m</td>
                                        <td class="text-end"><?php echo formatCurrency($project['payment_received']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($project_expenses); ?></td>
                                        <td class="text-end fw-bold <?php echo $project_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($project_profit); ?>
                                        </td>
                                        <td class="text-end <?php echo $project_margin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($project_margin, 2); ?>%
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $project['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($project['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../projects/view_projects.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="../expenses/manage_expenses.php?project_id=<?php echo $project['id']; ?>" class="btn btn-outline-success">
                                                    <i class="bi bi-cash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <td colspan="5" class="fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($total_revenue); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($total_expenses); ?></td>
                                        <td class="text-end fw-bold <?php echo $total_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($total_profit); ?>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $profit_margin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($profit_margin, 2); ?>%
                                        </td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Save Report Form -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h5 class="card-title mb-0">Save Report</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Report Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Add any notes or comments about this report..."></textarea>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" name="generate_report" class="btn btn-primary w-100">
                                    <i class="bi bi-save me-1"></i> Save Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Expense Breakdown Chart
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    
    <?php if (!empty($expense_by_category)): ?>
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo '"' . implode('","', array_keys($expense_by_category)) . '"'; ?>],
            datasets: [{
                data: [<?php echo implode(',', array_column($expense_by_category, 'total')); ?>],
                backgroundColor: [
                    '#0d6efd', '#198754', '#ffc107', '#0dcaf0', 
                    '#6c757d', '#6610f2', '#dc3545', '#fd7e14'
                ],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += 'Ksh ' + context.parsed.toLocaleString();
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    
    <?php if (!empty($monthly_trends)): ?>
    const trendLabels = [<?php echo '"' . implode('","', array_column($monthly_trends, 'month')) . '"'; ?>].reverse();
    const trendRevenue = [<?php echo implode(',', array_column($monthly_trends, 'revenue')) ?>].reverse();
    const trendProjects = [<?php echo implode(',', array_column($monthly_trends, 'project_count')) ?>].reverse();
    
    const trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Revenue',
                data: trendRevenue,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3,
                fill: true
            }, {
                label: 'Projects',
                data: trendProjects,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (Ksh)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Ksh ' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Project Count'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label === 'Revenue') {
                                label += ': Ksh ' + context.parsed.y.toLocaleString();
                            } else {
                                label += ': ' + context.parsed.y + ' projects';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once '../../includes/footer.php'; ?>