<?php
// customer_report.php - Professional Customer Analysis Report
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customer_type_filter = isset($_GET['customer_type']) ? escape($_GET['customer_type']) : '';
$min_projects = isset($_GET['min_projects']) ? intval($_GET['min_projects']) : 0;
$min_revenue = isset($_GET['min_revenue']) ? floatval($_GET['min_revenue']) : 0;

// Build WHERE clause
$where_clause = "";
$conditions = [];

if ($start_date && $end_date) {
    $conditions[] = "(p.completion_date IS NOT NULL AND p.completion_date BETWEEN '" . escape($start_date) . "' AND '" . escape($end_date) . "')";
}

if ($customer_type_filter == 'corporate') {
    $conditions[] = "c.company_name IS NOT NULL AND c.company_name != ''";
} elseif ($customer_type_filter == 'individual') {
    $conditions[] = "(c.company_name IS NULL OR c.company_name = '')";
}

if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

// ==================== CORE ANALYSIS QUERIES ====================

// 1. Customer Performance Summary
$summary_sql = "SELECT 
    COUNT(DISTINCT c.id) as total_customers,
    COUNT(DISTINCT p.id) as total_projects,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    COALESCE(AVG(p.payment_received), 0) as avg_project_value,
    COALESCE(MAX(p.payment_received), 0) as max_project_value,
    COALESCE(MIN(p.payment_received), 0) as min_project_value,
    COUNT(DISTINCT CASE WHEN p.rig_id IS NOT NULL AND p.rig_id != 0 THEN p.rig_id END) as rigs_utilized
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause";

$summary = fetchOne($summary_sql);

