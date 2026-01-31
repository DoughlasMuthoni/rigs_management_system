<?php
// export_customer_report.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access. Please log in.");
}

// Get filter parameters from POST
$start_date = isset($_POST['start_date']) ? escape($_POST['start_date']) : date('Y-01-01');
$end_date = isset($_POST['end_date']) ? escape($_POST['end_date']) : date('Y-m-d');
$customer_type_filter = isset($_POST['customer_type']) ? escape($_POST['customer_type']) : '';
$min_projects = isset($_POST['min_projects']) ? intval($_POST['min_projects']) : 0;
$min_revenue = isset($_POST['min_revenue']) ? floatval($_POST['min_revenue']) : 0;

// Build WHERE clause
$conditions = [];
if ($start_date && $end_date) {
    $conditions[] = "(p.completion_date IS NOT NULL AND p.completion_date BETWEEN '$start_date' AND '$end_date')";
}

// Note: customer_type column might not exist in your customers table
// Using company_name to determine customer type instead
if ($customer_type_filter == 'corporate') {
    $conditions[] = "c.company_name IS NOT NULL AND c.company_name != ''";
} elseif ($customer_type_filter == 'individual') {
    $conditions[] = "(c.company_name IS NULL OR c.company_name = '')";
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// Get top customers data - UPDATED QUERY
$top_customers_sql = "SELECT 
    c.id,
    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
    c.company_name,
    c.city,
    c.country,
    c.email,
    c.phone,
    c.customer_code,
    CASE 
        WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
        ELSE 'Individual'
    END as customer_type,
    COUNT(p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    COALESCE(SUM(e.amount), 0) as total_expenses,
    COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) as total_profit,
    ROUND((COALESCE(SUM(p.payment_received) - COALESCE(SUM(e.amount), 0), 0) / 
           NULLIF(COALESCE(SUM(p.payment_received), 0), 0)) * 100, 2) as profit_margin,
    MAX(p.completion_date) as last_project_date,
    DATEDIFF(NOW(), MAX(p.completion_date)) as days_since_last_project
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
LEFT JOIN expenses e ON p.id = e.project_id
$where_clause
GROUP BY c.id, c.first_name, c.last_name, c.company_name, c.city, c.country, c.email, c.phone, c.customer_code
HAVING total_revenue >= $min_revenue AND project_count >= $min_projects
ORDER BY total_revenue DESC";

$top_customers = fetchAll($top_customers_sql);

// Get summary statistics
$summary_sql = "SELECT 
    COUNT(DISTINCT c.id) as total_customers,
    COUNT(DISTINCT p.id) as total_projects,
    COALESCE(SUM(p.payment_received), 0) as total_revenue,
    COALESCE(AVG(p.payment_received), 0) as avg_project_value,
    COUNT(DISTINCT CASE WHEN p.rig_id IS NOT NULL AND p.rig_id != 0 THEN p.rig_id END) as rigs_utilized
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause";

$summary = fetchOne($summary_sql);

// Set headers for Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="customer_analysis_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start HTML output for Excel
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Customer Analysis Export</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th { background-color: #2c3e50; color: white; font-weight: bold; padding: 8px; text-align: left; border: 1px solid #ddd; }
        td { padding: 6px; border: 1px solid #ddd; }
        .header { background-color: #3498db; color: white; font-size: 16px; font-weight: bold; text-align: center; padding: 15px; }
        .subheader { background-color: #ecf0f1; font-weight: bold; padding: 10px; }
        .summary-row { background-color: #f8f9fa; }
        .total-row { background-color: #d4edda; font-weight: bold; }
        .warning-row { background-color: #fff3cd; }
        .success-row { background-color: #d1ecf1; }
        .number { text-align: right; }
        .center { text-align: center; }
        .profit-positive { color: #28a745; font-weight: bold; }
        .profit-negative { color: #dc3545; font-weight: bold; }
        .profit-margin-high { background-color: #d4edda; }
        .profit-margin-medium { background-color: #fff3cd; }
        .profit-margin-low { background-color: #f8d7da; }
    </style>
</head>
<body>

<!-- Report Header -->
<table>
    <tr>
        <td colspan="15" class="header">
            WATERLIFT SOLAR & RIG TRACKER - CUSTOMER INTELLIGENCE REPORT
        </td>
    </tr>
    <tr class="summary-row">
        <td colspan="15">
            <strong>Report Generated:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
            <strong>Period:</strong> <?php echo date('d/m/Y', strtotime($start_date)); ?> to <?php echo date('d/m/Y', strtotime($end_date)); ?><br>
            <strong>Filters Applied:</strong> 
            <?php 
            echo "Min Projects: $min_projects, ";
            echo "Min Revenue: " . formatCurrency($min_revenue);
            if ($customer_type_filter) {
                echo ", Customer Type: " . ($customer_type_filter == 'corporate' ? 'Corporate' : 'Individual');
            }
            ?>
        </td>
    </tr>
</table>

<!-- Executive Summary -->
<table>
    <tr>
        <td colspan="5" class="subheader">EXECUTIVE SUMMARY</td>
    </tr>
    <tr class="summary-row">
        <td><strong>Total Customers</strong></td>
        <td class="number"><?php echo $summary['total_customers']; ?></td>
        <td><strong>Total Projects</strong></td>
        <td class="number"><?php echo $summary['total_projects']; ?></td>
        <td><strong>Rigs Utilized</strong></td>
        <td class="number"><?php echo $summary['rigs_utilized']; ?></td>
    </tr>
    <tr class="summary-row">
        <td><strong>Total Revenue</strong></td>
        <td class="number"><?php echo formatCurrency($summary['total_revenue']); ?></td>
        <td><strong>Avg Project Value</strong></td>
        <td class="number"><?php echo formatCurrency($summary['avg_project_value']); ?></td>
        <td><strong>Revenue/Customer</strong></td>
        <td class="number"><?php echo $summary['total_customers'] > 0 ? formatCurrency($summary['total_revenue'] / $summary['total_customers']) : '0.00'; ?></td>
    </tr>
</table>

<!-- Customer Performance Analysis -->
<table>
    <tr>
        <td colspan="15" class="subheader">CUSTOMER PERFORMANCE ANALYSIS (Sorted by Revenue)</td>
    </tr>
    <tr>
        <th>#</th>
        <th>Customer Name</th>
        <th>Company</th>
        <th>Type</th>
        <th>Location</th>
        <th>Contact</th>
        <th>Customer Code</th>
        <th>Projects</th>
        <th>Total Revenue</th>
        <th>Total Expenses</th>
        <th>Total Profit</th>
        <th>Profit Margin</th>
        <th>Avg/Project</th>
        <th>Last Project</th>
        <th>Status</th>
    </tr>
    
    <?php
    if (!empty($top_customers)) {
        $total_revenue = 0;
        $total_expenses = 0;
        $total_profit = 0;
        $row_number = 1;
        
        foreach ($top_customers as $customer) {
            $avg_per_project = $customer['project_count'] > 0 ? $customer['total_revenue'] / $customer['project_count'] : 0;
            
            // Determine status
            if ($customer['days_since_last_project'] <= 90) {
                $status = 'Active';
                $status_class = 'success-row';
            } elseif ($customer['days_since_last_project'] <= 180) {
                $status = 'At Risk';
                $status_class = 'warning-row';
            } else {
                $status = 'Inactive';
                $status_class = '';
            }
            
            // Determine profit margin class
            $margin_class = '';
            if ($customer['profit_margin'] >= 30) {
                $margin_class = 'profit-margin-high';
            } elseif ($customer['profit_margin'] >= 15) {
                $margin_class = 'profit-margin-medium';
            } elseif ($customer['profit_margin'] > 0) {
                $margin_class = 'profit-margin-low';
            }
            
            // Determine profit color
            $profit_class = $customer['total_profit'] >= 0 ? 'profit-positive' : 'profit-negative';
            
            echo '<tr>';
            echo '<td class="center">' . $row_number++ . '</td>';
            echo '<td>' . htmlspecialchars($customer['customer_name']) . '</td>';
            echo '<td>' . htmlspecialchars($customer['company_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($customer['customer_type'] ?? 'Individual') . '</td>';
            echo '<td>' . htmlspecialchars(($customer['city'] ?? 'N/A') . ', ' . ($customer['country'] ?? 'N/A')) . '</td>';
            echo '<td>' . htmlspecialchars($customer['email'] ?? 'N/A') . '<br>' . htmlspecialchars($customer['phone'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($customer['customer_code'] ?? 'N/A') . '</td>';
            echo '<td class="number">' . $customer['project_count'] . '</td>';
            echo '<td class="number">' . formatCurrency($customer['total_revenue']) . '</td>';
            echo '<td class="number">' . formatCurrency($customer['total_expenses']) . '</td>';
            echo '<td class="number ' . $profit_class . '">' . formatCurrency($customer['total_profit']) . '</td>';
            echo '<td class="number ' . $margin_class . '">' . number_format($customer['profit_margin'], 1) . '%</td>';
            echo '<td class="number">' . formatCurrency($avg_per_project) . '</td>';
            echo '<td>' . ($customer['last_project_date'] ? date('d/m/Y', strtotime($customer['last_project_date'])) : 'N/A') . '</td>';
            echo '<td class="center ' . $status_class . '">' . $status . '</td>';
            echo '</tr>';
            
            $total_revenue += $customer['total_revenue'];
            $total_expenses += $customer['total_expenses'];
            $total_profit += $customer['total_profit'];
        }
        
        // Calculate averages
        $avg_revenue = count($top_customers) > 0 ? $total_revenue / count($top_customers) : 0;
        $avg_projects = count($top_customers) > 0 ? array_sum(array_column($top_customers, 'project_count')) / count($top_customers) : 0;
        $avg_profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
        
        // Totals row
        echo '<tr class="total-row">';
        echo '<td colspan="7"><strong>TOTALS & AVERAGES</strong></td>';
        echo '<td class="number"><strong>' . number_format($avg_projects, 1) . ' avg</strong></td>';
        echo '<td class="number"><strong>' . formatCurrency($total_revenue) . '</strong></td>';
        echo '<td class="number"><strong>' . formatCurrency($total_expenses) . '</strong></td>';
        echo '<td class="number"><strong>' . formatCurrency($total_profit) . '</strong></td>';
        echo '<td class="number"><strong>' . number_format($avg_profit_margin, 1) . '% avg</strong></td>';
        echo '<td class="number"><strong>' . formatCurrency($avg_revenue) . ' avg</strong></td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
        
        // Pareto analysis note
        $top20_count = ceil(count($top_customers) * 0.2);
        $top20_revenue = 0;
        for ($i = 0; $i < $top20_count && $i < count($top_customers); $i++) {
            $top20_revenue += $top_customers[$i]['total_revenue'];
        }
        $top20_percentage = $total_revenue > 0 ? ($top20_revenue / $total_revenue) * 100 : 0;
        
        echo '<tr class="summary-row">';
        echo '<td colspan="15">';
        echo '<strong>PARETO ANALYSIS (80/20 Rule):</strong> ';
        echo 'Top ' . $top20_count . ' customers (' . round(($top20_count / count($top_customers)) * 100, 1) . '%) ';
        echo 'generate ' . number_format($top20_percentage, 1) . '% of total revenue.';
        if ($top20_percentage >= 70) {
            echo ' <em>(High concentration - focus on retaining these key accounts)</em>';
        }
        echo '</td>';
        echo '</tr>';
        
    } else {
        echo '<tr><td colspan="15" class="center" style="padding: 20px;">No customer data found for the selected filters.</td></tr>';
    }
    ?>
</table>

<!-- Customer Segmentation -->
<?php
// Get segmentation data
$segmentation_sql = "SELECT 
    CASE 
        WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
        ELSE 'Individual'
    END as customer_type,
    COUNT(DISTINCT c.id) as customer_count,
    COUNT(DISTINCT p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY CASE 
    WHEN c.company_name IS NOT NULL AND c.company_name != '' THEN 'Corporate'
    ELSE 'Individual'
END
ORDER BY total_revenue DESC";

$segmentation = fetchAll($segmentation_sql);

if (!empty($segmentation)) {
?>
<table>
    <tr>
        <td colspan="6" class="subheader">CUSTOMER SEGMENTATION ANALYSIS</td>
    </tr>
    <tr>
        <th>Customer Type</th>
        <th>Customer Count</th>
        <th>% of Total</th>
        <th>Project Count</th>
        <th>Total Revenue</th>
        <th>% of Revenue</th>
    </tr>
    <?php
    $total_customers_seg = array_sum(array_column($segmentation, 'customer_count'));
    $total_revenue_seg = array_sum(array_column($segmentation, 'total_revenue'));
    
    foreach ($segmentation as $segment) {
        $customer_percent = $total_customers_seg > 0 ? ($segment['customer_count'] / $total_customers_seg) * 100 : 0;
        $revenue_percent = $total_revenue_seg > 0 ? ($segment['total_revenue'] / $total_revenue_seg) * 100 : 0;
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($segment['customer_type']) . '</td>';
        echo '<td class="number">' . $segment['customer_count'] . '</td>';
        echo '<td class="number">' . number_format($customer_percent, 1) . '%</td>';
        echo '<td class="number">' . $segment['project_count'] . '</td>';
        echo '<td class="number">' . formatCurrency($segment['total_revenue']) . '</td>';
        echo '<td class="number">' . number_format($revenue_percent, 1) . '%</td>';
        echo '</tr>';
    }
    ?>
</table>
<?php } ?>

<!-- Geographic Analysis -->
<?php
// Get geographic data
$geographic_sql = "SELECT 
    COALESCE(c.city, 'Not Specified') as location,
    COALESCE(c.country, 'Not Specified') as country,
    COUNT(DISTINCT c.id) as customer_count,
    COUNT(DISTINCT p.id) as project_count,
    COALESCE(SUM(p.payment_received), 0) as total_revenue
FROM customers c
LEFT JOIN projects p ON c.id = p.customer_id
$where_clause
GROUP BY c.city, c.country
HAVING project_count > 0
ORDER BY total_revenue DESC
LIMIT 10";

$geographic_analysis = fetchAll($geographic_sql);

if (!empty($geographic_analysis)) {
?>
<table>
    <tr>
        <td colspan="7" class="subheader">TOP 10 GEOGRAPHIC MARKETS</td>
    </tr>
    <tr>
        <th>#</th>
        <th>Location</th>
        <th>Country</th>
        <th>Customers</th>
        <th>Projects</th>
        <th>Total Revenue</th>
        <th>% of Total</th>
    </tr>
    <?php
    $total_geo_revenue = array_sum(array_column($geographic_analysis, 'total_revenue'));
    $row_num = 1;
    
    foreach ($geographic_analysis as $geo) {
        $revenue_percent = $total_geo_revenue > 0 ? ($geo['total_revenue'] / $total_geo_revenue) * 100 : 0;
        
        echo '<tr>';
        echo '<td class="center">' . $row_num++ . '</td>';
        echo '<td>' . htmlspecialchars($geo['location']) . '</td>';
        echo '<td>' . htmlspecialchars($geo['country']) . '</td>';
        echo '<td class="number">' . $geo['customer_count'] . '</td>';
        echo '<td class="number">' . $geo['project_count'] . '</td>';
        echo '<td class="number">' . formatCurrency($geo['total_revenue']) . '</td>';
        echo '<td class="number">' . number_format($revenue_percent, 1) . '%</td>';
        echo '</tr>';
    }
    ?>
</table>
<?php } ?>

<!-- Notes and Recommendations -->
<table>
    <tr>
        <td class="subheader">KEY INSIGHTS & RECOMMENDATIONS</td>
    </tr>
    <tr class="summary-row">
        <td>
            <strong>Based on the analysis:</strong><br><br>
            1. <strong>Customer Retention:</strong> 
            <?php
            $inactive_count = count(array_filter($top_customers, function($c) { 
                return $c['days_since_last_project'] > 180; 
            }));
            echo $inactive_count . ' customers are inactive (>180 days). Consider a win-back campaign.';
            ?>
            <br><br>
            2. <strong>Profitability Focus:</strong> 
            <?php
            $low_margin_count = count(array_filter($top_customers, function($c) { 
                return $c['profit_margin'] > 0 && $c['profit_margin'] < 15; 
            }));
            echo $low_margin_count . ' customers have profit margins below 15%. Review pricing or costs.';
            ?>
            <br><br>
            3. <strong>Growth Opportunity:</strong> 
            <?php
            $single_project_count = count(array_filter($top_customers, function($c) { 
                return $c['project_count'] == 1; 
            }));
            echo $single_project_count . ' customers have only one project. Upsell maintenance contracts or new services.';
            ?>
            <br><br>
            4. <strong>VIP Program:</strong> Consider creating a VIP program for top 
            <?php echo ceil(count($top_customers) * 0.2); ?> customers who generate 
            <?php echo number_format($top20_percentage ?? 0, 1); ?>% of revenue.
        </td>
    </tr>
</table>

<!-- Footer -->
<table>
    <tr>
        <td style="text-align: center; padding: 10px; font-size: 10px; color: #666;">
            Confidential - Waterlift Solar & Rig Tracker System<br>
            Report ID: <?php echo uniqid('CRPT_'); ?> | Generated by: <?php echo $_SESSION['username'] ?? 'System'; ?>
        </td>
    </tr>
</table>

</body>
</html>