<?php
if (!defined('ROLE_ADMIN')) {
    require_once __DIR__ . '/../config/constants.php';
}

require_once 'db_connect.php';

// Format currency
// function formatCurrency($amount) {
//     return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
// }
// Format currency
function formatCurrency($amount) {
    // Always use 'Ksh' - ignore any constant that might be wrong
    return 'Ksh ' . number_format($amount, 2);
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

/**
 * Allocate salaries based on project revenue percentage
 */
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

/**
 * Save monthly salary summary
 */
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

/**
 * Get monthly salary allocation summary
 */
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

/**
 * Calculate project profit with monthly salary consideration
 */
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
    
    if (!$data) return 0;
    
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
?>