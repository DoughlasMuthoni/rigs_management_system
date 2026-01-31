<?php
if (!defined('ROLE_ADMIN')) {
    require_once __DIR__ . '/../config/constants.php';
}

require_once 'db_connect.php';


function formatCurrency($amount) {
    // Always use 'Ksh' - ignore any constant that might be wrong
    return 'Ksh ' . number_format($amount, 2);
}

// Calculate project profit (UPDATED to include all expense systems)
function calculateProjectProfit($project_id) {
    // Get project revenue
    $project = fetchOne("SELECT payment_received FROM projects WHERE id = $project_id");
    if (!$project) return 0;
    
    $revenue = $project['payment_received'];
    
    // Get total expenses from all systems
    $total_expenses = getTotalProjectExpensesAllSystems($project_id);
    
    // Calculate profit
    $profit = $revenue - $total_expenses;
    
    return $profit;
}


/**
 * Get project expenses from NEW system only (expenses table)
 */
function getProjectExpenses($project_id) {
    $sql = "SELECT 
                SUM(e.amount) as total_expenses,
                SUM(CASE WHEN et.category = 'Direct Costs' THEN e.amount ELSE 0 END) as direct_costs,
                SUM(CASE WHEN et.category = 'Indirect Costs' THEN e.amount ELSE 0 END) as indirect_costs,
                SUM(CASE WHEN et.category = 'Personnel' THEN e.amount ELSE 0 END) as personnel_costs
            FROM expenses e
            JOIN expense_types et ON e.expense_type_id = et.id
            WHERE e.project_id = $project_id
            AND e.status IN ('approved', 'paid')";
    
    $result = fetchOne($sql);
    
    if (!$result) {
        return [
            'total' => 0,
            'direct_costs' => 0,
            'indirect_costs' => 0,
            'personnel_costs' => 0
        ];
    }
    
    return [
        'total' => $result['total_expenses'] ?? 0,
        'direct_costs' => $result['direct_costs'] ?? 0,
        'indirect_costs' => $result['indirect_costs'] ?? 0,
        'personnel_costs' => $result['personnel_costs'] ?? 0
    ];
}
/**
 * Get total expenses for project from OLD system only (for backward compatibility)
 */
/*
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
    
    if (!$data) {
        return [
            'fixed' => 0,
            'consumables' => 0,
            'miscellaneous' => 0,
            'total' => 0
        ];
    }
    
    $fixed = $data['fixed_expenses'] ?? 0;
    $consumables = $data['consumables_total'] ?? 0;
    $miscellaneous = $data['misc_total'] ?? 0;
    $total = $fixed + $consumables + $miscellaneous;
    
    return [
        'fixed' => $fixed,
        'consumables' => $consumables,
        'miscellaneous' => $miscellaneous,
        'total' => $total
    ];
}
*/

/**
 * Get total expenses for project from ALL systems (OLD + NEW)
 */
function getTotalProjectExpensesAllSystems($project_id) {
    // Get expenses from OLD system
    // $old_expenses = getProjectExpenses($project_id);
    // $old_total = $old_expenses['total'] ?? 0;
    $old_total = 0; // Set to 0 since old tables are removed
    
    // Get expenses from NEW system (expenses table)
    $sql = "SELECT SUM(amount) as total_new_expenses FROM expenses WHERE project_id = $project_id";
    $new_expenses = fetchOne($sql);
    $new_total = $new_expenses['total_new_expenses'] ?? 0;
    
    // Get extra expenses from NEW system
    $sql_extra = "SELECT SUM(ee.amount) as total_extra_expenses 
                 FROM extra_expenses ee
                 JOIN expenses e ON ee.expense_id = e.id
                 WHERE e.project_id = $project_id";
    $extra_expenses = fetchOne($sql_extra);
    $extra_total = $extra_expenses['total_extra_expenses'] ?? 0;
    
    // Calculate total from all systems
    $total_expenses = $old_total + $new_total + $extra_total;
    
    return $total_expenses;
}

/**
 * Get complete expense breakdown from ALL systems
 */