// 2. Customer Segmentation Analysis
$segmentation_sql = "SELECT 
    CASE 
        WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
        ELSE 'Individual'
    END as customer_type,
    COUNT(DISTINCT c.id) as customer_count,
    COUNT(DISTINCT p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    ROUND(COALESCE(AVG(p.payment_received), 0), 2) as avg_revenue_per_customer,
    ROUND(COALESCE(AVG(p.payment_received / NULLIF(p.depth, 0)), 0), 2) as avg_revenue_per_meter
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY CASE 
    WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
    ELSE 'Individual'
END
ORDER BY total_revenue DESC";

$segmentation = fetchAll($segmentation_sql);

// 3. Top Performing Customers (Pareto Analysis)
$top_customers_sql = "SELECT 
    c.id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.company_name,
    c.city,
    c.country,
    c.phone,
    c.email,
    c.customer_code,
    CASE 
        WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
        ELSE 'Individual'
    END as customer_type,
    COUNT(p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    COALESCE(SUM(e.amount), 0) as total_expenses,
    COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) as total_profit,
    ROUND(COALESCE(AVG(p.payment_received), 0), 2) as avg_project_size,
    MAX(p.completion_date) as last_project_date,
    DATEDIFF(NOW(), MAX(p.completion_date)) as days_since_last_project
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
LEFT JOIN expenses e ON p.id = e.project_id
$where_clause
GROUP BY c.id, c.first_name, c.last_name, c.company_name, c.city, c.country, c.phone, c.email, c.customer_code
HAVING total_revenue >= $min_revenue AND project_count >= $min_projects
ORDER BY total_revenue DESC
LIMIT 50";

$top_customers = fetchAll($top_customers_sql);

// 4. Customer Lifetime Value Analysis
$clv_sql = "SELECT 
    c.id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.company_name,
    c.customer_code,
    MIN(p.completion_date) as first_project_date,
    MAX(p.completion_date) as last_project_date,
    COUNT(DISTINCT YEAR(p.completion_date)) as active_years,
    COUNT(p.id) as total_projects,
    COALESCE(SUM(p.payment_received), 0) as lifetime_value,
    ROUND(COALESCE(SUM(p.payment_received) / NULLIF(COUNT(DISTINCT YEAR(p.completion_date)), 0), 0), 2) as annual_value,
    ROUND(COALESCE(SUM(p.payment_received) / NULLIF(COUNT(p.id), 0), 0), 2) as avg_value_per_project,
    CASE 
        WHEN DATEDIFF(NOW(), MAX(p.completion_date)) <= 90 THEN 'Active'
        WHEN DATEDIFF(NOW(), MAX(p.completion_date)) <= 180 THEN 'At Risk'
        ELSE 'Inactive'
    END as engagement_status
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY c.id, c.first_name, c.last_name, c.company_name, c.customer_code
HAVING lifetime_value > 0
ORDER BY lifetime_value DESC";

$clv_analysis = fetchAll($clv_sql);

// 5. Project Frequency Analysis
$frequency_sql = "SELECT 
    c.id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.company_name,
    COUNT(p.id) as total_projects,
    COUNT(DISTINCT YEAR(p.completion_date)) as years_active,
    ROUND(COUNT(p.id) / NULLIF(COUNT(DISTINCT YEAR(p.completion_date)), 1), 2) as projects_per_year,
    CASE 
        WHEN COUNT(p.id) >= 5 THEN 'High Frequency'
        WHEN COUNT(p.id) >= 2 THEN 'Medium Frequency'
        ELSE 'Low Frequency'
    END as frequency_category
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY c.id, c.first_name, c.last_name, c.company_name
HAVING total_projects > 0
ORDER BY projects_per_year DESC";

$frequency_analysis = fetchAll($frequency_sql);

// Separate query for average days between projects
$avg_days_sql = "SELECT 
    c.id,
    c.first_name,
    c.last_name,
    AVG(DATEDIFF(
        p2.completion_date, 
        p1.completion_date
    )) as avg_days_between
FROM customers c
LEFT JOIN projects p1 ON c.id = p1.customer_id
LEFT JOIN projects p2 ON c.id = p2.customer_id 
    AND p2.completion_date > p1.completion_date
    AND NOT EXISTS (
        SELECT 1 FROM projects p3 
        WHERE p3.customer_id = c.id 
        AND p3.completion_date > p1.completion_date 
        AND p3.completion_date < p2.completion_date
    )
WHERE p1.completion_date IS NOT NULL 
  AND p2.completion_date IS NOT NULL
  AND p1.completion_date BETWEEN '" . escape($start_date) . "' AND '" . escape($end_date) . "'
  AND p2.completion_date BETWEEN '" . escape($start_date) . "' AND '" . escape($end_date) . "'
GROUP BY c.id, c.first_name, c.last_name
HAVING COUNT(p1.id) > 1";

$days_between_projects = fetchAll($avg_days_sql);

// Combine the data
$frequency_data = [];
foreach ($frequency_analysis as $freq) {
    $avg_days = null;
    foreach ($days_between_projects as $days) {
        if ($days['first_name'] == $freq['customer_name']) {
            $avg_days = $days['avg_days_between'];
            break;
        }
    }
    
    $frequency_data[] = [
        'customer_name' => $freq['customer_name'],
        'company_name' => $freq['company_name'],
        'total_projects' => $freq['total_projects'],
        'years_active' => $freq['years_active'],
        'projects_per_year' => $freq['projects_per_year'],
        'avg_days_between_projects' => $avg_days,
        'frequency_category' => $freq['frequency_category']
    ];
}

$frequency_analysis = $frequency_data;

// 6. Geographic Analysis (using city/country)
$geographic_sql = "SELECT 
    COALESCE(c.city, 'Not Specified') as location,
    COALESCE(c.country, 'Not Specified') as country,
    COUNT(DISTINCT c.id) as customer_count,
    COUNT(DISTINCT p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    ROUND(COALESCE(AVG(p.payment_received), 0), 2) as avg_project_value,
    ROUND(COALESCE(SUM(p.payment_received) / NULLIF(COUNT(DISTINCT c.id), 0), 0), 2) as revenue_per_customer
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY c.city, c.country
HAVING project_count > 0
ORDER BY total_revenue DESC";

$geographic_analysis = fetchAll($geographic_sql);

// 7. Customer Retention Analysis
$retention_sql = "SELECT 
    YEAR(p.completion_date) as project_year,
    COUNT(DISTINCT c.id) as total_customers,
    COUNT(DISTINCT CASE WHEN EXISTS (
        SELECT 1 FROM projects p2 
        WHERE p2.customer_id = c.id 
        AND YEAR(p2.completion_date) = YEAR(p.completion_date) + 1
    ) THEN c.id END) as retained_customers,
    ROUND(COUNT(DISTINCT CASE WHEN EXISTS (
        SELECT 1 FROM projects p2 
        WHERE p2.customer_id = c.id 
        AND YEAR(p2.completion_date) = YEAR(p.completion_date) + 1
    ) THEN c.id END) * 100.0 / NULLIF(COUNT(DISTINCT c.id), 0), 2) as retention_rate
FROM customers c
JOIN projects p ON c.id = p.customer_id
WHERE YEAR(p.completion_date) BETWEEN YEAR('$start_date') AND YEAR('$end_date')
GROUP BY YEAR(p.completion_date)
ORDER BY project_year";

$retention_analysis = fetchAll($retention_sql);

// 8. Profitability Analysis by Customer
$profitability_sql = "SELECT 
    c.id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.company_name,
    COUNT(p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    COALESCE(SUM(e.amount), 0) as total_expenses,
    COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) as total_profit,
    ROUND((COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) / NULLIF(COALESCE(SUM(e.amount), 0), 0)) * 100, 2) as profit_margin,
    ROUND(COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) / NULLIF(COUNT(p.id), 0), 2) as profit_per_project
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
LEFT JOIN expenses e ON p.id = e.project_id
$where_clause
GROUP BY c.id, c.first_name, c.last_name, c.company_name
HAVING project_count > 0
ORDER BY profit_margin DESC";

