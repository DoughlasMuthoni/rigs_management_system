<?php
// Include config.php which already includes functions.php and starts session
require_once 'config.php';

// Check if user is logged in
if (function_exists('isLoggedIn')) {
    if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
        header('Location: login.php');
        exit();
    }
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get selected month/year from GET
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;
$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // month, quarter, year, all
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 0;

// Validate month
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = $current_month;
}

// Validate year
if ($selected_year < 2020 || $selected_year > 2100) {
    $selected_year = $current_year;
}

// Get monthly performance for all rigs
if (function_exists('getAllRigsMonthlySummary')) {
    $monthly_summary = getAllRigsMonthlySummary($selected_month, $selected_year);
} else {
    // Temporary fallback data
    $monthly_summary = [
        [
            'rig_id' => 1,
            'rig_name' => 'Rig Alpha',
            'rig_code' => 'RA-001',
            'revenue' => 2500000,
            'expenses' => 1500000,
            'profit' => 1000000,
            'profit_margin' => 40.00,
            'project_count' => 5
        ],
        [
            'rig_id' => 2,
            'rig_name' => 'Rig Beta',
            'rig_code' => 'RB-002',
            'revenue' => 1800000,
            'expenses' => 1200000,
            'profit' => 600000,
            'profit_margin' => 33.33,
            'project_count' => 3
        ]
    ];
}

// Calculate totals
$total_revenue = 0;
$total_expenses = 0;
$total_profit = 0;
$total_projects = 0;

foreach ($monthly_summary as $rig) {
    $total_revenue += $rig['revenue'];
    $total_expenses += $rig['expenses'];
    $total_profit += $rig['profit'];
    $total_projects += $rig['project_count'];
}

// Get project data for chart
$chart_labels = [];
$chart_revenue = [];
$chart_expenses = [];
$chart_profit = [];

foreach ($monthly_summary as $rig) {
    $chart_labels[] = $rig['rig_name'];
    $chart_revenue[] = $rig['revenue'];
    $chart_expenses[] = $rig['expenses'];
    $chart_profit[] = $rig['profit'];
}

// SET VARIABLES NEEDED FOR HEADER.PHP
// These variables are used by header.php to build navigation
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$current_page = basename($_SERVER['PHP_SELF']); // This is 'index.php'

// Now include the header which contains all navigation
require_once 'includes/header.php';
?>

<!-- MAIN CONTENT STARTS HERE -->