function getCompleteProjectExpenses($project_id) {
    // Get expenses from OLD system
    // $old_expenses = getProjectExpenses($project_id);
    // $old_total = $old_expenses['total'] ?? 0;
    $old_total = 0;
    
    // Get expenses from NEW system (expenses table)
    $sql = "SELECT SUM(amount) as total_new_expenses FROM expenses WHERE project_id = $project_id";
    $new_expenses = fetchOne($sql);
    $new_total = $new_expenses['total_new_expenses'] ?? 0;
    
    // Get extra expenses from NEW system
    $sql_extra = "SELECT SUM(ee.amount) as total_extra_expenses 
                 FROM extra_expenses ee
                 JOIN expenses e ON ee.expense_id = e.id
                 WHERE e.project_id = $project_id";
    $extra_expenses = fetchOne($sql_extra);
    $extra_total = $extra_expenses['total_extra_expenses'] ?? 0;
    
    // Calculate total from all systems
    $total_expenses = $old_total + $new_total + $extra_total;
    
    return [
        'old_total' => $old_total,
        'new_total' => $new_total,
        'extra_total' => $extra_total,
        'total' => $total_expenses,
        'old_breakdown' => [] // Empty array since old tables are removed
    ];
}

// Get monthly performance for a rig (UPDATED)
// Get monthly performance for a rig or unassigned projects
function getRigMonthlyPerformance($rig_id, $month, $year) {
    if ($rig_id == 0) {
        // Get unassigned projects
        $sql = "SELECT 
            p.id,
            p.project_code,
            p.project_name,
            p.contract_amount,
            p.payment_received,
            p.completion_date
        FROM projects p
        WHERE (p.rig_id IS NULL OR p.rig_id = 0 OR p.rig_id = '')
        AND YEAR(p.completion_date) = $year
        AND MONTH(p.completion_date) = $month
        AND p.status IN ('completed', 'paid')";
    } else {
        // Get projects for specific rig
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
    }
    
    $projects = fetchAll($sql);
    $total_revenue = 0;
    $total_expenses = 0;
    $total_profit = 0;
    
    foreach ($projects as $project) {
        $expenses = getTotalProjectExpensesAllSystems($project['id']);
        $total_revenue += $project['payment_received'];
        $total_expenses += $expenses;
        $total_profit += ($project['payment_received'] - $expenses);
    }
    
    return [
        'revenue' => $total_revenue,
        'expenses' => $total_expenses,
        'profit' => $total_profit,
        'project_count' => count($projects),
        'profit_margin' => $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0
    ];
}

// Get all rigs with monthly summary (UPDATED)
// Get all rigs with monthly summary - INCLUDES UNASSIGNED PROJECTS
function getAllRigsMonthlySummary($month, $year) {
    $summary = [];
    
    // 1. Get all active rigs
    $sql = "SELECT * FROM rigs WHERE status = 'active'";
    $rigs = fetchAll($sql);
    
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
    
    // 2. Add "Unassigned Projects" section
    $unassigned_performance = getUnassignedProjectsMonthlyPerformance($month, $year);
    if ($unassigned_performance['project_count'] > 0 || $unassigned_performance['revenue'] > 0) {
        $summary[] = [
            'rig_id' => 0,
            'rig_name' => 'Unassigned Projects',
            'rig_code' => 'N/A',
            'revenue' => $unassigned_performance['revenue'],
            'expenses' => $unassigned_performance['expenses'],
            'profit' => $unassigned_performance['profit'],
            'project_count' => $unassigned_performance['project_count'],
            'profit_margin' => $unassigned_performance['profit_margin']
        ];
    }
    
    return $summary;
}

