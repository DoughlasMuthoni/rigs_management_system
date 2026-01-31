<?php
// export_expense_report.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Get filter parameters from POST
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$rig_id = isset($_POST['rig_id']) ? intval($_POST['rig_id']) : 0;
$category = isset($_POST['category']) ? escape($_POST['category']) : '';
$supplier_id = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;

// Build conditions (same as main report)
$conditions = [];
if ($start_date && $end_date) {
    $conditions[] = "e.expense_date BETWEEN '$start_date' AND '$end_date'";
}
if ($project_id > 0) {
    $conditions[] = "e.project_id = $project_id";
}
if ($rig_id > 0) {
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

// Get summary data
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

// Get detailed expenses
// Get detailed expenses
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
ORDER BY e.expense_date DESC";

$expenses = fetchAll($expenses_sql);

// Get categories
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

// Set headers for Excel file
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="expense_report_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #f2f2f2; font-weight: bold; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .summary { background-color: #e8f4fd; }
        .total { background-color: #d1ecf1; font-weight: bold; }
        .header { background-color: #007bff; color: white; font-size: 18px; }
    </style>
</head>
<body>';

// Report Header
echo '<table>
    <tr><td colspan="8" class="header">Waterlift Solar - Expense Analysis Report</td></tr>
    <tr><td colspan="8">Generated: ' . date('d/m/Y H:i:s') . '</td></tr>
    <tr><td colspan="8">Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</td></tr>
</table>
<br>';

// Executive Summary
echo '<table>
    <tr><td colspan="4" class="summary"><strong>EXECUTIVE SUMMARY</strong></td></tr>
    <tr>
        <td><strong>Total Expenses</strong></td>
        <td>' . formatCurrency($summary['total_amount']) . '</td>
        <td><strong>Total Records</strong></td>
        <td>' . $summary['total_records'] . '</td>
    </tr>
    <tr>
        <td><strong>Average Expense</strong></td>
        <td>' . formatCurrency($summary['average_expense']) . '</td>
        <td><strong>Total Projects</strong></td>
        <td>' . $summary['total_projects'] . '</td>
    </tr>
    <tr>
        <td><strong>Highest Expense</strong></td>
        <td>' . formatCurrency($summary['max_expense']) . '</td>
        <td><strong>Total Suppliers</strong></td>
        <td>' . $summary['total_suppliers'] . '</td>
    </tr>
    <tr>
        <td><strong>Lowest Expense</strong></td>
        <td>' . formatCurrency($summary['min_expense']) . '</td>
        <td><strong>Daily Average</strong></td>
        <td>' . formatCurrency($summary['total_amount'] / max((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1, 1)) . '</td>
    </tr>
</table>
<br>';

// Category Breakdown
if (!empty($categories)) {
    echo '<table>
        <tr><td colspan="5" class="summary"><strong>EXPENSE CATEGORY BREAKDOWN</strong></td></tr>
        <tr>
            <th>Category</th>
            <th>Amount</th>
            <th>Count</th>
            <th>Percentage</th>
            <th>Average per Item</th>
        </tr>';
    
    foreach ($categories as $cat) {
        echo '<tr>
            <td>' . $cat['category'] . '</td>
            <td>' . formatCurrency($cat['total_amount']) . '</td>
            <td>' . $cat['expense_count'] . '</td>
            <td>' . $cat['percentage'] . '%</td>
            <td>' . formatCurrency($cat['total_amount'] / max($cat['expense_count'], 1)) . '</td>
        </tr>';
    }
    
    echo '</table><br>';
}

// Detailed Expense List
if (!empty($expenses)) {
    echo '<table>
        <tr><td colspan="9" class="summary"><strong>DETAILED EXPENSE LIST</strong></td></tr>
        <tr>
            <th>Date</th>
            <th>Project Code</th>
            <th>Project Name</th>
            <th>Rig</th>
            <th>Category</th>
            <th>Expense Type</th>
            <th>Description</th>
            <th>Supplier</th>
            th>Contact Person</th>
            <th>Phone</th>
            <th>Amount</th>
        </tr>';
    
    $total_amount = 0;
    foreach ($expenses as $expense) {
        echo '<tr>
            <td>' . $expense['formatted_date'] . '</td>
            <td>' . $expense['project_code'] . '</td>
            <td>' . $expense['project_name'] . '</td>
            <td>' . ($expense['rig_name'] ?? 'N/A') . '</td>
            <td>' . $expense['category'] . '</td>
            <td>' . $expense['expense_type'] . '</td>
            <td>' . $expense['expense_name'] . '</td>
            <td>' . ($expense['supplier_name'] ?? 'N/A') . '</td>
            <td>' . ($expense['contact_person'] ?? 'N/A') . '</td>
            <td>' . ($expense['phone'] ?? 'N/A') . '</td>
            <td>' . formatCurrency($expense['amount']) . '</td>
        </tr>';
        $total_amount += $expense['amount'];
    }
    
    echo '<tr class="total">
        <td colspan="8"><strong>TOTAL</strong></td>
        <td><strong>' . formatCurrency($total_amount) . '</strong></td>
    </tr>';
    echo '</table>';
}

echo '</body></html>';
?>