<div class="container-fluid mt-4">
    <!-- Filter Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">
                    <i class="bi bi-filter me-2"></i>Performance Period Filter
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <!-- Period Type Filter -->
                    <div class="col-md-3">
                        <label for="period_filter" class="form-label">Period Type</label>
                        <select id="period_filter" name="period" class="form-select" onchange="updatePeriodFilters()">
                            <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Yearly</option>
                            <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>All Time</option>
                        </select>
                    </div>

                    <!-- Month Filter (shown for monthly) -->
                    <div class="col-md-3" id="month_filter_container" style="<?php echo $period != 'month' ? 'display:none;' : ''; ?>">
                        <label for="month_filter" class="form-label">Month</label>
                        <select id="month_filter" name="month" class="form-select">
                            <option value="0">All Months</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Quarter Filter (shown for quarterly) -->
                    <div class="col-md-3" id="quarter_filter_container" style="<?php echo $period != 'quarter' ? 'display:none;' : ''; ?>">
                        <label for="quarter_filter" class="form-label">Quarter</label>
                        <select id="quarter_filter" name="quarter" class="form-select">
                            <option value="0">All Quarters</option>
                            <option value="1" <?php echo $quarter == 1 ? 'selected' : ''; ?>>Q1 (Jan-Mar)</option>
                            <option value="2" <?php echo $quarter == 2 ? 'selected' : ''; ?>>Q2 (Apr-Jun)</option>
                            <option value="3" <?php echo $quarter == 3 ? 'selected' : ''; ?>>Q3 (Jul-Sep)</option>
                            <option value="4" <?php echo $quarter == 4 ? 'selected' : ''; ?>>Q4 (Oct-Dec)</option>
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div class="col-md-3">
                        <label for="year_filter" class="form-label">Year</label>
                        <select id="year_filter" name="year" class="form-select">
                            <option value="0">All Years</option>
                            <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="bi bi-filter me-1"></i> Apply Filter
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_revenue); ?>
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
                                Total Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_expenses); ?>
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
            <div class="card border-left-<?php echo $total_profit >= 0 ? 'success' : 'danger'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $total_profit >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Total Profit</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_profit); ?>
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
                                <?php echo $total_projects; ?>
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
    <!-- In index.php - Add this after the Summary Cards section -->

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-lightning me-2"></i>Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/customer/manage_customers.php?action=add" class="btn btn-primary w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-person-plus display-6 mb-2"></i>
                            <span>Add Customer</span>
                        </a>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/projects/add_project.php" class="btn btn-success w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-plus-circle display-6 mb-2"></i>
                            <span>Add Project</span>
                        </a>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/expenses/manage_expenses.php?action=add" class="btn btn-warning w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-receipt display-6 mb-2"></i>
                            <span>Add Expense</span>
                        </a>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/reports/rig_monthly_report.php" class="btn btn-info w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-clipboard-data display-6 mb-2"></i>
                            <span>Generate Report</span>
                        </a>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/expenses/manage_suppliers.php?action=add" class="btn btn-secondary w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-truck display-6 mb-2"></i>
                            <span>Add Supplier</span>
                        </a>
                    </div>
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <a href="modules/expenses/manage_vehicles.php?action=add" class="btn btn-dark w-100 d-flex flex-column align-items-center p-3">
                            <i class="bi bi-car-front display-6 mb-2"></i>
                            <span>Add Vehicle</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- Rig Performance Cards -->
    <div class="row mb-4">
        <?php foreach ($monthly_summary as $rig): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>)
                    </h6>
                    <span class="badge bg-<?php echo $rig['profit'] >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo $rig['profit'] >= 0 ? 'Profitable' : 'Loss'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row no-gutters">
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Revenue
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['revenue']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Expenses
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['expenses']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-<?php echo $rig['profit'] >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Profit
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['profit']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Margin
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo number_format($rig['profit_margin'], 2); ?>%
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Projects
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo $rig['project_count']; ?> Completed
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
    <div class="d-flex justify-content-between">
        <?php if ($rig['rig_id'] == 0): ?>
            <!-- For unassigned projects -->
            <a href="modules/projects/view_projects.php?unassigned=1&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>View Projects
            </a>
            <a href="reports/monthly_summary.php?unassigned=1&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
               class="btn btn-sm btn-outline-info">
                <i class="bi bi-graph-up me-1"></i>Report
            </a>
        <?php else: ?>
            <!-- For rig-assigned projects -->
            <a href="modules/projects/view_projects.php?rig=<?php echo $rig['rig_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>View Projects
            </a>
            <a href="reports/monthly_summary.php?rig=<?php echo $rig['rig_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
               class="btn btn-sm btn-outline-info">
                <i class="bi bi-graph-up me-1"></i>Report
            </a>
        <?php endif; ?>
    </div>
</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php 
                        if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                            echo date('F Y', strtotime("$selected_year-$selected_month-01")) . " Performance Comparison";
                        } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                            echo "Q$quarter $year Performance Comparison";
                        } elseif ($period == 'year' && $selected_year > 0) {
                            echo "Year $selected_year Performance Comparison";
                        } elseif ($period == 'all') {
                            echo "All Time Performance Comparison";
                        } else {
                            echo "Performance Comparison";
                        }
                        ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="performanceChart" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- In index.php - Add these sections after the Chart Section -->

