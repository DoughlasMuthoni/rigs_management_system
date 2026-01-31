<?php
// expense_report.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$rig_id = isset($_GET['rig_id']) ? intval($_GET['rig_id']) : 0;
$category = isset($_GET['category']) ? escape($_GET['category']) : '';
$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

// Build SQL conditions
$conditions = [];
$params = [];

if ($start_date && $end_date) {
    $conditions[] = "e.expense_date BETWEEN '$start_date' AND '$end_date'";
}

if ($project_id > 0) {
    $conditions[] = "e.project_id = $project_id";
}

if ($rig_id > 0) {
    // Get projects for this rig
    $project_ids = fetchAll("SELECT id FROM projects WHERE rig_id = $rig_id");
    if (!empty($project_ids)) {
        $ids = array_column($project_ids, 'id');
        $conditions[] = "e.project_id IN (" . implode(',', $ids) . ")";
    }
}

if ($category) {
    $conditions[] = "et.category = '$category'";
}

if ($supplier_id > 0) {
    $conditions[] = "e.supplier_id = $supplier_id";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT e.project_id) as total_projects,
    COUNT(DISTINCT e.supplier_id) as total_suppliers,
    SUM(e.amount) as total_amount,
    AVG(e.amount) as average_expense,
    MAX(e.amount) as max_expense,
    MIN(e.amount) as min_expense,
    COUNT(*) as total_records
FROM expenses e
$where_clause";

$summary = fetchOne($summary_sql);

// Get expense trends by month
$trends_sql = "SELECT 
    DATE_FORMAT(e.expense_date, '%Y-%m') as month,
    SUM(e.amount) as total_amount,
    COUNT(*) as expense_count
FROM expenses e
WHERE e.expense_date BETWEEN '$start_date' AND '$end_date'
GROUP BY DATE_FORMAT(e.expense_date, '%Y-%m')
ORDER BY month";

$trends = fetchAll($trends_sql);

// Get top categories
$categories_sql = "SELECT 
    et.category,
    SUM(e.amount) as total_amount,
    COUNT(*) as expense_count,
    ROUND((SUM(e.amount) / (SELECT SUM(amount) FROM expenses e2 $where_clause)) * 100, 2) as percentage
FROM expenses e
JOIN expense_types et ON e.expense_type_id = et.id
$where_clause
GROUP BY et.category
ORDER BY total_amount DESC";

$categories = fetchAll($categories_sql);

// Get top suppliers
$suppliers_sql = "SELECT 
    s.supplier_name,
    SUM(e.amount) as total_amount,
    COUNT(*) as expense_count,
    ROUND(AVG(e.amount), 2) as average_amount
FROM expenses e
LEFT JOIN suppliers s ON e.supplier_id = s.id
$where_clause
GROUP BY s.supplier_name
ORDER BY total_amount DESC
LIMIT 10";

$top_suppliers = fetchAll($suppliers_sql);

// Get expense details
// Get expense details
$expenses_sql = "SELECT 
    e.*,
    p.project_code,
    p.project_name,
    r.rig_name,
    et.category,
    et.expense_name,
    s.supplier_name,
    s.contact_person,
    s.phone,
    s.email,
    DATE_FORMAT(e.expense_date, '%d/%m/%Y') as formatted_date,
    CASE 
        WHEN e.ref_number = 'MONTHLY-SALARY' THEN 'Monthly Allocation'
        ELSE 'Direct Expense'
    END as expense_type
FROM expenses e
LEFT JOIN projects p ON e.project_id = p.id
LEFT JOIN rigs r ON p.rig_id = r.id
LEFT JOIN expense_types et ON e.expense_type_id = et.id
LEFT JOIN suppliers s ON e.supplier_id = s.id
$where_clause
ORDER BY e.expense_date DESC, e.amount DESC
LIMIT 1000";

$expenses = fetchAll($expenses_sql);

