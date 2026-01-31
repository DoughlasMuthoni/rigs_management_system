<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Get filters from URL
$filters = $_GET;
unset($filters['export']); // Remove export parameter if exists

// Generate expense report
$expenses = generateExpenseReport($filters);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=expenses_export_' . date('Ymd_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Expense Code',
    'Date',
    'Project Code',
    'Project Name',
    'Customer',
    'Expense Type',
    'Category',
    'Supplier',
    'Reference Number',
    'Amount',
    'Quantity',
    'Unit Price',
    'Status',
    'Notes',
    'Receipt Path',
    'Created Date'
]);

// Add data rows
foreach ($expenses as $expense) {
    // Prepare customer name
    $customer_name = '';
    if (!empty($expense['company_name'])) {
        $customer_name = $expense['company_name'];
    } elseif (!empty($expense['first_name']) || !empty($expense['last_name'])) {
        $customer_name = trim($expense['first_name'] . ' ' . $expense['last_name']);
    }
    
    fputcsv($output, [
        $expense['expense_code'],
        $expense['expense_date'],
        $expense['project_code'] ?? '',
        $expense['project_name'] ?? '',
        $customer_name,
        $expense['expense_name'] ?? '',
        $expense['category'] ?? '',
        $expense['supplier_name'] ?? '',
        $expense['ref_number'] ?? '',
        $expense['amount'],
        $expense['quantity'],
        $expense['unit_price'],
        $expense['status'],
        $expense['notes'] ?? '',
        $expense['receipt_path'] ?? '',
        $expense['created_at']
    ]);
}

fclose($output);
exit();