<!-- Recent Customers & Expenses -->
<div class="row mb-4">
    <!-- Recent Customers -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-people-fill me-2"></i>Recent Customers
                </h6>
                <a href="modules/customer/manage_customers.php" class="btn btn-sm btn-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php 
                // Get recent customers
                if (function_exists('getAllCustomers')) {
                    $recent_customers = getAllCustomers('active');
                    $recent_customers = array_slice($recent_customers, 0, 5);
                } else {
                    $recent_customers = [];
                }
                ?>
                
                <?php if (!empty($recent_customers)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_customers as $customer): 
                            // Get customer's project count
                            $project_count = 0;
                            if (function_exists('getCustomerProjects')) {
                                $projects = getCustomerProjects($customer['id']);
                                $project_count = count($projects);
                            }
                        ?>
                        <a href="modules/customer/manage_customers.php?action=view&id=<?php echo $customer['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    <?php if ($customer['company_name']): ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($customer['company_name']); ?>)</small>
                                    <?php endif; ?>
                                </h6>
                                <span class="badge bg-info"><?php echo $project_count; ?> projects</span>
                            </div>
                            <p class="mb-1 small">
                                <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?>
                                <?php if ($customer['email']): ?>
                                    <br><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?>
                                <?php endif; ?>
                            </p>
                            <small class="text-muted">
                                Customer since: <?php echo date('d/m/Y', strtotime($customer['created_at'])); ?>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-people display-4 text-muted"></i>
                        <p class="text-muted mt-2 mb-0">No customers found</p>
                        <a href="modules/customer/manage_customers.php?action=add" class="btn btn-primary btn-sm mt-3">
                            <i class="bi bi-person-plus me-1"></i>Add First Customer
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<!-- Replace lines 318-350 with: -->
<!-- Recent Expenses - SIMPLIFIED to match working version -->
<div class="col-lg-6 mb-4">
    <div class="card shadow h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-warning">
                <i class="bi bi-cash-stack me-2"></i>Recent Expenses
            </h6>
            <div class="btn-group">
                <a href="modules/expenses/manage_expenses.php" class="btn btn-sm btn-warning">
                    View All
                </a>
                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="dropdown">
                    <i class="bi bi-filter"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="index.php?period=all">All Time</a></li>
                    <li><a class="dropdown-item" href="index.php?period=month&month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>">This Month</a></li>
                    <li><a class="dropdown-item" href="index.php?period=quarter&quarter=<?php echo ceil(date('m')/3); ?>&year=<?php echo date('Y'); ?>">This Quarter</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php 
            // Option 1: Use the SIMPLE query that works
            if (function_exists('fetchAll')) {
                // Use the exact same query that works in the other file
                $all_expenses = fetchAll("SELECT e.*, p.project_code, et.expense_name, r.rig_name, s.supplier_name
                                        FROM expenses e
                                        JOIN projects p ON e.project_id = p.id
                                        JOIN expense_types et ON e.expense_type_id = et.id
                                        LEFT JOIN rigs r ON p.rig_id = r.id
                                        LEFT JOIN suppliers s ON e.supplier_id = s.id
                                        ORDER BY e.created_at DESC");
                
                // Apply date filtering manually
                $filtered_expenses = [];
                foreach ($all_expenses as $expense) {
                    $expense_date = strtotime($expense['expense_date']);
                    
                    if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                        $month_start = strtotime("$selected_year-$selected_month-01");
                        $month_end = strtotime(date('Y-m-t', $month_start));
                        if ($expense_date >= $month_start && $expense_date <= $month_end) {
                            $filtered_expenses[] = $expense;
                        }
                    } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                        $start_month = (($quarter - 1) * 3) + 1;
                        $end_month = $start_month + 2;
                        $quarter_start = strtotime("$selected_year-$start_month-01");
                        $quarter_end = strtotime(date('Y-m-t', strtotime("$selected_year-$end_month-01")));
                        if ($expense_date >= $quarter_start && $expense_date <= $quarter_end) {
                            $filtered_expenses[] = $expense;
                        }
                    } elseif ($period == 'year' && $selected_year > 0) {
                        $year_start = strtotime("$selected_year-01-01");
                        $year_end = strtotime("$selected_year-12-31");
                        if ($expense_date >= $year_start && $expense_date <= $year_end) {
                            $filtered_expenses[] = $expense;
                        }
                    } elseif ($period == 'all') {
                        $filtered_expenses = $all_expenses;
                        break;
                    }
                }
                
                $recent_expenses = array_slice($filtered_expenses, 0, 5);
                $total_expenses_period = array_sum(array_column($filtered_expenses, 'amount'));
            } else {
                $recent_expenses = [];
                $filtered_expenses = [];
                $total_expenses_period = 0;
            }
            ?>
            
            <!-- Period Summary -->
            <div class="alert alert-warning alert-sm mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>
                            <?php 
                            if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                                echo date('F Y', strtotime("$selected_year-$selected_month-01"));
                            } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                                echo "Q$quarter $selected_year";
                            } elseif ($period == 'year' && $selected_year > 0) {
                                echo "Year $selected_year";
                            } else {
                                echo "All Time";
                            }
                            ?>
                        </strong>
                    </div>
                    <div>
                        <span class="fw-bold"><?php echo formatCurrency($total_expenses_period); ?></span>
                        <small class="text-muted ms-1">(<?php echo count($filtered_expenses); ?> items)</small>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($recent_expenses)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_expenses as $expense): ?>
                    <a href="modules/expenses/manage_expenses.php?action=edit&id=<?php echo $expense['id']; ?>" 
                       class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($expense['expense_code']); ?></span>
                                <?php echo htmlspecialchars($expense['expense_name']); ?>
                            </h6>
                            <span class="fw-bold"><?php echo formatCurrency($expense['amount']); ?></span>
                        </div>
                        <p class="mb-1 small">
                            <i class="bi bi-folder me-1"></i>
                            <strong><?php echo htmlspecialchars($expense['project_code'] ?? 'No Project'); ?></strong>
                            <?php if (!empty($expense['supplier_name']) && $expense['supplier_name'] != 'No Supplier'): ?>
                                <br><i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($expense['supplier_name']); ?>
                            <?php endif; ?>
                            <?php if (!empty($expense['rig_name']) && $expense['rig_name'] != 'No Rig'): ?>
                                <br><i class="bi bi-gear me-1"></i><?php echo htmlspecialchars($expense['rig_name']); ?>
                            <?php endif; ?>
                        </p>
                        <small class="text-muted">
                            <i class="bi bi-calendar me-1"></i><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?> | 
                            Status: <span class="badge bg-<?php 
                                echo $expense['status'] == 'paid' ? 'success' : 
                                     ($expense['status'] == 'approved' ? 'primary' : 
                                     ($expense['status'] == 'rejected' ? 'danger' : 'warning')); 
                            ?>"><?php echo ucfirst($expense['status']); ?></span>
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($filtered_expenses) > 5): ?>
                <div class="text-center mt-3">
                    <a href="modules/expenses/manage_expenses.php<?php 
                        if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                            echo "?month=$selected_month&year=$selected_year";
                        } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                            echo "?quarter=$quarter&year=$selected_year";
                        } elseif ($period == 'year' && $selected_year > 0) {
                            echo "?year=$selected_year";
                        }
                    ?>" class="btn btn-outline-warning btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> View All Expenses
                    </a>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-receipt display-4 text-muted"></i>
                    <p class="text-muted mt-2 mb-0">No expenses found for this period</p>
                    
                    <!-- Show total expenses in database for debugging -->
                    <?php 
                    $total_in_db = fetchOne("SELECT COUNT(*) as count FROM expenses");
                    if ($total_in_db && $total_in_db['count'] > 0): 
                    ?>
                    <div class="alert alert-info mt-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        Database has <?php echo $total_in_db['count']; ?> total expenses.
                        Try changing period filter or check expense dates.
                    </div>
                    <?php endif; ?>
                    
                    <a href="modules/expenses/manage_expenses.php?action=add" class="btn btn-warning btn-sm mt-3">
                        <i class="bi bi-plus-lg me-1"></i>Add First Expense
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <!-- Customer Statistics -->
    <div class="col-md-4 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Total Customers
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            if (function_exists('getAllCustomers')) {
                                $all_customers = getAllCustomers();
                                echo count($all_customers);
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <?php 
                            if (function_exists('getAllCustomers')) {
                                $active_customers = getAllCustomers('active');
                                echo '<span class="text-success mr-2">' . count($active_customers) . ' active</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
   <!-- Expense Statistics -->
<div class="col-md-4 mb-4">
    <div class="card border-left-warning shadow h-100 py-2">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        <?php 
                        if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                            echo date('F Y', strtotime("$selected_year-$selected_month-01")) . " Expenses";
                        } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                            echo "Q$quarter $selected_year Expenses";
                        } elseif ($period == 'year' && $selected_year > 0) {
                            echo "Year $selected_year Expenses";
                        } else {
                            echo "Total Expenses";
                        }
                        ?>
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                        <?php 
                        if (function_exists('generateExpenseReport')) {
                            $expense_filters = [];
                            
                            if ($period == 'month' && $selected_month > 0 && $selected_year > 0) {
                                $expense_filters['start_date'] = date('Y-m-01', strtotime("$selected_year-$selected_month-01"));
                                $expense_filters['end_date'] = date('Y-m-t', strtotime("$selected_year-$selected_month-01"));
                            } elseif ($period == 'quarter' && $quarter > 0 && $selected_year > 0) {
                                $start_month = (($quarter - 1) * 3) + 1;
                                $end_month = $start_month + 2;
                                $expense_filters['start_date'] = date('Y-m-01', strtotime("$selected_year-$start_month-01"));
                                $expense_filters['end_date'] = date('Y-m-t', strtotime("$selected_year-$end_month-01"));
                            } elseif ($period == 'year' && $selected_year > 0) {
                                $expense_filters['start_date'] = date('Y-01-01', strtotime("$selected_year-01-01"));
                                $expense_filters['end_date'] = date('Y-12-31', strtotime("$selected_year-12-31"));
                            }
                            
                            $monthly_expenses = generateExpenseReport($expense_filters);
                            $total_monthly_expenses = array_sum(array_column($monthly_expenses, 'amount'));
                            echo formatCurrency($total_monthly_expenses);
                        } else {
                            echo formatCurrency(0);
                        }
                        ?>
                    </div>
                    <div class="mt-2 mb-0 text-muted text-xs">
                        <span class="text-warning mr-2">
                            <?php 
                            if (function_exists('generateExpenseReport')) {
                                echo count($monthly_expenses ?? []) . ' expense items';
                            } else {
                                echo '0 items';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div class="col-auto">
                    <i class="bi bi-cash-stack fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
    
    <!-- Project Statistics -->
    <div class="col-md-4 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Active Projects
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php 
                            if (function_exists('getCustomerProjects')) {
                                // This is a simplified version - you might want a different query
                                $active_projects = fetchAll("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
                                echo $active_projects[0]['count'] ?? 0;
                            } else {
                                echo '0';
                            }
                            ?>
                        </div>
                        <div class="mt-2 mb-0 text-muted text-xs">
                            <?php 
                            $current_month_projects = fetchAll("SELECT COUNT(*) as count FROM projects 
                                                                WHERE MONTH(completion_date) = $selected_month 
                                                                AND YEAR(completion_date) = $selected_year");
                            if ($current_month_projects) {
                                echo '<span class="text-success mr-2">' . $current_month_projects[0]['count'] . ' this month</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-clipboard-data fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Revenue',
                data: <?php echo json_encode($chart_revenue); ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1
            },
            {
                label: 'Expenses',
                data: <?php echo json_encode($chart_expenses); ?>,
                backgroundColor: 'rgba(246, 194, 62, 0.7)',
                borderColor: 'rgba(246, 194, 62, 1)',
                borderWidth: 1
            },
            {
                label: 'Profit',
                data: <?php echo json_encode($chart_profit); ?>,
                backgroundColor: 'rgba(28, 200, 138, 0.7)',
                borderColor: 'rgba(28, 200, 138, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Ksh ' + value.toLocaleString();
                    }
                },
                grid: {
                    drawBorder: false,
                    color: 'rgba(0, 0, 0, 0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += 'Ksh ' + context.parsed.y.toLocaleString();
                        return label;
                    }
                }
            }
        }
    }
});
// Update period filter visibility
function updatePeriodFilters() {
    const periodType = document.getElementById('period_filter').value;
    
    // Hide all filter containers first
    document.getElementById('month_filter_container').style.display = 'none';
    document.getElementById('quarter_filter_container').style.display = 'none';
    
    // Show relevant filter
    if (periodType === 'month') {
        document.getElementById('month_filter_container').style.display = 'block';
    } else if (periodType === 'quarter') {
        document.getElementById('quarter_filter_container').style.display = 'block';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set initial period filter state
    updatePeriodFilters();
    
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php 
// Include footer
require_once 'includes/footer.php';
?>