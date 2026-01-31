<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Get filters
$search_where = "WHERE 1=1";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = escape($_GET['search']);
    $search_where .= " AND (
        first_name LIKE '%$search_term%' OR 
        last_name LIKE '%$search_term%' OR 
        company_name LIKE '%$search_term%' OR 
        email LIKE '%$search_term%' OR 
        phone LIKE '%$search_term%'
    )";
}

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = escape($_GET['status']);
    $search_where .= " AND status = '$status'";
}

// Get customers with project counts
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM projects p WHERE p.customer_id = c.id) as project_count,
        (SELECT SUM(payment_received) FROM projects p WHERE p.customer_id = c.id) as total_revenue
        FROM customers c 
        $search_where 
        ORDER BY first_name, last_name";
$customers = fetchAll($sql);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=customers_export_' . date('Ymd_His') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'Customer Code',
    'First Name',
    'Last Name',
    'Company Name',
    'Email',
    'Phone',
    'Alternate Phone',
    'Address',
    'City',
    'Country',
    'Tax ID',
    'Status',
    'Total Projects',
    'Total Revenue',
    'Created Date',
    'Notes'
]);

// Add data rows
foreach ($customers as $customer) {
    fputcsv($output, [
        $customer['customer_code'],
        $customer['first_name'],
        $customer['last_name'],
        $customer['company_name'],
        $customer['email'],
        $customer['phone'],
        $customer['phone2'],
        $customer['address'],
        $customer['city'],
        $customer['country'],
        $customer['tax_id'],
        $customer['status'],
        $customer['project_count'],
        $customer['total_revenue'] ?? 0,
        $customer['created_at'],
        $customer['notes']
    ]);
}

fclose($output);
exit();
?>