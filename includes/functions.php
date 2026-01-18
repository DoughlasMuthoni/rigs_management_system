<?php
if (!defined('ROLE_ADMIN')) {
    require_once __DIR__ . '/../config/constants.php';
}

require_once 'db_connect.php';

// Format currency
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

// Calculate project profit
function calculateProjectProfit($project_id) {
    $sql = "SELECT 
        p.contract_amount,
        p.payment_received,
        COALESCE(fe.salaries, 0) + 
        COALESCE(fe.fuel_rig, 0) + 
        COALESCE(fe.fuel_truck, 0) + 
        COALESCE(fe.fuel_pump, 0) + 
        COALESCE(fe.fuel_hired, 0) + 
        COALESCE(fe.casing_surface, 0) + 
        COALESCE(fe.casing_screened, 0) + 
        COALESCE(fe.casing_plain, 0) as fixed_expenses,
        COALESCE((SELECT SUM(amount) FROM consumables WHERE project_id = p.id), 0) as consumables_total,
        COALESCE((SELECT SUM(amount) FROM miscellaneous WHERE project_id = p.id), 0) as misc_total
    FROM projects p
    LEFT JOIN fixed_expenses fe ON p.id = fe.project_id
    WHERE p.id = $project_id";
    
    $data = fetchOne($sql);
    
    if (!$data) return 0;
    
    $total_expenses = $data['fixed_expenses'] + $data['consumables_total'] + $data['misc_total'];
    $profit = $data['payment_received'] - $total_expenses;
    
    return $profit;
}

// Get total expenses for project
function getProjectExpenses($project_id) {
    $sql = "SELECT 
        COALESCE(fe.salaries, 0) + 
        COALESCE(fe.fuel_rig, 0) + 
        COALESCE(fe.fuel_truck, 0) + 
        COALESCE(fe.fuel_pump, 0) + 
        COALESCE(fe.fuel_hired, 0) + 
        COALESCE(fe.casing_surface, 0) + 
        COALESCE(fe.casing_screened, 0) + 
        COALESCE(fe.casing_plain, 0) as fixed_expenses,
        COALESCE((SELECT SUM(amount) FROM consumables WHERE project_id = $project_id), 0) as consumables_total,
        COALESCE((SELECT SUM(amount) FROM miscellaneous WHERE project_id = $project_id), 0) as misc_total
    FROM projects p
    LEFT JOIN fixed_expenses fe ON p.id = fe.project_id
    WHERE p.id = $project_id";
    
    $data = fetchOne($sql);
    
    return [
        'fixed' => $data['fixed_expenses'],
        'consumables' => $data['consumables_total'],
        'miscellaneous' => $data['misc_total'],
        'total' => $data['fixed_expenses'] + $data['consumables_total'] + $data['misc_total']
    ];
}

// Get monthly performance for a rig
function getRigMonthlyPerformance($rig_id, $month, $year) {
    $sql = "SELECT 
        p.id,
        p.project_code,
        p.project_name,
        p.contract_amount,
        p.payment_received,
        p.completion_date
    FROM projects p
    WHERE p.rig_id = $rig_id
    AND YEAR(p.completion_date) = $year
    AND MONTH(p.completion_date) = $month
    AND p.status IN ('completed', 'paid')";
    
    $projects = fetchAll($sql);
    $total_revenue = 0;
    $total_expenses = 0;
    $total_profit = 0;
    
    foreach ($projects as $project) {
        $expenses = getProjectExpenses($project['id']);
        $total_revenue += $project['payment_received'];
        $total_expenses += $expenses['total'];
        $total_profit += ($project['payment_received'] - $expenses['total']);
    }
    
    return [
        'revenue' => $total_revenue,
        'expenses' => $total_expenses,
        'profit' => $total_profit,
        'project_count' => count($projects),
        'profit_margin' => $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0
    ];
}

// Get all rigs with monthly summary
function getAllRigsMonthlySummary($month, $year) {
    $sql = "SELECT * FROM rigs WHERE status = 'active'";
    $rigs = fetchAll($sql);
    
    $summary = [];
    foreach ($rigs as $rig) {
        $performance = getRigMonthlyPerformance($rig['id'], $month, $year);
        $summary[] = [
            'rig_id' => $rig['id'],
            'rig_name' => $rig['rig_name'],
            'rig_code' => $rig['rig_code'],
            'revenue' => $performance['revenue'],
            'expenses' => $performance['expenses'],
            'profit' => $performance['profit'],
            'project_count' => $performance['project_count'],
            'profit_margin' => $performance['profit_margin']
        ];
    }
    
    return $summary;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

// Check user role
function hasRole($required_role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $user_role = $_SESSION['role'];
    
    // Admin has access to everything
    if ($user_role == ROLE_ADMIN) {
        return true;
    }
    
    // Check specific role
    return $user_role == $required_role;
}
?>