// Get filter options
$projects = fetchAll("SELECT id, project_code, project_name FROM projects ORDER BY project_code");
$rigs = fetchAll("SELECT id, rig_name FROM rigs WHERE status = 'active' ORDER BY rig_name");
$suppliers = fetchAll("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name");
$expense_categories = fetchAll("SELECT DISTINCT category FROM expense_types ORDER BY category");

// Calculate additional analytics
$daily_average = 0;
if ($summary && $summary['total_amount'] > 0) {
    $days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1;
    $daily_average = $summary['total_amount'] / max($days, 1);
}

// Cost efficiency metrics (if projects selected)
$efficiency_metrics = [];
if ($project_id > 0 || $rig_id > 0) {
    $efficiency_sql = "SELECT 
        p.project_code,
        p.project_name,
        p.payment_received,
        COALESCE(SUM(e.amount), 0) as total_expenses,
        COALESCE(SUM(e.amount) / NULLIF(p.payment_received, 0), 0) as cost_ratio,
        CASE 
            WHEN p.payment_received > 0 
            THEN (p.payment_received - COALESCE(SUM(e.amount), 0)) / p.payment_received * 100
            ELSE 0 
        END as profit_margin
    FROM projects p
    LEFT JOIN expenses e ON p.id = e.project_id
    WHERE p.id IN (
        SELECT DISTINCT project_id FROM expenses 
        $where_clause
    )
    GROUP BY p.id, p.project_code, p.project_name, p.payment_received
    HAVING total_expenses > 0
    ORDER BY cost_ratio DESC";
    
    $efficiency_metrics = fetchAll($efficiency_sql);
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header bg-white rounded shadow-sm p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold text-primary mb-2">
                    <i class="bi bi-graph-up me-2"></i>Advanced Expense Analysis
                </h1>
                <p class="lead text-muted mb-0">
                    Deep insights into expense patterns and financial performance
                </p>
                <p class="text-muted small">
                    Period: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>
                    <?php if ($project_id > 0): ?>
                        | Project: <?php echo $projects[array_search($project_id, array_column($projects, 'id'))]['project_code'] ?? 'N/A'; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print Report
                </button>
                <button class="btn btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
                </button>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-filter me-2"></i>Report Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo $p['id']; ?>" 
                                <?php echo $project_id == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo $p['project_code']; ?> - <?php echo substr($p['project_name'], 0, 30); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Rig</label>
                    <select name="rig_id" class="form-select">
                        <option value="0">All Rigs</option>
                        <?php foreach ($rigs as $rig): ?>
                            <option value="<?php echo $rig['id']; ?>" 
                                <?php echo $rig_id == $rig['id'] ? 'selected' : ''; ?>>
                                <?php echo $rig['rig_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($expense_categories as $cat): ?>
                            <option value="<?php echo $cat['category']; ?>" 
                                <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo $cat['category']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-bold">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="0">All Suppliers</option>
                        <?php foreach ($suppliers as $supp): ?>
                            <option value="<?php echo $supp['id']; ?>" 
                                <?php echo $supplier_id == $supp['id'] ? 'selected' : ''; ?>>
                                <?php echo $supp['supplier_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-search me-1"></i>Apply Filters
                        </button>
                        <a href="expense_report.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </a>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#analysisModal">
                            <i class="bi bi-lightbulb me-1"></i>AI Insights
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Executive Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-primary mb-2">
                        <?php echo formatCurrency($summary['total_amount'] ?? 0); ?>
                    </div>
                    <p class="text-muted mb-0">Total Expenses</p>
                    <small class="text-muted">
                        <i class="bi bi-calendar me-1"></i>
                        Daily Avg: <?php echo formatCurrency($daily_average); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-success shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-success mb-2">
                        <?php echo $summary['total_records'] ?? 0; ?>
                    </div>
                    <p class="text-muted mb-0">Expense Records</p>
                    <small class="text-muted">
                        <i class="bi bi-coin me-1"></i>
                        Avg: <?php echo formatCurrency($summary['average_expense'] ?? 0); ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-info shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-info mb-2">
                        <?php echo $summary['total_projects'] ?? 0; ?>
                    </div>
                    <p class="text-muted mb-0">Projects Involved</p>
                    <small class="text-muted">
                        <i class="bi bi-building me-1"></i>
                        <?php echo $summary['total_suppliers'] ?? 0; ?> Suppliers
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-warning mb-2">
                        <?php echo formatCurrency($summary['max_expense'] ?? 0); ?>
                    </div>
                    <p class="text-muted mb-0">Largest Expense</p>
                    <small class="text-muted">
                        <i class="bi bi-arrow-up me-1"></i>
                        Range: <?php echo formatCurrency($summary['min_expense'] ?? 0); ?> - <?php echo formatCurrency($summary['max_expense'] ?? 0); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Expense Trends Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Expense Trends
                    </h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Categories -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-pie-chart me-2"></i>Expense Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Analysis -->
    <div class="row mb-4">
        <!-- Category Breakdown -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-tags me-2"></i>Category Analysis
                    </h5>
                    <span class="badge bg-primary">Top <?php echo count($categories); ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">% of Total</th>
                                    <th class="text-end">Avg/Item</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-info"><?php echo $cat['category']; ?></span>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($cat['total_amount']); ?></td>
                                    <td class="text-end"><?php echo $cat['expense_count']; ?></td>
                                    <td class="text-end">
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" 
                                                 style="width: <?php echo $cat['percentage']; ?>%">
                                            </div>
                                        </div>
                                        <small><?php echo $cat['percentage']; ?>%</small>
                                    </td>
                                    <td class="text-end">
                                        <?php echo formatCurrency($cat['total_amount'] / max($cat['expense_count'], 1)); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Suppliers -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-truck me-2"></i>Supplier Analysis
                    </h5>
                    <span class="badge bg-primary">Top 10</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Supplier</th>
                                    <th class="text-end">Total Spent</th>
                                    <th class="text-end">Transactions</th>
                                    <th class="text-end">Avg/Transaction</th>
                                    <th>Frequency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_suppliers as $supplier): ?>
                                <?php 
                                $frequency_class = '';
                                if ($supplier['expense_count'] > 20) $frequency_class = 'bg-success';
                                elseif ($supplier['expense_count'] > 10) $frequency_class = 'bg-warning';
                                else $frequency_class = 'bg-info';
                                ?>
                                <tr>
                                    <td><?php echo $supplier['supplier_name']; ?></td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($supplier['total_amount']); ?></td>
                                    <td class="text-end"><?php echo $supplier['expense_count']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($supplier['average_amount']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $frequency_class; ?>">
                                            <?php echo $supplier['expense_count']; ?> trans
                                        </span>
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
    
    <!-- Cost Efficiency Metrics -->
    <?php if (!empty($efficiency_metrics)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-speedometer2 me-2"></i>Cost Efficiency Metrics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Project</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Expenses</th>
                                    <th class="text-end">Cost Ratio</th>
                                    <th class="text-end">Profit Margin</th>
                                    <th>Efficiency</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($efficiency_metrics as $metric): ?>
                                <?php 
                                $ratio_class = $metric['cost_ratio'] < 0.7 ? 'bg-success' : 
                                              ($metric['cost_ratio'] < 0.9 ? 'bg-warning' : 'bg-danger');
                                $margin_class = $metric['profit_margin'] > 30 ? 'bg-success' : 
                                               ($metric['profit_margin'] > 15 ? 'bg-warning' : 'bg-danger');
                                $efficiency = '';
                                if ($metric['cost_ratio'] < 0.7 && $metric['profit_margin'] > 30) {
                                    $efficiency = 'Excellent';
                                    $eff_class = 'success';
                                } elseif ($metric['cost_ratio'] < 0.8 && $metric['profit_margin'] > 20) {
                                    $efficiency = 'Good';
                                    $eff_class = 'primary';
                                } elseif ($metric['cost_ratio'] < 0.9 && $metric['profit_margin'] > 10) {
                                    $efficiency = 'Average';
                                    $eff_class = 'warning';
                                } else {
                                    $efficiency = 'Needs Attention';
                                    $eff_class = 'danger';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $metric['project_code']; ?></strong><br>
                                        <small class="text-muted"><?php echo $metric['project_name']; ?></small>
                                    </td>
                                    <td class="text-end"><?php echo formatCurrency($metric['payment_received']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($metric['total_expenses']); ?></td>
                                    <td class="text-end">
                                        <span class="badge <?php echo $ratio_class; ?>">
                                            <?php echo number_format($metric['cost_ratio'] * 100, 1); ?>%
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge <?php echo $margin_class; ?>">
                                            <?php echo number_format($metric['profit_margin'], 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $eff_class; ?>">
                                            <?php echo $efficiency; ?>
                                        </span>
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
    <?php endif; ?>
    
    <!-- Detailed Expense List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Detailed Expense List
                    </h5>
                    <span class="badge bg-primary"><?php echo count($expenses); ?> records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Project</th>
                                    <th>Rig</th>
                                    <th>Category</th>
                                    <th>Expense Type</th>
                                    <th>Description</th>
                                    <th>Supplier</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo $expense['formatted_date']; ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $expense['project_code']; ?></span>
                                    </td>
                                    <td><?php echo $expense['rig_name'] ?? 'N/A'; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $expense['category']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $expense['expense_type'] == 'Monthly Allocation' ? 'success' : 'primary'; ?>">
                                            <?php echo $expense['expense_type']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo substr($expense['expense_name'], 0, 30); ?>
                                        <?php if (!empty($expense['notes'])): ?>
                                            <br><small class="text-muted"><?php echo substr($expense['notes'], 0, 50); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $expense['supplier_name'] ?? 'N/A'; ?>
                                        <?php if (!empty($expense['contact_person'])): ?>
                                            <br><small class="text-muted">Contact: <?php echo $expense['contact_person']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($expense['amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $expense['status'] == 'approved' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($expense['status']); ?>
                                        </span>
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
</main>

<!-- AI Insights Modal -->
<div class="modal fade" id="analysisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-robot me-2"></i>AI-Powered Expense Insights
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Key Insights:</strong> Based on your expense data analysis
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-currency-dollar text-success me-2"></i>Cost Optimization
                                </h6>
                                <ul class="small">
                                    <li>Top 3 expense categories account for 
                                        <?php 
                                        $top3 = array_slice($categories, 0, 3);
                                        $top3_percent = 0;
                                        foreach ($top3 as $cat) {
                                            $top3_percent += $cat['percentage'];
                                        }
                                        echo number_format($top3_percent, 1);
                                        ?>% of total expenses
                                    </li>
                                    <li>Consider bulk purchasing for high-frequency items</li>
                                    <li>Review supplier contracts for potential savings</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-graph-up-arrow text-primary me-2"></i>Trend Analysis
                                </h6>
                                <ul class="small">
                                    <li>Expense trend is 
                                        <?php 
                                        if (count($trends) >= 2) {
                                            $first = $trends[0]['total_amount'] ?? 0;
                                            $last = $trends[count($trends)-1]['total_amount'] ?? 0;
                                            $change = $last > 0 ? (($first - $last) / $last) * 100 : 0;
                                            $trend = $change > 0 ? 'increasing' : ($change < 0 ? 'decreasing' : 'stable');
                                            echo $trend . ' by ' . number_format(abs($change), 1) . '%';
                                        } else {
                                            echo 'stable';
                                        }
                                        ?>
                                    </li>
                                    <li>Monitor monthly expense growth</li>
                                    <li>Seasonal patterns may exist</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-people text-warning me-2"></i>Supplier Management
                                </h6>
                                <ul class="small">
                                    <li>Top supplier accounts for 
                                        <?php 
                                        $top_supplier_percent = $top_suppliers[0]['total_amount'] ?? 0 > 0 ? 
                                            ($top_suppliers[0]['total_amount'] / $summary['total_amount']) * 100 : 0;
                                        echo number_format($top_supplier_percent, 1);
                                        ?>% of expenses
                                    </li>
                                    <li>Consider diversifying suppliers</li>
                                    <li>Negotiate better terms with top suppliers</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="bi bi-shield-check text-danger me-2"></i>Risk Assessment
                                </h6>
                                <ul class="small">
                                    <li>
                                        <?php 
                                        $high_expenses = array_filter($expenses, function($e) use ($summary) {
                                            return $e['amount'] > ($summary['average_expense'] * 2);
                                        });
                                        echo count($high_expenses) . ' high-value expenses detected';
                                        ?>
                                    </li>
                                    <li>Review expense approval process</li>
                                    <li>Implement spending limits if needed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <button class="btn btn-outline-info">
                        <i class="bi bi-download me-1"></i>Generate Detailed Analysis PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
<script>
// Register the plugin
Chart.register(ChartDataLabels);

document.addEventListener('DOMContentLoaded', function() {
    // Trends Chart (Line)
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: [<?php 
                foreach ($trends as $trend) {
                    echo "'" . date('M Y', strtotime($trend['month'] . '-01')) . "',";
                }
            ?>],
            datasets: [{
                label: 'Expense Amount (Ksh)',
                data: [<?php foreach ($trends as $trend) echo $trend['total_amount'] . ','; ?>],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }, {
                label: 'Expense Count',
                data: [<?php foreach ($trends as $trend) echo $trend['expense_count'] . ','; ?>],
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount (Ksh)'
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
                        text: 'Count'
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
                            if (label.includes('Amount')) {
                                label += ': Ksh ' + context.parsed.y.toLocaleString();
                            } else {
                                label += ': ' + context.parsed.y;
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Categories Chart (Pie with Doughnut)
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    const categoriesChart = new Chart(categoriesCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach ($categories as $cat) echo "'" . addslashes($cat['category']) . "',"; ?>],
            datasets: [{
                data: [<?php foreach ($categories as $cat) echo $cat['total_amount'] . ','; ?>],
                backgroundColor: [
                    '#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d',
                    '#20c997', '#fd7e14', '#e83e8c', '#6610f2', '#6f42c1'
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
                    position: 'right',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label;
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: Ksh ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        weight: 'bold',
                        size: 12
                    },
                    formatter: function(value, context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                        return percentage >= 5 ? percentage + '%' : '';
                    }
                }
            }
        }
    });
});

function exportToExcel() {
    // Collect filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Create form for download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_expense_report.php';
    
    // Add all parameters as hidden inputs
    params.forEach((value, key) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });
    
    // Add current date
    const dateInput = document.createElement('input');
    dateInput.type = 'hidden';
    dateInput.name = 'export_date';
    dateInput.value = new Date().toISOString();
    form.appendChild(dateInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<style>
.page-header {
    background: linear-gradient(135deg, #080c1d 0%, #781ed3 10%);
    color: white;
}

.page-header h1, .page-header p {
    color: white !important;
}

.card {
    border: none;
    border-radius: 10px;
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.card-header {
    border-bottom: 1px solid rgba(0,0,0,.125);
    border-radius: 10px 10px 0 0 !important;
    font-weight: 600;
}

.table th {
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    padding: 4px 8px;
    font-size: 0.75rem;
}

.progress {
    height: 8px;
    border-radius: 4px;
    margin-top: 4px;
}

.progress-bar {
    border-radius: 4px;
}

.modal-header {
    border-bottom: none;
}

.modal-content {
    border-radius: 10px;
}

@media print {
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn, .modal, .card-header .badge {
        display: none !important;
    }
    
    .page-header {
        background: white !important;
        color: black !important;
    }
    
    .page-header h1, .page-header p {
        color: black !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>