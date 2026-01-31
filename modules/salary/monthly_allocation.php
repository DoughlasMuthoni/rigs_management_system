<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    
    if ($action == 'allocate') {
        $totalSalary = floatval($_POST['total_salary']);
        $method = $_POST['allocation_method'];
        $expense_date = date('Y-m-d');
        
        // Get completed projects for the month
        $projects = fetchAll("
            SELECT p.id, p.project_code, p.payment_received
            FROM projects p
            WHERE MONTH(p.completion_date) = $month 
            AND YEAR(p.completion_date) = $year
            AND p.status = 'completed'
        ");
        
        if (empty($projects)) {
            $message = "No completed projects found for " . date('F Y', strtotime("$year-$month-01"));
            $message_type = 'error';
        } else {
            $projectCount = count($projects);
            
            // Get Personnel expense type ID
            $personnel_type = fetchOne("SELECT id FROM expense_types WHERE category = 'Personnel' LIMIT 1");
            $expense_type_id = $personnel_type['id'] ?? 1;
            
            $allocated_count = 0;
            
            if ($method == 'equal') {
                $salaryPerProject = $totalSalary / $projectCount;
                
                foreach ($projects as $project) {
                    // Generate unique expense code
                    $unique_expense_code = 'SAL-' . date('YmdHis') . '-' . $project['id'] . '-' . uniqid();
                    
                    $notes = "Monthly salary allocation for " . date('F Y', strtotime("$year-$month-01")) . 
                             " - Equal distribution across $projectCount projects";
                    
                    $sql = "INSERT INTO expenses 
                            (expense_code, project_id, expense_type_id, ref_number, expense_date, 
                             amount, quantity, unit_price, notes, status, created_by, created_at) 
                            VALUES (
                                '" . escape($unique_expense_code) . "',
                                " . $project['id'] . ",
                                " . $expense_type_id . ",
                                'MONTHLY-SALARY',
                                '" . escape($expense_date) . "',
                                " . $salaryPerProject . ",
                                1,
                                " . $salaryPerProject . ",
                                '" . escape($notes) . "',
                                'approved',
                                " . $_SESSION['user_id'] . ",
                                NOW()
                            )";
                    
                    if (query($sql)) {
                        $allocated_count++;
                    }
                }
                
                $message = "Allocated Ksh " . number_format($totalSalary, 2) . " equally across " . $allocated_count . " projects";
                $message_type = 'success';
                
            } elseif ($method == 'revenue') {
                $totalRevenue = array_sum(array_column($projects, 'payment_received'));
                
                if ($totalRevenue <= 0) {
                    $message = "Cannot allocate by revenue: Total project revenue is 0";
                    $message_type = 'error';
                } else {
                    foreach ($projects as $project) {
                        $projectRevenue = $project['payment_received'];
                        $allocatedSalary = ($projectRevenue / $totalRevenue) * $totalSalary;
                        
                        // Generate unique expense code
                        $unique_expense_code = 'SAL-' . date('YmdHis') . '-' . $project['id'] . '-' . uniqid();
                        
                        $percentage = number_format(($projectRevenue/$totalRevenue)*100, 1);
                        $notes = "Monthly salary allocation for " . date('F Y', strtotime("$year-$month-01")) . 
                                 " - Revenue-based allocation ($percentage% of revenue)";
                        
                        $sql = "INSERT INTO expenses 
                                (expense_code, project_id, expense_type_id, ref_number, expense_date, 
                                 amount, quantity, unit_price, notes, status, created_by, created_at) 
                                VALUES (
                                    '" . escape($unique_expense_code) . "',
                                    " . $project['id'] . ",
                                    " . $expense_type_id . ",
                                    'MONTHLY-SALARY',
                                    '" . escape($expense_date) . "',
                                    " . $allocatedSalary . ",
                                    1,
                                    " . $allocatedSalary . ",
                                    '" . escape($notes) . "',
                                    'approved',
                                    " . $_SESSION['user_id'] . ",
                                    NOW()
                                )";
                        
                        if (query($sql)) {
                            $allocated_count++;
                        }
                    }
                    
                    $message = "Allocated Ksh " . number_format($totalSalary, 2) . " based on revenue percentages across " . $allocated_count . " projects";
                    $message_type = 'success';
                }
            }
        }
        
    } elseif ($action == 'clear') {
        // Clear salary allocations for the month
        $sql = "DELETE FROM expenses 
                WHERE ref_number = 'MONTHLY-SALARY' 
                AND MONTH(expense_date) = $month 
                AND YEAR(expense_date) = $year";
        
        $deleted = query($sql);
        $message = "Cleared salary allocations for " . date('F Y', strtotime("$year-$month-01"));
        $message_type = 'warning';
    }
}