$profitability_analysis = fetchAll($profitability_sql);

// ==================== CALCULATE ADVANCED METRICS ====================

// Calculate Pareto Principle (80/20 rule)
$total_revenue_all = $summary['total_revenue'];
$pareto_data = [];
$cumulative_percentage = 0;

foreach ($top_customers as $index => $customer) {
    $percentage = $total_revenue_all > 0 ? ($customer['total_revenue'] / $total_revenue_all) * 100 : 0;
    $cumulative_percentage += $percentage;
    
    $pareto_data[] = [
        'customer' => $customer['customer_name'],
        'revenue' => $customer['total_revenue'],
        'percentage' => $percentage,
        'cumulative_percentage' => $cumulative_percentage,
        'is_top_20' => $cumulative_percentage <= 80
    ];
}

// Customer Churn Analysis
$current_year = date('Y');
$previous_year = $current_year - 1;

$churn_sql = "SELECT 
    COUNT(DISTINCT CASE WHEN YEAR(p.completion_date) = $previous_year THEN c.id END) as prev_year_customers,
    COUNT(DISTINCT CASE WHEN YEAR(p.completion_date) = $current_year THEN c.id END) as curr_year_customers,
    COUNT(DISTINCT CASE WHEN YEAR(p.completion_date) = $previous_year AND NOT EXISTS (
        SELECT 1 FROM projects p2 
        WHERE p2.customer_id = c.id 
        AND YEAR(p2.completion_date) = $current_year
    ) THEN c.id END) as churned_customers
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id";

$churn_data = fetchOne($churn_sql);
$churn_rate = $churn_data['prev_year_customers'] > 0 ? 
    ($churn_data['churned_customers'] / $churn_data['prev_year_customers']) * 100 : 0;

// Get filter options for customer type
$customer_types = [
    ['type' => 'corporate', 'name' => 'Corporate (Has Company)'],
    ['type' => 'individual', 'name' => 'Individual (No Company)']
];
$cities = fetchAll("SELECT DISTINCT city FROM customers WHERE city IS NOT NULL AND city != '' ORDER BY city");
$countries = fetchAll("SELECT DISTINCT country FROM customers WHERE country IS NOT NULL AND country != '' ORDER BY country");

// Get industries if industry column exists
$industries = [];
$industry_check = fetchOne("SHOW COLUMNS FROM customers LIKE 'industry'");
if ($industry_check) {
    $industries = fetchAll("SELECT DISTINCT industry FROM customers WHERE industry IS NOT NULL AND industry != '' ORDER BY industry");
}