// Get monthly performance for unassigned projects
function getUnassignedProjectsMonthlyPerformance($month, $year) {
    $sql = "SELECT 
        p.id,
        p.project_code,
        p.project_name,
        p.contract_amount,
        p.payment_received,
        p.completion_date
    FROM projects p
    WHERE (p.rig_id IS NULL OR p.rig_id = 0 OR p.rig_id = '')
    AND YEAR(p.completion_date) = $year
    AND MONTH(p.completion_date) = $month
    AND p.status IN ('completed', 'paid')";
    
    $projects = fetchAll($sql);
    $total_revenue = 0;
    $total_expenses = 0;
    $total_profit = 0;
    
    foreach ($projects as $project) {
        $expenses = getTotalProjectExpensesAllSystems($project['id']);
        $total_revenue += $project['payment_received'];
        $total_expenses += $expenses;
        $total_profit += ($project['payment_received'] - $expenses);
    }
    
    return [
        'revenue' => $total_revenue,
        'expenses' => $total_expenses,
        'profit' => $total_profit,
        'project_count' => count($projects),
        'profit_margin' => $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0
    ];
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

/**
 * Get total monthly salary to be allocated
 */
function getMonthlySalaryToAllocate($month, $year) {
    return isset($_SESSION['monthly_salary_' . $year . '_' . $month]) 
        ? floatval($_SESSION['monthly_salary_' . $year . '_' . $month])
        : 0;
}

/**
 * Set monthly salary amount (for allocation)
 */
function setMonthlySalaryToAllocate($month, $year, $amount) {
    $_SESSION['monthly_salary_' . $year . '_' . $month] = $amount;
    return true;
}

/**
 * Allocate monthly salaries to projects (Equal distribution)
 */
/*
function allocateSalariesToMonthlyProjects($month, $year, $totalSalary) {
    // Save monthly salary total for reference
    saveMonthlySalarySummary($month, $year, $totalSalary, 'equal');
    
    // Get all completed projects for the month
    $projects = fetchAll("
        SELECT id, payment_received 
        FROM projects 
        WHERE MONTH(completion_date) = $month 
        AND YEAR(completion_date) = $year
        AND status = 'completed'
    ");
    
    if (empty($projects)) {
        return ['success' => false, 'message' => 'No projects found for this month'];
    }
    
    $projectCount = count($projects);
    $salaryPerProject = $totalSalary / $projectCount;
    
    // Update each project's fixed_expenses salary
    foreach ($projects as $project) {
        $projectId = $project['id'];
        
        // Check if fixed_expenses exists
        $fixedExpenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = $projectId");
        
        if ($fixedExpenses) {
            // Update existing record
            $updateSql = "UPDATE fixed_expenses 
                         SET salaries = $salaryPerProject, 
                             salary_source = 'monthly',
                             updated_at = NOW()
                         WHERE project_id = $projectId";
        } else {
            // Create new record
            $updateSql = "INSERT INTO fixed_expenses 
                         (project_id, salaries, salary_source, created_at) 
                         VALUES ($projectId, $salaryPerProject, 'monthly', NOW())";
        }
        
        query($updateSql);
    }
    
    return [
        'success' => true, 
        'message' => "Allocated $totalSalary across $projectCount projects",
        'salary_per_project' => $salaryPerProject
    ];
}
*/

/**
 * Allocate salaries based on project revenue percentage
 */
/*
function allocateSalariesByRevenuePercentage($month, $year, $totalSalary) {
    saveMonthlySalarySummary($month, $year, $totalSalary, 'revenue');
    
    $projects = fetchAll("
        SELECT id, payment_received 
        FROM projects 
        WHERE MONTH(completion_date) = $month 
        AND YEAR(completion_date) = $year
        AND status = 'completed'
    ");
    
    if (empty($projects)) {
        return ['success' => false, 'message' => 'No projects found for this month'];
    }
    
    $totalRevenue = array_sum(array_column($projects, 'payment_received'));
    
    if ($totalRevenue <= 0) {
        return allocateSalariesToMonthlyProjects($month, $year, $totalSalary);
    }
    
    foreach ($projects as $project) {
        $projectId = $project['id'];
        $projectRevenue = $project['payment_received'];
        $percentage = ($totalRevenue > 0) ? ($projectRevenue / $totalRevenue) * 100 : 0;
        $allocatedSalary = ($projectRevenue / $totalRevenue) * $totalSalary;
        
        $fixedExpenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = $projectId");
        
        if ($fixedExpenses) {
            $updateSql = "UPDATE fixed_expenses 
                         SET salaries = $allocatedSalary, 
                             salary_source = 'monthly',
                             updated_at = NOW()
                         WHERE project_id = $projectId";
        } else {
            $updateSql = "INSERT INTO fixed_expenses 
                         (project_id, salaries, salary_source, created_at) 
                         VALUES ($projectId, $allocatedSalary, 'monthly', NOW())";
        }
        
        query($updateSql);
    }
    
    return [
        'success' => true, 
        'message' => "Allocated $totalSalary based on revenue percentages"
    ];
}
*/

/**
 * Save monthly salary summary
 */
/*
function saveMonthlySalarySummary($month, $year, $totalAmount, $method) {
    // Check if exists
    $existing = fetchOne("
        SELECT id FROM monthly_salary_summary 
        WHERE month = $month AND year = $year
    ");
    
    if ($existing) {
        $sql = "UPDATE monthly_salary_summary 
                SET total_monthly_salary = $totalAmount,
                    allocation_method = '$method',
                    allocation_date = NOW()
                WHERE month = $month AND year = $year";
    } else {
        $sql = "INSERT INTO monthly_salary_summary 
                (month, year, total_monthly_salary, allocation_method, allocation_date)
                VALUES ($month, $year, $totalAmount, '$method', NOW())";
    }
    
    return query($sql);
}
*/

/**
 * Get monthly salary allocation summary
 */
/*
function getMonthlySalarySummary($month, $year) {
    $summary = fetchOne("
        SELECT * FROM monthly_salary_summary 
        WHERE month = $month AND year = $year
    ");
    
    if (!$summary) {
        return [
            'month' => $month,
            'year' => $year,
            'total_monthly_salary' => 0,
            'allocation_method' => 'none',
            'allocated' => false
        ];
    }
    
    // Get project allocations
    $projects = fetchAll("
        SELECT p.id, p.project_code, p.project_name, p.payment_received,
               fe.salaries as allocated_salary
        FROM projects p
        LEFT JOIN fixed_expenses fe ON p.id = fe.project_id AND fe.salary_source = 'monthly'
        WHERE MONTH(p.completion_date) = $month 
        AND YEAR(p.completion_date) = $year
        AND p.status = 'completed'
        AND fe.salaries > 0
    ");
    
    $summary['projects'] = $projects;
    $summary['allocated'] = true;
    
    return $summary;
}
*/

/**
 * Calculate project profit with monthly salary consideration
 */
/*
function calculateProjectProfitWithMonthlySalary($project_id, $month = null, $year = null) {
    // If month/year not provided, get from project completion date
    if (!$month || !$year) {
        $project = fetchOne("SELECT * FROM projects WHERE id = $project_id");
        if ($project && $project['completion_date']) {
            $month = date('m', strtotime($project['completion_date']));
            $year = date('Y', strtotime($project['completion_date']));
        }
    }
    
    // Get all expenses including monthly allocated salary
    $sql = "SELECT 
        p.contract_amount,
        p.payment_received,
        COALESCE(fe.salaries, 0) as salaries,
        COALESCE(fe.fuel_rig, 0) + 
        COALESCE(fe.fuel_truck, 0) + 
        COALESCE(fe.fuel_pump, 0) + 
        COALESCE(fe.fuel_hired, 0) + 
        COALESCE(fe.casing_surface, 0) + 
        COALESCE(fe.casing_screened, 0) + 
        COALESCE(fe.casing_plain, 0) as other_expenses,
        COALESCE((SELECT SUM(amount) FROM consumables WHERE project_id = p.id), 0) as consumables_total,
        COALESCE((SELECT SUM(amount) FROM miscellaneous WHERE project_id = p.id), 0) as misc_total,
        COALESCE(fe.salary_source, 'project') as salary_source
    FROM projects p
    LEFT JOIN fixed_expenses fe ON p.id = fe.project_id
    WHERE p.id = $project_id";
    
    $data = fetchOne($sql);
    
    if (!$data) return [
        'profit' => 0,
        'salaries' => 0,
        'salary_source' => 'project',
        'other_expenses' => 0,
        'consumables' => 0,
        'misc' => 0,
        'total_expenses' => 0,
        'revenue' => 0
    ];
    
    $total_expenses = $data['salaries'] + 
                     $data['other_expenses'] + 
                     $data['consumables_total'] + 
                     $data['misc_total'];
    
    $profit = $data['payment_received'] - $total_expenses;
    
    return [
        'profit' => $profit,
        'salaries' => $data['salaries'],
        'salary_source' => $data['salary_source'],
        'other_expenses' => $data['other_expenses'],
        'consumables' => $data['consumables_total'],
        'misc' => $data['misc_total'],
        'total_expenses' => $total_expenses,
        'revenue' => $data['payment_received']
    ];
}
*/


// Updated functions.php with new functionality

/**
 * Get all customers
 */
function getAllCustomers($status = 'active') {
    $where = $status ? "WHERE status = '$status'" : "";
    return fetchAll("SELECT * FROM customers $where ORDER BY first_name, last_name");
}

/**
 * Get customer by ID
 */
function getCustomerById($customer_id) {
    return fetchOne("SELECT * FROM customers WHERE id = $customer_id");
}

/**
 * Get customer projects
 */
function getCustomerProjects($customer_id) {
    return fetchAll("SELECT p.*, r.rig_name 
                    FROM projects p 
                    LEFT JOIN rigs r ON p.rig_id = r.id
                    WHERE p.customer_id = $customer_id 
                    ORDER BY p.completion_date DESC");
}

/**
 * Get project details with customer info
 */
function getProjectWithCustomer($project_id) {
    return fetchOne("SELECT p.*, c.*, r.rig_name, r.rig_code
                    FROM projects p
                    LEFT JOIN customers c ON p.customer_id = c.id
                    LEFT JOIN rigs r ON p.rig_id = r.id
                    WHERE p.id = $project_id");
}

/**
 * Get all suppliers
 */
function getAllSuppliers($status = 'active') {
    $where = $status ? "WHERE status = '$status'" : "";
    return fetchAll("SELECT * FROM suppliers $where ORDER BY supplier_name");
}

/**
 * Get all vehicles
 */
function getAllVehicles($status = 'active') {
    $where = $status ? "WHERE status = '$status'" : "";
    return fetchAll("SELECT * FROM vehicles $where ORDER BY vehicle_no");
}

/**
 * Get all expense types
 */
function getAllExpenseTypes($category = null) {
    $where = $category ? "WHERE category = '$category'" : "";
    return fetchAll("SELECT * FROM expense_types $where ORDER BY category, expense_name");
}

/**
 * Get project expenses from NEW system only
 */
function getProjectExpensesNewSystem($project_id) {
    return fetchAll("SELECT e.*, et.category, et.expense_name, s.supplier_name, v.vehicle_no
                    FROM expenses e
                    LEFT JOIN expense_types et ON e.expense_type_id = et.id
                    LEFT JOIN suppliers s ON e.supplier_id = s.id
                    LEFT JOIN vehicles v ON e.vehicle_id = v.id
                    WHERE e.project_id = $project_id
                    ORDER BY e.expense_date DESC");
}

/**
 * Get rig monthly expenses breakdown
 */
function getRigMonthlyExpenses($rig_id, $month, $year) {
    $sql = "SELECT 
                et.category,
                et.expense_name,
                SUM(e.amount) as total_amount,
                COUNT(e.id) as expense_count
            FROM expenses e
            JOIN projects p ON e.project_id = p.id
            JOIN expense_types et ON e.expense_type_id = et.id
            WHERE p.rig_id = $rig_id
            AND MONTH(e.expense_date) = $month
            AND YEAR(e.expense_date) = $year
            GROUP BY et.category, et.expense_name
            ORDER BY et.category, total_amount DESC";
    
    return fetchAll($sql);
}

/**
 * Get monthly rig report data
 */
function getMonthlyRigReport($rig_id, $month, $year) {
    // Get projects for the month
    $projects = fetchAll("SELECT p.* FROM projects p 
                         WHERE p.rig_id = $rig_id
                         AND MONTH(p.completion_date) = $month
                         AND YEAR(p.completion_date) = $year
                         AND p.status = 'completed'");
    
    $total_revenue = 0;
    $total_expenses = 0;
    $project_count = count($projects);
    
    foreach ($projects as $project) {
        $total_revenue += $project['payment_received'];
        
        // Get project expenses from ALL systems
        $expenses = getTotalProjectExpensesAllSystems($project['id']);
        $total_expenses += $expenses;
    }
    
    $total_profit = $total_revenue - $total_expenses;
    $profit_margin = $total_revenue > 0 ? ($total_profit / $total_revenue) * 100 : 0;
    
    return [
        'rig_id' => $rig_id,
        'month' => $month,
        'year' => $year,
        'total_revenue' => $total_revenue,
        'total_expenses' => $total_expenses,
        'total_profit' => $total_profit,
        'profit_margin' => $profit_margin,
        'project_count' => $project_count,
        'projects' => $projects
    ];
}

/**
 * Calculate customer lifetime value
 */
function calculateCustomerLifetimeValue($customer_id) {
    $sql = "SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.company_name,
                COUNT(p.id) as project_count,
                SUM(p.contract_amount) as total_contracts,
                SUM(p.payment_received) as total_payments,
                MIN(p.start_date) as first_project,
                MAX(p.completion_date) as last_project
            FROM customers c
            LEFT JOIN projects p ON c.id = p.customer_id
            WHERE c.id = $customer_id
            GROUP BY c.id";
    
    return fetchOne($sql);
}

/**
 * Get rig performance summary
 */
function getRigPerformanceSummary($rig_id, $start_date = null, $end_date = null) {
    $where = "WHERE p.rig_id = $rig_id AND p.status = 'completed'";
    
    if ($start_date) {
        $where .= " AND p.completion_date >= '$start_date'";
    }
    if ($end_date) {
        $where .= " AND p.completion_date <= '$end_date'";
    }
    
    $sql = "SELECT 
                r.rig_name,
                r.rig_code,
                COUNT(p.id) as total_projects,
                SUM(p.contract_amount) as total_contract_value,
                SUM(p.payment_received) as total_revenue,
                AVG(p.depth) as average_depth,
                MIN(p.completion_date) as first_project_date,
                MAX(p.completion_date) as last_project_date
            FROM rigs r
            LEFT JOIN projects p ON r.id = p.rig_id
            $where
            GROUP BY r.id";
    
    return fetchOne($sql);
}



/**
 * Generate expense report - FIXED VERSION for dashboard
 */
function generateExpenseReport($filters = []) {
    $where = "WHERE 1=1";
    
    if (isset($filters['project_id']) && $filters['project_id'] > 0) {
        $where .= " AND e.project_id = " . intval($filters['project_id']);
    }
    
    if (isset($filters['rig_id']) && $filters['rig_id'] > 0) {
        $where .= " AND p.rig_id = " . intval($filters['rig_id']);
    }
    
    if (isset($filters['supplier_id']) && $filters['supplier_id'] > 0) {
        $where .= " AND e.supplier_id = " . intval($filters['supplier_id']);
    }
    
    if (isset($filters['expense_type_id']) && $filters['expense_type_id'] > 0) {
        $where .= " AND e.expense_type_id = " . intval($filters['expense_type_id']);
    }
    
    if (isset($filters['start_date']) && !empty($filters['start_date'])) {
        $where .= " AND e.expense_date >= '" . escape($filters['start_date']) . "'";
    }
    
    if (isset($filters['end_date']) && !empty($filters['end_date'])) {
        $where .= " AND e.expense_date <= '" . escape($filters['end_date']) . "'";
    }
    
    // IMPORTANT: Make status filter optional - only apply if status is specified and not 'all'
    if (isset($filters['status']) && !empty($filters['status']) && $filters['status'] != 'all') {
        $where .= " AND e.status = '" . escape($filters['status']) . "'";
    }
    
    // ADDED: Filter by asset_type
    if (isset($filters['asset_type']) && !empty($filters['asset_type'])) {
        $where .= " AND e.asset_type = '" . escape($filters['asset_type']) . "'";
    }
    if (isset($filters['project_rig_id']) && $filters['project_rig_id'] > 0) {
    $where .= " AND p.rig_id = " . intval($filters['project_rig_id']);
    }

    if (isset($filters['expense_rig_id']) && $filters['expense_rig_id'] > 0) {
        $where .= " AND e.rig_id = " . intval($filters['expense_rig_id']);
    }
    // Use INNER JOIN for projects and expense_types like the working query
    $sql = "SELECT 
                e.id,
                e.expense_code,
                e.project_id,
                e.supplier_id,
                e.expense_type_id,
                e.ref_number,
                e.expense_date,
                e.vehicle_id,
                e.rig_id,                -- ADDED: rig_id from expenses table
                e.asset_type,            -- ADDED: asset_type from expenses table
                e.amount,
                e.quantity,
                e.unit_price,
                e.notes,
                e.receipt_path,
                e.status,
                e.created_at,
                e.updated_at,
                p.project_code,
                COALESCE(p.project_name, 'No Project') as project_name,
                c.first_name,
                c.last_name,
                c.company_name,
                et.expense_name,
                et.category,
                COALESCE(s.supplier_name, 'No Supplier') as supplier_name,
                -- Get vehicle details if vehicle is selected
                COALESCE(v.vehicle_no, 'N/A') as vehicle_no,
                COALESCE(v.vehicle_type, 'N/A') as vehicle_type,
                -- Get rig details if rig is selected (from expenses.rig_id)
                COALESCE(r_expense.rig_name, 'N/A') as rig_name,
                COALESCE(r_expense.rig_code, 'N/A') as rig_code,
                -- Also keep the project rig for backward compatibility
                COALESCE(r_project.rig_name, 'No Rig') as project_rig_name
            FROM expenses e
            INNER JOIN projects p ON e.project_id = p.id
            INNER JOIN expense_types et ON e.expense_type_id = et.id
            LEFT JOIN customers c ON p.customer_id = c.id
            LEFT JOIN suppliers s ON e.supplier_id = s.id
            -- Join with vehicles table for vehicle expenses
            LEFT JOIN vehicles v ON e.vehicle_id = v.id
            -- Join with rigs table for rig expenses (using expenses.rig_id)
            LEFT JOIN rigs r_expense ON e.rig_id = r_expense.id
            -- Also join with rigs for project rig (backward compatibility)
            LEFT JOIN rigs r_project ON p.rig_id = r_project.id
            $where
            ORDER BY e.expense_date DESC, e.created_at DESC";
    
    return fetchAll($sql);
}

/**
 * Save uploaded receipt
 */
function saveExpenseReceipt($expense_id, $file_data) {
    $upload_dir = ROOT_PATH . '/uploads/expenses/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = 'expense_' . $expense_id . '_' . time() . '_' . basename($file_data['name']);
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file_data['tmp_name'], $file_path)) {
        $relative_path = '/uploads/expenses/' . $file_name;
        query("UPDATE expenses SET receipt_path = '$relative_path' WHERE id = $expense_id");
        return $relative_path;
    }
    
    return false;
}

function getExpenseBreakdownByCategory($project_id) {
    $sql = "SELECT 
                et.category,
                et.expense_name,
                e.amount,
                s.supplier_name,
                e.expense_date
            FROM expenses e
            JOIN expense_types et ON e.expense_type_id = et.id
            LEFT JOIN suppliers s ON e.supplier_id = s.id
            WHERE e.project_id = $project_id
            ORDER BY et.category, e.expense_date DESC";
    
    $expenses = fetchAll($sql);
    
    $breakdown = [];
    foreach ($expenses as $expense) {
        $category = $expense['category'];
        if (!isset($breakdown[$category])) {
            $breakdown[$category] = [
                'total' => 0,
                'items' => []
            ];
        }
        $breakdown[$category]['total'] += $expense['amount'];
        $breakdown[$category]['items'][] = $expense;
    }
    
    return $breakdown;
}
// Add this function to your existing functions.php file
function buildCustomerReportWhereClause($start_date, $end_date, $customer_type_filter, 
                                        $table_alias = 'p', $customer_alias = 'c') {
    global $conn; // Make sure you have database connection available
    
    $conditions = [];
    
    // Date filtering on projects table
    if ($start_date && $end_date) {
        $start_escaped = mysqli_real_escape_string($conn, $start_date);
        $end_escaped = mysqli_real_escape_string($conn, $end_date);
        $conditions[] = "($table_alias.completion_date IS NOT NULL 
                          AND $table_alias.completion_date BETWEEN '$start_escaped' 
                          AND '$end_escaped')";
    }
    
    // Customer type filtering on customers table
    if ($customer_type_filter == 'corporate') {
        $conditions[] = "$customer_alias.company_name IS NOT NULL 
                         AND $customer_alias.company_name != ''";
    } elseif ($customer_type_filter == 'individual') {
        $conditions[] = "($customer_alias.company_name IS NULL 
                         OR $customer_alias.company_name = '')";
    }
    
    return !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
}
?>