// Get monthly summary using new expenses system
function getMonthlySalarySummary($month, $year) {
    $summary = [
        'month' => $month,
        'year' => $year,
        'total_monthly_salary' => 0,
        'allocation_method' => 'none',
        'allocated' => false
    ];
    
    // Check if any monthly salary expenses exist for this month
    $salary_expenses = fetchOne("
        SELECT SUM(amount) as total 
        FROM expenses 
        WHERE ref_number = 'MONTHLY-SALARY' 
        AND MONTH(expense_date) = $month 
        AND YEAR(expense_date) = $year
    ");
    
    if ($salary_expenses && $salary_expenses['total'] > 0) {
        $summary['total_monthly_salary'] = $salary_expenses['total'];
        $summary['allocated'] = true;
    }
    
    return $summary;
}

// Get projects for the month with allocated salary from new system
$projects = fetchAll("
    SELECT p.*, r.rig_name,
           COALESCE((
               SELECT SUM(e.amount) 
               FROM expenses e 
               WHERE e.project_id = p.id 
               AND e.ref_number = 'MONTHLY-SALARY'
               AND MONTH(e.expense_date) = $current_month 
               AND YEAR(e.expense_date) = $current_year
           ), 0) as allocated_salary
    FROM projects p
    LEFT JOIN rigs r ON p.rig_id = r.id
    WHERE MONTH(p.completion_date) = $current_month 
    AND YEAR(p.completion_date) = $current_year
    AND p.status = 'completed'
    ORDER BY p.completion_date DESC
");

$totalRevenue = array_sum(array_column($projects, 'payment_received'));
$totalAllocated = array_sum(array_column($projects, 'allocated_salary'));
$projectCount = count($projects);

// Get summary
$summary = getMonthlySalarySummary($current_month, $current_year);
?>

<!-- REST OF THE HTML/PHP FILE REMAINS EXACTLY THE SAME AS YOUR ORIGINAL -->
<!-- Just copy everything from <main class="container-fluid mt-4"> to the end -->

<main class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-calculator me-2"></i>Monthly Salary Allocation
                            </h4>
                            <p class="text-muted mb-0">Manage team salaries across all projects for a month</p>
                        </div>
                        <div>
                            <form method="GET" class="d-flex gap-2">
                                <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (isset($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Month Summary -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-primary shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Selected Month</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-calendar3 fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Projects</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo $projectCount; ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-clipboard-check fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-info shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Total Revenue</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo formatCurrency($totalRevenue); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-cash-stack fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-warning shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                Allocated Salaries</div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                <?php echo formatCurrency($totalAllocated); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="bi bi-people-fill fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Allocation Form -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Allocate Monthly Salaries</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="allocate">
                                <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                                <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="total_salary" class="form-label fw-bold">
                                            <i class="bi bi-currency-dollar text-primary me-1"></i>Total Monthly Salary (Ksh) *
                                        </label>
                                        <input type="number" class="form-control" id="total_salary" 
                                               name="total_salary" required step="0.01" min="0"
                                               value="<?php echo $summary['total_monthly_salary']; ?>">
                                        <div class="form-text">Total salary amount to be distributed across all projects</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="allocation_method" class="form-label fw-bold">
                                            <i class="bi bi-diagram-3 text-primary me-1"></i>Allocation Method *
                                        </label>
                                        <select class="form-select" id="allocation_method" name="allocation_method" required>
                                            <option value="equal" <?php echo $summary['allocation_method'] == 'equal' ? 'selected' : ''; ?>>
                                                Equal Distribution (Split equally among projects)
                                            </option>
                                            <option value="revenue" <?php echo $summary['allocation_method'] == 'revenue' ? 'selected' : ''; ?>>
                                                Revenue-Based (Distribute based on project revenue)
                                            </option>
                                        </select>
                                        <div class="form-text">How to distribute salaries among projects</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="bi bi-check-circle me-2"></i>Allocate Salaries
                                            </button>
                                            
                                            <button type="button" class="btn btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#clearModal">
                                                <i class="bi bi-x-circle me-2"></i>Clear Allocations
                                            </button>
                                            
                                            <a href="reports/monthly_summary.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                                               class="btn btn-outline-secondary ms-auto">
                                                <i class="bi bi-arrow-right-circle me-2"></i>View Monthly Report
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Projects Preview -->
                    <?php if ($projectCount > 0): ?>
                        <div class="card">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Projects for <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr class="small">
                                                <th>Project Code</th>
                                                <th>Project Name</th>
                                                <th>Rig</th>
                                                <th class="text-end">Revenue</th>
                                                <th class="text-end">Allocated Salary</th>
                                                <th>Salary Source</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projects as $project): ?>
                                              <tr class="small">
    <td>
        <span class="badge bg-secondary"><?php echo $project['project_code']; ?></span>
    </td>
    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
    <td><?php echo $project['rig_name']; ?></td>
    <td class="text-end"><?php echo formatCurrency($project['payment_received']); ?></td>
    <td class="text-end">
        <span class="fw-bold <?php echo $project['allocated_salary'] > 0 ? 'text-success' : 'text-muted'; ?>">
            <?php echo formatCurrency($project['allocated_salary']); ?>
        </span>
    </td>
    <td>
        <span class="badge bg-<?php echo $project['allocated_salary'] > 0 ? 'success' : 'secondary'; ?>">
            <?php echo $project['allocated_salary'] > 0 ? 'Allocated' : 'Not Allocated'; ?>
        </span>
    </td>
</tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No completed projects found for <?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?>.
                            <a href="../projects/add_project.php" class="alert-link">Add a project</a> first.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Clear Allocations Modal -->
<div class="modal fade" id="clearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Clear Salary Allocations
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to clear all salary allocations for <strong><?php echo date('F Y', strtotime("$current_year-$current_month-01")); ?></strong>?</p>
                <p class="text-danger small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    This will set all project salaries to 0 and cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                    <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                    <button type="submit" class="btn btn-danger">Clear Allocations</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calculate allocation preview
    document.getElementById('allocation_method').addEventListener('change', updatePreview);
    document.getElementById('total_salary').addEventListener('input', updatePreview);
    
    function updatePreview() {
        const totalSalary = parseFloat(document.getElementById('total_salary').value) || 0;
        const method = document.getElementById('allocation_method').value;
        const projectCount = <?php echo $projectCount; ?>;
        
        if (projectCount > 0) {
            if (method === 'equal') {
                const perProject = totalSalary / projectCount;
                console.log('Each project will get:', perProject.toFixed(2));
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>