// Calculate additional metrics
$avg_customer_lifetime = $clv_analysis ? array_sum(array_column($clv_analysis, 'active_years')) / count($clv_analysis) : 0;
$avg_customer_value = $summary['total_customers'] > 0 ? $summary['total_revenue'] / $summary['total_customers'] : 0;
$repeat_customer_rate = $summary['total_customers'] > 0 ? 
    (count(array_filter($top_customers, function($c) { return $c['project_count'] > 1; })) / $summary['total_customers']) * 100 : 0;
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header bg-gradient-primary text-white rounded shadow-sm p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-6 fw-bold mb-2 text-white">
                    <i class="bi bi-people-fill me-3"></i>Customer Intelligence Report
                </h1>
                <p class="lead mb-0 opacity-75">
                    Advanced analytics and insights into customer behavior, value, and profitability
                </p>
                <p class="small opacity-50 mb-0">
                    Period: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-light" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print Report
                </button>
                <button class="btn btn-outline-light" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export
                </button>
            </div>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>Analysis Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Customer Type</label>
                    <select name="customer_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="corporate" <?php echo $customer_type_filter == 'corporate' ? 'selected' : ''; ?>>
                            Corporate (Has Company)
                        </option>
                        <option value="individual" <?php echo $customer_type_filter == 'individual' ? 'selected' : ''; ?>>
                            Individual (No Company)
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Min Projects</label>
                    <input type="number" name="min_projects" class="form-control" 
                           value="<?php echo $min_projects; ?>" min="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Min Revenue (Ksh)</label>
                    <input type="number" name="min_revenue" class="form-control" 
                           value="<?php echo $min_revenue; ?>" min="0" step="1000">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold invisible">Actions</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-bar-chart me-1"></i>Analyze Data
                        </button>
                        <a href="customer_report.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </a>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#insightsModal">
                            <i class="bi bi-lightbulb me-1"></i>Get Insights
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
                        <?php echo $summary['total_customers']; ?>
                    </div>
                    <p class="text-muted mb-0">Active Customers</p>
                    <small class="text-muted">
                        <i class="bi bi-graph-up me-1"></i>
                        <?php echo count(array_filter($clv_analysis, function($c) { 
                            return $c['engagement_status'] == 'Active'; 
                        })); ?> engaged
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-success shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-success mb-2">
                        <?php echo formatCurrency($summary['total_revenue']); ?>
                    </div>
                    <p class="text-muted mb-0">Total Revenue</p>
                    <small class="text-muted">
                        <i class="bi bi-coin me-1"></i>
                        Avg: <?php echo formatCurrency($avg_customer_value); ?> per customer
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-info shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-info mb-2">
                        <?php echo number_format($repeat_customer_rate, 1); ?>%
                    </div>
                    <p class="text-muted mb-0">Repeat Customer Rate</p>
                    <small class="text-muted">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        <?php echo count(array_filter($top_customers, function($c) { return $c['project_count'] > 1; })); ?> repeat customers
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-6 fw-bold text-warning mb-2">
                        <?php echo number_format($churn_rate, 1); ?>%
                    </div>
                    <p class="text-muted mb-0">Annual Churn Rate</p>
                    <small class="text-muted">
                        <i class="bi bi-people-x me-1"></i>
                        <?php echo $churn_data['churned_customers'] ?? 0; ?> customers lost
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pareto Analysis & Segmentation -->
    <div class="row mb-4">
        <!-- Pareto Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart-steps me-2"></i>Pareto Analysis (80/20 Rule)
                    </h5>
                    <span class="badge bg-primary">
                        Top <?php echo count(array_filter($pareto_data, function($p) { return $p['is_top_20']; })); ?> customers generate 80% revenue
                    </span>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="paretoChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Insight:</strong> 
                            <?php 
                            $top20_count = count(array_filter($pareto_data, function($p) { return $p['is_top_20']; }));
                            $top20_percent = $top20_count > 0 ? round(($top20_count / count($pareto_data)) * 100, 1) : 0;
                            echo "{$top20_percent}% of your customers generate 80% of your revenue. Focus on retaining these key accounts.";
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Segmentation -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-diagram-3 me-2"></i>Customer Segmentation
                    </h5>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="segmentationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Customers Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy me-2"></i>Top Performing Customers
                    </h5>
                    <span class="badge bg-primary">Top 50 by Revenue</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th class="text-end">Projects</th>
                                    <th class="text-end">Total Revenue</th>
                                    <th class="text-end">Profit Margin</th>
                                    <th class="text-end">LTV</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $index => $customer): 
                                    $profit_margin = $customer['total_expenses'] > 0 ? 
                                        (($customer['total_revenue'] - $customer['total_expenses']) / $customer['total_revenue']) * 100 : 0;
                                    $ltv = $customer['total_revenue'];
                                    $status_class = $customer['days_since_last_project'] <= 90 ? 'success' : 
                                                  ($customer['days_since_last_project'] <= 180 ? 'warning' : 'danger');
                                    $status_text = $customer['days_since_last_project'] <= 90 ? 'Active' : 
                                                  ($customer['days_since_last_project'] <= 180 ? 'At Risk' : 'Inactive');
                                ?>
                                <tr>
                                    <td class="fw-bold">#<?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo $customer['customer_name']; ?></strong>
                                        <?php if ($customer['company_name']): ?>
                                            <br><small class="text-muted"><?php echo $customer['company_name']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $customer['customer_type'] ?? 'N/A'; ?></span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo $customer['city'] ?? 'N/A'; ?>
                                            <?php if ($customer['country']): ?>
                                                <br><span class="text-muted"><?php echo $customer['country']; ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-secondary"><?php echo $customer['project_count']; ?></span>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($customer['total_revenue']); ?></td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $profit_margin >= 30 ? 'success' : ($profit_margin >= 15 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($profit_margin, 1); ?>%
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-primary"><?php echo formatCurrency($ltv); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../customer/manage_customers.php?action=view&id=<?php echo $customer['id']; ?>" 
                                           class="btn btn-sm btn-outline-info">
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
        </div>
    </div>
    
    <!-- Customer Lifetime Value Analysis -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up-arrow me-2"></i>Customer Lifetime Value Analysis
                    </h5>
                    <span class="badge bg-primary">Customer Value Metrics</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-center">First Project</th>
                                    <th class="text-center">Last Project</th>
                                    <th class="text-end">Active Years</th>
                                    <th class="text-end">Total Projects</th>
                                    <th class="text-end">Lifetime Value</th>
                                    <th class="text-end">Annual Value</th>
                                    <th class="text-end">Avg/Project</th>
                                    <th>Engagement</th>
                                    <th>Value Tier</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clv_analysis as $customer): 
                                    $value_tier = '';
                                    $tier_class = '';
                                    if ($customer['lifetime_value'] >= 5000000) {
                                        $value_tier = 'Platinum';
                                        $tier_class = 'danger';
                                    } elseif ($customer['lifetime_value'] >= 1000000) {
                                        $value_tier = 'Gold';
                                        $tier_class = 'warning';
                                    } elseif ($customer['lifetime_value'] >= 500000) {
                                        $value_tier = 'Silver';
                                        $tier_class = 'secondary';
                                    } else {
                                        $value_tier = 'Bronze';
                                        $tier_class = 'info';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo $customer['customer_name']; ?></strong></td>
                                    <td class="text-center"><?php echo $customer['first_project_date'] ? date('M Y', strtotime($customer['first_project_date'])) : 'N/A'; ?></td>
                                    <td class="text-center"><?php echo $customer['last_project_date'] ? date('M Y', strtotime($customer['last_project_date'])) : 'N/A'; ?></td>
                                    <td class="text-end"><?php echo $customer['active_years']; ?> yrs</td>
                                    <td class="text-end"><?php echo $customer['total_projects']; ?></td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($customer['lifetime_value']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($customer['annual_value']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($customer['avg_value_per_project']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $customer['engagement_status'] == 'Active' ? 'success' : 
                                                                 ($customer['engagement_status'] == 'At Risk' ? 'warning' : 'danger'); ?>">
                                            <?php echo $customer['engagement_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $tier_class; ?>">
                                            <?php echo $value_tier; ?>
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
    
    <!-- Geographic & Profitability Analysis -->
    <div class="row mb-4">
        <!-- Geographic Analysis -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt me-2"></i>Geographic Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Location</th>
                                    <th class="text-end">Customers</th>
                                    <th class="text-end">Projects</th>
                                    <th class="text-end">Total Revenue</th>
                                    <th class="text-end">Avg Project Value</th>
                                    <th class="text-end">Rev/Customer</th>
                                    <th>Contribution</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($geographic_analysis as $location): 
                                    $contribution = $summary['total_revenue'] > 0 ? 
                                        ($location['total_revenue'] / $summary['total_revenue']) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $location['location']; ?></strong>
                                        <?php if ($location['country'] && $location['country'] != 'Not Specified'): ?>
                                            <br><small class="text-muted"><?php echo $location['country']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo $location['customer_count']; ?></td>
                                    <td class="text-end"><?php echo $location['project_count']; ?></td>
                                    <td class="text-end fw-bold"><?php echo formatCurrency($location['total_revenue']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($location['avg_project_value']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($location['revenue_per_customer']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo $contribution; ?>%"></div>
                                        </div>
                                        <small><?php echo number_format($contribution, 1); ?>%</small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profitability Analysis -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-currency-dollar me-2"></i>Customer Profitability
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-end">Projects</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Expenses</th>
                                    <th class="text-end">Profit</th>
                                    <th class="text-end">Margin</th>
                                    <th class="text-end">Profit/Project</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($profitability_analysis as $customer): ?>
                                <tr>
                                    <td><small><?php echo $customer['customer_name']; ?></small></td>
                                    <td class="text-end"><?php echo $customer['project_count']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($customer['total_revenue']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($customer['total_expenses']); ?></td>
                                    <td class="text-end fw-bold <?php echo $customer['total_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($customer['total_profit']); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge bg-<?php echo $customer['profit_margin'] >= 30 ? 'success' : 
                                                             ($customer['profit_margin'] >= 15 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($customer['profit_margin'], 1); ?>%
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <small><?php echo formatCurrency($customer['profit_per_project']); ?></small>
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
    
    <!-- Retention & Frequency Analysis -->
    <div class="row mb-4">
        <!-- Retention Analysis -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat me-2"></i>Customer Retention Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <div style="height: 250px;">
                        <canvas id="retentionChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Retention Insight:</strong> 
                            Average retention rate is 
                            <?php 
                            $avg_retention = $retention_analysis ? 
                                array_sum(array_column($retention_analysis, 'retention_rate')) / count($retention_analysis) : 0;
                            echo number_format($avg_retention, 1); 
                            ?>%. 
                            Industry standard is 75-85% for service businesses.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Frequency Analysis -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-heart me-2"></i>Project Frequency Analysis
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Frequency Category</th>
                                    <th class="text-end">Customers</th>
                                    <th class="text-end">% of Total</th>
                                    <th class="text-end">Avg Projects/Year</th>
                                    <th class="text-end">Avg Days Between</th>
                                    <th class="text-end">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $frequency_categories = ['High Frequency', 'Medium Frequency', 'Low Frequency'];
                                foreach ($frequency_categories as $category):
                                    $filtered = array_filter($frequency_analysis, function($c) use ($category) {
                                        return $c['frequency_category'] == $category;
                                    });
                                    $customer_count = count($filtered);
                                    $total_customers = count($frequency_analysis);
                                    $total_percent = $total_customers > 0 ? ($customer_count / $total_customers) * 100 : 0;
                                    $avg_projects_year = $customer_count > 0 ? 
                                        array_sum(array_column($filtered, 'projects_per_year')) / $customer_count : 0;
                                    $days_array = array_filter(array_column($filtered, 'avg_days_between_projects'), function($v) { return $v !== null; });
                                    $avg_days_between = (count($days_array) > 0) ? array_sum($days_array) / count($days_array) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $category == 'High Frequency' ? 'success' : 
                                                             ($category == 'Medium Frequency' ? 'warning' : 'info'); ?>">
                                            <?php echo $category; ?>
                                        </span>
                                    </td>
                                    <td class="text-end"><?php echo $customer_count; ?></td>
                                    <td class="text-end"><?php echo number_format($total_percent, 1); ?>%</td>
                                    <td class="text-end"><?php echo number_format($avg_projects_year, 1); ?></td>
                                    <td class="text-end"><?php echo $avg_days_between ? number_format($avg_days_between, 0) . ' days' : 'N/A'; ?></td>
                                    <td class="text-end">
                                        <?php 
                                        $revenue = 0;
                                        foreach ($filtered as $customer) {
                                            foreach ($top_customers as $top) {
                                                if ($top['customer_name'] == $customer['customer_name']) {
                                                    $revenue += $top['total_revenue'];
                                                    break;
                                                }
                                            }
                                        }
                                        echo formatCurrency($revenue);
                                        ?>
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
    <!-- Help Button -->
<button class="help-btn" data-bs-toggle="modal" data-bs-target="#helpModal">
    <i class="bi bi-question-circle"></i>
</button>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-info-circle me-2"></i>Customer Report Guide
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>How to use this report:</strong> Each section provides actionable insights for your water drilling business
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-funnel me-2"></i>Filter Section</h6>
                    <ul>
                        <li><span class="highlight-term">Date Range</span>: Analyze specific periods (e.g., quarterly, annually)</li>
                        <li><span class="highlight-term">Customer Type</span>: Compare Corporate vs Individual clients</li>
                        <li><span class="highlight-term">Min Projects/Revenue</span>: Focus on high-value customers</li>
                        <li><strong>Tip</strong>: Set min revenue to 500,000 Ksh to see top clients only</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-bar-chart-steps me-2"></i>Pareto Analysis (80/20 Rule)</h6>
                    <ul>
                        <li>Shows which customers generate most revenue</li>
                        <li><span class="highlight-term">Blue Bars</span>: Revenue per customer</li>
                        <li><span class="highlight-term">Red Line</span>: Cumulative percentage</li>
                        <li><strong>Business Insight</strong>: Focus on customers above the 80% line - they generate 80% of your revenue!</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-trophy me-2"></i>Top Customers Table</h6>
                    <ul>
                        <li><span class="badge bg-success">Active</span>: Project in last 90 days</li>
                        <li><span class="badge bg-warning">At Risk</span>: 90-180 days since last project</li>
                        <li><span class="badge bg-danger">Inactive</span>: Over 180 days</li>
                        <li><strong>Action</strong>: Call "At Risk" customers this week with special offer</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-graph-up-arrow me-2"></i>Customer Lifetime Value</h6>
                    <ul>
                        <li><span class="badge bg-danger">Platinum</span>: 5M+ Ksh lifetime value</li>
                        <li><span class="badge bg-warning">Gold</span>: 1M-5M Ksh</li>
                        <li><span class="badge bg-secondary">Silver</span>: 500K-1M Ksh</li>
                        <li><span class="badge bg-info">Bronze</span>: Under 500K Ksh</li>
                        <li><strong>Strategy</strong>: Different service levels for each tier</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-currency-dollar me-2"></i>Profitability Analysis</h6>
                    <ul>
                        <li>Compares revenue vs expenses per customer</li>
                        <li><span class="highlight-term">Margin Colors</span>:
                            <span class="badge bg-success">Green ≥30%</span> (Excellent),
                            <span class="badge bg-warning">Yellow 15-30%</span> (Good),
                            <span class="badge bg-danger">Red <15%</span> (Review)
                        </li>
                        <li><strong>Action</strong>: Increase prices for red margin customers</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-arrow-repeat me-2"></i>Retention Rate</h6>
                    <ul>
                        <li>Measures how many customers return each year</li>
                        <li><span class="highlight-term">Industry Standard</span>: 75-85% for service businesses</li>
                        <li><strong>If below 75%</strong>: Implement loyalty program</li>
                    </ul>
                </div>
                
                <div class="help-section">
                    <h6><i class="bi bi-calendar-heart me-2"></i>Frequency Analysis</h6>
                    <ul>
                        <li><span class="badge bg-success">High Frequency</span>: 5+ projects</li>
                        <li><span class="badge bg-warning">Medium Frequency</span>: 2-4 projects</li>
                        <li><span class="badge bg-info">Low Frequency</span>: 1 project only</li>
                        <li><strong>Goal</strong>: Convert Low → Medium → High frequency</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <h6><i class="bi bi-clock-history me-2"></i>Monthly Review Checklist:</h6>
                    <ol class="mb-0">
                        <li>Check churn rate (should be <15%)</li>
                        <li>Call all "At Risk" customers</li>
                        <li>Review profitability of bottom 20% customers</li>
                        <li>Update VIP program for top 20%</li>
                        <li>Analyze geographic opportunities</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Insights Modal -->
<div class="modal fade" id="insightsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-graph-up-arrow me-2"></i>Customer Intelligence Insights
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-primary">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Key Strategic Insights:</strong> Based on customer data analysis
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-success">
                                    <i class="bi bi-currency-dollar me-2"></i>Revenue Concentration
                                </h6>
                                <ul class="small">
                                    <li><strong>Top 20% of customers</strong> generate 
                                        <?php 
                                        $top20_revenue = array_sum(array_slice(array_column($pareto_data, 'revenue'), 0, ceil(count($pareto_data) * 0.2)));
                                        $top20_percent = $summary['total_revenue'] > 0 ? ($top20_revenue / $summary['total_revenue']) * 100 : 0;
                                        echo number_format($top20_percent, 1);
                                        ?>% of total revenue
                                    </li>
                                    <li><strong>Key Accounts:</strong> 
                                        <?php 
                                        $platinum_count = count(array_filter($clv_analysis, function($c) { 
                                            return $c['lifetime_value'] >= 5000000; 
                                        }));
                                        echo $platinum_count . ' Platinum-tier customers identified';
                                        ?>
                                    </li>
                                    <li><strong>Recommendation:</strong> Implement VIP program for top 20%</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>Risk Factors
                                </h6>
                                <ul class="small">
                                    <li><strong>Churn Risk:</strong> 
                                        <?php 
                                        $at_risk = count(array_filter($clv_analysis, function($c) { 
                                            return $c['engagement_status'] == 'At Risk'; 
                                        }));
                                        echo $at_risk . ' customers at risk of churning';
                                        ?>
                                    </li>
                                    <li><strong>Inactive Accounts:</strong> 
                                        <?php 
                                        $inactive = count(array_filter($clv_analysis, function($c) { 
                                            return $c['engagement_status'] == 'Inactive'; 
                                        }));
                                        echo $inactive . ' customers inactive (>180 days)';
                                        ?>
                                    </li>
                                    <li><strong>Recommendation:</strong> Reactivation campaign needed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="bi bi-geo-alt me-2"></i>Geographic Opportunities
                                </h6>
                                <ul class="small">
                                    <li><strong>Top Location:</strong> 
                                        <?php 
                                        $top_location = $geographic_analysis[0]['location'] ?? 'N/A';
                                        $top_location_rev = $geographic_analysis[0]['total_revenue'] ?? 0;
                                        echo $top_location . ' ('.formatCurrency($top_location_rev).')';
                                        ?>
                                    </li>
                                    <li><strong>Growth Potential:</strong> 
                                        <?php 
                                        $avg_geo_value = $geographic_analysis ? 
                                            array_sum(array_column($geographic_analysis, 'revenue_per_customer')) / count($geographic_analysis) : 0;
                                        echo formatCurrency($avg_geo_value) . ' average per customer';
                                        ?>
                                    </li>
                                    <li><strong>Recommendation:</strong> Target similar geographic areas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="bi bi-people me-2"></i>Customer Behavior
                                </h6>
                                <ul class="small">
                                    <li><strong>Repeat Rate:</strong> 
                                        <?php echo number_format($repeat_customer_rate, 1); ?>% of customers return
                                    </li>
                                    <li><strong>Project Frequency:</strong> 
                                        <?php 
                                        $avg_frequency = $frequency_analysis ? 
                                            array_sum(array_column($frequency_analysis, 'projects_per_year')) / count($frequency_analysis) : 0;
                                        echo number_format($avg_frequency, 1) . ' projects per year average';
                                        ?>
                                    </li>
                                    <li><strong>Recommendation:</strong> Improve customer touchpoints</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-secondary mt-3">
                    <h6 class="mb-2"><i class="bi bi-clipboard-check me-2"></i>Actionable Recommendations:</h6>
                    <ol class="small mb-0">
                        <li>Create tiered service packages based on customer value</li>
                        <li>Implement proactive retention program for at-risk customers</li>
                        <li>Develop geographic-specific marketing campaigns</li>
                        <li>Establish referral program with high-value customers</li>
                        <li>Regular profitability reviews of customer accounts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pareto Chart
    const paretoCtx = document.getElementById('paretoChart').getContext('2d');
    const paretoChart = new Chart(paretoCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($pareto_data, 'customer')); ?>,
            datasets: [
                {
                    label: 'Revenue (Ksh)',
                    data: <?php echo json_encode(array_column($pareto_data, 'revenue')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Cumulative %',
                    data: <?php echo json_encode(array_column($pareto_data, 'cumulative_percentage')); ?>,
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
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
                            return 'Ksh ' + (value/1000).toLocaleString() + 'k';
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Cumulative %'
                    },
                    min: 0,
                    max: 100,
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label.includes('Revenue')) {
                                label += ': Ksh ' + context.parsed.y.toLocaleString();
                            } else {
                                label += ': ' + context.parsed.y.toFixed(1) + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Segmentation Chart
    const segCtx = document.getElementById('segmentationChart').getContext('2d');
    const segChart = new Chart(segCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($segmentation, 'customer_type')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($segmentation, 'total_revenue')); ?>,
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
                        usePointStyle: true
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
                }
            }
        }
    });

    // Retention Chart
    const retentionCtx = document.getElementById('retentionChart').getContext('2d');
    const retentionChart = new Chart(retentionCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($retention_analysis, 'project_year')); ?>,
            datasets: [{
                label: 'Retention Rate (%)',
                data: <?php echo json_encode(array_column($retention_analysis, 'retention_rate')); ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Retention Rate (%)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Year'
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Retention: ${context.parsed.y}%`;
                        }
                    }
                }
            }
        }
    });
});

function exportToExcel() {
    const params = new URLSearchParams(window.location.search);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_customer_report.php';
    
    params.forEach((value, key) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
// Add tooltips to key terms (add to your existing script section)
function addHelpTooltips() {
    // Add help badges to key terms
    const helpTerms = [
        { selector: '.page-header h1', text: 'Complete customer intelligence dashboard with 9 different analyses' },
        { selector: '#paretoChart', text: '80/20 Rule: 20% of customers generate 80% of revenue. Focus on customers above the line.' },
        { selector: '#segmentationChart', text: 'Corporate vs Individual customers. Corporate usually have higher lifetime value.' },
        { selector: '#retentionChart', text: 'Year-over-year customer retention. Aim for 75%+ in service business.' }
    ];
    
    helpTerms.forEach(term => {
        const element = document.querySelector(term.selector);
        if (element) {
            element.setAttribute('title', term.text);
            element.classList.add('badge-help');
        }
    });
}

document.addEventListener('DOMContentLoaded', addHelpTooltips);
</script>

<style>
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card {
    border: none;
    border-radius: 10px;
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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

.modal-header {
    border-bottom: none;
}

@media print {
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .btn, .modal, .card-header .badge:not(.print-badge) {
        display: none !important;
    }
    
    .page-header {
        background: white !important;
        color: black !important;
    }
    
    .table {
        font-size: 12px;
    }
}
/* Help System Styles */
.help-btn {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.help-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0,0,0,0.2);
}

.help-modal .modal-dialog {
    max-width: 800px;
}

.help-section {
    margin-bottom: 25px;
    padding: 20px;
    border-radius: 10px;
    background: #f8f9fa;
    border-left: 4px solid #0d6efd;
}

.help-section h6 {
    color: #0d6efd;
    margin-bottom: 10px;
    font-weight: 600;
}

.help-section ul {
    margin-bottom: 0;
    padding-left: 20px;
}

.help-section li {
    margin-bottom: 5px;
    color: #495057;
}

.badge-help {
    cursor: help;
    border-bottom: 1px dashed #6c757d;
}

.highlight-term {
    background-color: #fff3cd;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: 600;
    color: #856404;
}
</style>

<?php require_once '../../includes/footer.php'; ?>