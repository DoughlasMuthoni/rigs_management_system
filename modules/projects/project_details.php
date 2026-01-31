<?php
// project_details.php (updated for new system)
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

// Check if project ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_projects.php');
    exit();
}

$project_id = intval($_GET['id']);

// Get project details with customer and rig info
$project = fetchOne("SELECT p.*, r.rig_name, r.rig_code,
                            c.first_name, c.last_name, c.company_name, c.phone, c.email
                     FROM projects p 
                     LEFT JOIN rigs r ON p.rig_id = r.id 
                     LEFT JOIN customers c ON p.customer_id = c.id
                     WHERE p.id = $project_id");

if (!$project) {
    header('Location: view_projects.php');
    exit();
}

// FIX: Initialize $expenses variable with default values
$expenses = [
    'salaries' => 0,
    'salary_source' => 'project', // default
    'total_salary' => 0,
    'other_expenses' => 0
];

// Get salary information from the new expenses system
$salary_expenses = fetchOne("SELECT SUM(amount) as total_salary 
                             FROM expenses 
                             WHERE project_id = $project_id 
                             AND ref_number = 'MONTHLY-SALARY'");

// Also check for individual salary expenses
$project_salaries = fetchOne("SELECT SUM(amount) as project_salary 
                              FROM expenses 
                              WHERE project_id = $project_id 
                              AND ref_number != 'MONTHLY-SALARY'
                              AND expense_type_id IN (SELECT id FROM expense_types WHERE category = 'Personnel')");

// Combine both types of salary expenses
$total_salary = 0;
if ($salary_expenses && $salary_expenses['total_salary'] > 0) {
    $total_salary += $salary_expenses['total_salary'];
    $expenses['salary_source'] = 'monthly';
}
if ($project_salaries && $project_salaries['project_salary'] > 0) {
    $total_salary += $project_salaries['project_salary'];
    if ($expenses['salary_source'] == 'monthly') {
        $expenses['salary_source'] = 'mixed';
    } else {
        $expenses['salary_source'] = 'project';
    }
}

$expenses['salaries'] = $total_salary;
$expenses['total_salary'] = $total_salary;

// Get expenses from ALL systems (old and new)
$complete_expenses = getCompleteProjectExpenses($project_id);
$total_expenses = $complete_expenses['total'];
$profit = calculateProjectProfit($project_id);
$profit_margin = $project['payment_received'] > 0 ? ($profit / $project['payment_received']) * 100 : 0;

// Get next and previous projects for navigation
$prev_project = fetchOne("SELECT id, project_code FROM projects WHERE id < $project_id ORDER BY id DESC LIMIT 1");
$next_project = fetchOne("SELECT id, project_code FROM projects WHERE id > $project_id ORDER BY id ASC LIMIT 1");

// Get expense breakdown by category
$expense_breakdown = getExpenseBreakdownByCategory($project_id);
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Project Details</h1>
                <p class="text-muted mb-0">Complete financial breakdown for <?php echo $project['project_code']; ?></p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($prev_project): ?>
                    <a href="project_details.php?id=<?php echo $prev_project['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                <?php if ($next_project): ?>
                    <a href="project_details.php?id=<?php echo $next_project['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
                <a href="view_projects.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list me-1"></i> All Projects
                </a>
            </div>
        </div>
    </div>
    
    <!-- Project Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Revenue
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo formatCurrency($project['payment_received']); ?>
                            </div>
                        </div>
                        <div class="text-primary opacity-50">
                            <i class="bi bi-cash-coin display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Expenses
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo formatCurrency($total_expenses); ?>
                            </div>
                        </div>
                        <div class="text-warning opacity-50">
                            <i class="bi bi-currency-dollar display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?php echo $profit >= 0 ? 'success' : 'danger'; ?> shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-<?php echo $profit >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Profit
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo formatCurrency($profit); ?>
                            </div>
                        </div>
                        <div class="text-<?php echo $profit >= 0 ? 'success' : 'danger'; ?> opacity-50">
                            <i class="bi bi-graph-up-arrow display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                Profit Margin
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo number_format($profit_margin, 2); ?>%
                            </div>
                        </div>
                        <div class="text-info opacity-50">
                            <i class="bi bi-percent display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Project Info & Expenses -->
        <div class="col-lg-8">
            <!-- Project Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-info-circle me-2"></i>Project Information
                    </h6>
                    <span class="badge bg-<?php echo $project['status'] == 'completed' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($project['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Project Code</label>
                            <p class="fs-5"><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Project Name</label>
                            <p class="fs-5"><?php echo htmlspecialchars($project['project_name']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Customer</label>
                            <p class="fs-5">
                                <?php 
                                // Fixed customer name display
                                if (!empty($project['first_name']) || !empty($project['last_name'])) {
                                    $customer_name = trim($project['first_name'] . ' ' . $project['last_name']);
                                    echo htmlspecialchars($customer_name);
                                    if (!empty($project['company_name'])) {
                                        echo ' (' . htmlspecialchars($project['company_name']) . ')';
                                    }
                                } else {
                                    echo '<span class="text-muted">No customer assigned</span>';
                                }
                                ?>
                            </p>
                            <?php if ($project['phone']): ?>
                                <p class="small text-muted mb-0">
                                    <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($project['phone']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($project['email']): ?>
                                <p class="small text-muted mb-0">
                                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($project['email']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Drilling-specific sections -->
                        <?php if ($project['project_type'] == 'Drilling'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Assigned Rig</label>
                            <p class="fs-5">
                                <?php if (!empty($project['rig_name'])): ?>
                                    <?php echo $project['rig_name']; ?> (<?php echo $project['rig_code']; ?>)
                                <?php else: ?>
                                    <span class="text-muted">No rig assigned</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Project Type</label>
                            <p><span class="badge bg-info"><?php echo $project['project_type']; ?></span></p>
                        </div>
                        
                        <?php if ($project['project_type'] == 'Drilling'): ?>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Depth</label>
                            <p class="fs-5"><?php echo $project['depth']; ?> meters</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Salary Source</label>
                            <p>
                                <?php if (isset($expenses['salary_source'])): ?>
                                    <span class="badge bg-<?php echo $expenses['salary_source'] == 'monthly' ? 'success' : ($expenses['salary_source'] == 'mixed' ? 'warning' : 'primary'); ?>">
                                        <?php echo ucfirst($expenses['salary_source']); ?> Allocation
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Project Allocation</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Contract Amount</label>
                            <p class="fs-5 text-primary"><?php echo formatCurrency($project['contract_amount']); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Estimated Cost</label>
                            <p class="fs-5"><?php echo formatCurrency($project['estimate_cost']); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Payment Received</label>
                            <p class="fs-5 text-success"><?php echo formatCurrency($project['payment_received']); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Start Date</label>
                            <p><?php echo $project['start_date'] ? date('M d, Y', strtotime($project['start_date'])) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Completion Date</label>
                            <p><?php echo date('M d, Y', strtotime($project['completion_date'])); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Payment Date</label>
                            <p><?php echo $project['payment_date'] ? date('M d, Y', strtotime($project['payment_date'])) : 'N/A'; ?></p>
                        </div>
                        
                        <?php if (!empty($project['notes'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($project['notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Salary Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-people-fill me-2"></i>Salary Information
                    </h6>
                    <span class="badge bg-primary">
                        <?php echo formatCurrency($expenses['salaries']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-people text-success fs-4 me-2"></i>
                                    <span class="fw-bold">Team Salaries</span>
                                    <?php if (isset($expenses['salary_source'])): ?>
                                        <span class="badge bg-<?php echo $expenses['salary_source'] == 'monthly' ? 'success' : ($expenses['salary_source'] == 'mixed' ? 'warning' : 'primary'); ?> ms-2">
                                            <?php echo ucfirst($expenses['salary_source']); ?> allocation
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses['salaries']); ?></div>
                                    <small class="text-muted">Personnel cost</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- FIX: Added detail breakdown of salary types -->
                        <?php if ($salary_expenses && $salary_expenses['total_salary'] > 0): ?>
                        <div class="col-md-6 mb-2">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Monthly Allocation</small>
                                <div class="fw-bold text-success"><?php echo formatCurrency($salary_expenses['total_salary']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project_salaries && $project_salaries['project_salary'] > 0): ?>
                        <div class="col-md-6 mb-2">
                            <div class="border rounded p-2">
                                <small class="text-muted d-block">Project-Specific</small>
                                <div class="fw-bold text-primary"><?php echo formatCurrency($project_salaries['project_salary']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-12 mt-3">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note:</strong> All other expenses (fuel, materials, consumables, etc.) 
                                are tracked separately in the Expenses module.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Project Expenses Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-warning">
                        <i class="bi bi-receipt me-2"></i>Project Expenses
                    </h6>
                    <a href="../expenses/manage_expenses.php?project_id=<?php echo $project_id; ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-plus-circle me-1"></i>Add Expense
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($expense_breakdown)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-receipt display-4 text-muted mb-3"></i>
                            <p class="text-muted">No expenses recorded yet</p>
                            <a href="../expenses/manage_expenses.php?action=add&project_id=<?php echo $project_id; ?>" class="btn btn-warning">
                                <i class="bi bi-plus-circle me-1"></i>Add First Expense
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Expense Breakdown by Category -->
                        <?php foreach ($expense_breakdown as $category => $data): 
                            $category_percentage = $total_expenses > 0 ? ($data['total'] / $total_expenses) * 100 : 0;
                        ?>
                        <div class="mb-4">
                            <h6 class="d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <span class="badge bg-warning"><?php echo formatCurrency($data['total']); ?></span>
                            </h6>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar" 
                                     style="width: <?php echo $category_percentage; ?>%">
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <?php foreach ($data['items'] as $item): 
                                    $item_percentage = $data['total'] > 0 ? ($item['amount'] / $data['total']) * 100 : 0;
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                    <div>
                                        <span class="small"><?php echo htmlspecialchars($item['expense_name']); ?></span>
                                        <?php if ($item['supplier_name']): ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <i class="bi bi-truck"></i> <?php echo htmlspecialchars($item['supplier_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?php echo formatCurrency($item['amount']); ?></div>
                                        <small class="text-muted"><?php echo number_format($item_percentage, 1); ?>% of category</small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Summary & Actions -->
        <div class="col-lg-4">
            <!-- Expense Summary Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-pie-chart me-2"></i>Financial Summary
                    </h6>
                    <span class="badge bg-<?php echo $profit >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo number_format($profit_margin, 1); ?>%
                    </span>
                </div>
                <div class="card-body">
                    <!-- Revenue vs Expenses -->
                    <div class="mb-4">
                        <h6 class="mb-3">Revenue vs Expenses</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Revenue</span>
                            <span class="fw-bold text-success"><?php echo formatCurrency($project['payment_received']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Expenses</span>
                            <span class="fw-bold text-warning"><?php echo formatCurrency($total_expenses); ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $project['payment_received'] > 0 ? ($total_expenses / $project['payment_received'] * 100) : 0; ?>%">
                                Expenses
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profit Breakdown -->
                    <div class="mb-4">
                        <h6 class="mb-3">Profit Breakdown</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Contract Amount</span>
                            <span class="fw-bold"><?php echo formatCurrency($project['contract_amount']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Payment Received</span>
                            <span class="fw-bold text-success"><?php echo formatCurrency($project['payment_received']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Total Expenses</span>
                            <span class="fw-bold text-warning"><?php echo formatCurrency($total_expenses); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Net Profit</span>
                            <span class="fw-bold text-<?php echo $profit >= 0 ? 'success' : 'danger'; ?> fs-5">
                                <?php echo formatCurrency($profit); ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Cost Efficiency - Only show for Drilling projects -->
                    <?php if ($project['project_type'] == 'Drilling'): ?>
                    <div class="mb-4">
                        <h6 class="mb-3">Drilling Efficiency Metrics</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="p-3 border rounded">
                                    <div class="text-xs fw-bold text-muted">Cost per Meter</div>
                                    <div class="h6 fw-bold">
                                        <?php 
                                        if ($project['depth'] > 0 && $total_expenses > 0) {
                                            echo formatCurrency($total_expenses / $project['depth']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 border rounded">
                                    <div class="text-xs fw-bold text-muted">Revenue per Meter</div>
                                    <div class="h6 fw-bold text-success">
                                        <?php 
                                        if ($project['depth'] > 0 && $project['payment_received'] > 0) {
                                            echo formatCurrency($project['payment_received'] / $project['depth']);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i>Edit Project Details
                        </a>
                        <a href="../expenses/manage_expenses.php?action=add&project_id=<?php echo $project_id; ?>" class="btn btn-success">
                            <i class="bi bi-plus-circle me-2"></i>Add New Expense
                        </a>
                        <a href="../expenses/manage_expenses.php?project_id=<?php echo $project_id; ?>" class="btn btn-outline-warning">
                            <i class="bi bi-receipt me-2"></i>View All Expenses
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Report
                        </button>
                        <a href="view_projects.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i>View All Projects
                        </a>
                        <a href="../../index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Customer Quick Info -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-info">
                        <i class="bi bi-person me-2"></i>Customer Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="avatar-circle bg-info text-white mb-3 mx-auto">
                            <?php 
                            // Fixed customer name display for avatar
                            if (!empty($project['first_name']) || !empty($project['last_name'])) {
                                $customer_name = trim($project['first_name'] . ' ' . $project['last_name']);
                                echo strtoupper(substr($customer_name, 0, 1)); 
                            } elseif (!empty($project['company_name'])) {
                                echo strtoupper(substr($project['company_name'], 0, 1));
                            } else {
                                echo '?';
                            }
                            ?>
                        </div>
                        <h5>
                            <?php 
                            // Fixed customer name display for heading
                            if (!empty($project['first_name']) || !empty($project['last_name'])) {
                                $customer_name = trim($project['first_name'] . ' ' . $project['last_name']);
                                echo htmlspecialchars($customer_name);
                            } elseif (!empty($project['company_name'])) {
                                echo htmlspecialchars($project['company_name']);
                            } else {
                                echo 'No customer assigned';
                            }
                            ?>
                        </h5>
                        <?php if (!empty($project['company_name']) && (!empty($project['first_name']) || !empty($project['last_name']))): ?>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($project['company_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <?php if ($project['phone']): ?>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="bi bi-telephone text-info me-2"></i>
                            <span><?php echo htmlspecialchars($project['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($project['email']): ?>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="bi bi-envelope text-info me-2"></i>
                            <span><?php echo htmlspecialchars($project['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($project['customer_id']) && $project['customer_id'] > 0): ?>
                        <div class="list-group-item d-flex justify-content-between">
                            <span>Projects with this customer:</span>
                            <?php 
                            $customer_projects = getCustomerProjects($project['customer_id']);
                            $project_count = count($customer_projects);
                            ?>
                            <span class="badge bg-info"><?php echo $project_count; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($project['customer_id']) && $project['customer_id'] > 0): ?>
                        <div class="list-group-item text-center pt-3">
                            <a href="../customer/manage_customers.php?action=view&id=<?php echo $project['customer_id']; ?>" 
                               class="btn btn-sm btn-outline-info w-100">
                                <i class="bi bi-eye me-1"></i>View Customer Profile
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-danger { border-left: 4px solid #e74a3b !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    
    .card {
        border: 1px solid #e3e6f0;
        border-radius: 10px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0,0,0,.125);
        border-radius: 10px 10px 0 0 !important;
    }
    
    .list-group-item {
        border-left: none;
        border-right: none;
    }
    
    .list-group-item:first-child {
        border-top: none;
    }
    
    .list-group-item:last-child {
        border-bottom: none;
    }
    
    .avatar-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
    }
    
    @media print {
        .btn, .card-header .badge {
            display: none !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
        
        .action-buttons {
            display: none !important;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add any JavaScript functionality here if needed
});
</script>

<?php 
require_once '../../includes/footer.php'; 
?>