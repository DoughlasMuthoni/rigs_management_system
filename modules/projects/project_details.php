<?php
require_once '../../includes/header.php';

// Check if project ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_projects.php');
    exit();
}

$project_id = intval($_GET['id']);

// Get project details with rig info
$project = fetchOne("SELECT p.*, r.rig_name, r.rig_code 
                     FROM projects p 
                     LEFT JOIN rigs r ON p.rig_id = r.id 
                     WHERE p.id = $project_id");

if (!$project) {
    header('Location: view_projects.php');
    exit();
}

// Get fixed expenses
$expenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = $project_id");

// Get consumables
$consumables = fetchAll("SELECT * FROM consumables WHERE project_id = $project_id ORDER BY id");

// Get miscellaneous
$miscellaneous = fetchAll("SELECT * FROM miscellaneous WHERE project_id = $project_id ORDER BY id");

// Calculate totals
$total_expenses = getProjectExpenses($project_id);
$profit = calculateProjectProfit($project_id);
$profit_margin = $project['payment_received'] > 0 ? ($profit / $project['payment_received']) * 100 : 0;

// Get next and previous projects for navigation
$prev_project = fetchOne("SELECT id, project_code FROM projects WHERE id < $project_id ORDER BY id DESC LIMIT 1");
$next_project = fetchOne("SELECT id, project_code FROM projects WHERE id > $project_id ORDER BY id ASC LIMIT 1");
?>

<main class="main-content">
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
                                <?php echo formatCurrency($total_expenses['total']); ?>
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
        <!-- Left Column - Project Info & Fixed Expenses -->
        <div class="col-lg-8">
            <!-- Project Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-info-circle me-2"></i>Project Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Project Code</label>
                            <p class="fs-5"><?php echo $project['project_code']; ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Project Name</label>
                            <p class="fs-5"><?php echo htmlspecialchars($project['project_name']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Client Name</label>
                            <p class="fs-5"><?php echo htmlspecialchars($project['client_name']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Assigned Rig</label>
                            <p class="fs-5">
                                <?php echo $project['rig_name']; ?> (<?php echo $project['rig_code']; ?>)
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Contract Amount</label>
                            <p class="fs-5 text-primary"><?php echo formatCurrency($project['contract_amount']); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Payment Received</label>
                            <p class="fs-5 text-success"><?php echo formatCurrency($project['payment_received']); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Status</label>
                            <p>
                                <span class="badge bg-<?php echo $project['status'] == 'paid' ? 'success' : ($project['status'] == 'completed' ? 'warning' : 'secondary'); ?>">
                                    <?php echo ucfirst($project['status']); ?>
                                </span>
                            </p>
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
            
            <!-- Fixed Expenses Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-receipt me-2"></i>Fixed Expenses Breakdown
                    </h6>
                    <span class="badge bg-primary">Total: <?php echo formatCurrency($expenses ? 
                        ($expenses['salaries'] + $expenses['fuel_rig'] + $expenses['fuel_truck'] + $expenses['fuel_pump'] + $expenses['fuel_hired'] + 
                         $expenses['casing_surface'] + $expenses['casing_screened'] + $expenses['casing_plain']) : 0); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-people text-success fs-4 me-2"></i>
                                    <span class="fw-bold">Team Salaries</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['salaries'] : 0); ?></div>
                                    <small class="text-muted">Personnel cost</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-fuel-pump text-warning fs-4 me-2"></i>
                                    <span class="fw-bold">Rig Fuel</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['fuel_rig'] : 0); ?></div>
                                    <small class="text-muted">Drilling rig fuel</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-truck text-warning fs-4 me-2"></i>
                                    <span class="fw-bold">Support Truck Fuel</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['fuel_truck'] : 0); ?></div>
                                    <small class="text-muted">Support vehicle fuel</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-droplet text-warning fs-4 me-2"></i>
                                    <span class="fw-bold">Test Pumping Fuel</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['fuel_pump'] : 0); ?></div>
                                    <small class="text-muted">Pumping equipment fuel</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-car-front text-warning fs-4 me-2"></i>
                                    <span class="fw-bold">Hired Vehicle Fuel</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['fuel_hired'] : 0); ?></div>
                                    <small class="text-muted">Rental vehicle fuel</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-pipe text-info fs-4 me-2"></i>
                                    <span class="fw-bold">Surface Casings</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['casing_surface'] : 0); ?></div>
                                    <small class="text-muted">Surface casing materials</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-grid-3x3 text-info fs-4 me-2"></i>
                                    <span class="fw-bold">Screened Casings</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['casing_screened'] : 0); ?></div>
                                    <small class="text-muted">Screened casing materials</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                <div>
                                    <i class="bi bi-circle-fill text-info fs-4 me-2"></i>
                                    <span class="fw-bold">Plain Casings</span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($expenses ? $expenses['casing_plain'] : 0); ?></div>
                                    <small class="text-muted">Plain casing materials</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Consumables & Miscellaneous -->
        <div class="col-lg-4">
            <!-- Consumables Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-tools me-2"></i>Consumables
                    </h6>
                    <span class="badge bg-success">
                        <?php 
                        $consumables_total = array_sum(array_column($consumables, 'amount'));
                        echo formatCurrency($consumables_total);
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (count($consumables) == 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-tools display-4 text-muted mb-3"></i>
                            <p class="text-muted">No consumables recorded</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($consumables as $item): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <div>
                                    <i class="bi bi-circle-fill text-success me-2" style="font-size: 8px;"></i>
                                    <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                </div>
                                <span class="fw-bold"><?php echo formatCurrency($item['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Miscellaneous Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-secondary">
                        <i class="bi bi-cart-plus me-2"></i>Miscellaneous Expenses
                    </h6>
                    <span class="badge bg-secondary">
                        <?php 
                        $misc_total = array_sum(array_column($miscellaneous, 'amount'));
                        echo formatCurrency($misc_total);
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (count($miscellaneous) == 0): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-cart display-4 text-muted mb-3"></i>
                            <p class="text-muted">No miscellaneous expenses</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($miscellaneous as $item): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <div>
                                    <i class="bi bi-circle-fill text-secondary me-2" style="font-size: 8px;"></i>
                                    <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                </div>
                                <span class="fw-bold"><?php echo formatCurrency($item['amount']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Expense Summary Card -->
            <div class="card shadow border-primary">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-pie-chart me-2"></i>Expense Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Fixed Expenses</span>
                            <span class="fw-bold"><?php echo formatCurrency($total_expenses['fixed']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" 
                                 style="width: <?php echo $total_expenses['total'] > 0 ? ($total_expenses['fixed'] / $total_expenses['total'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Consumables</span>
                            <span class="fw-bold"><?php echo formatCurrency($total_expenses['consumables']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo $total_expenses['total'] > 0 ? ($total_expenses['consumables'] / $total_expenses['total'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Miscellaneous</span>
                            <span class="fw-bold"><?php echo formatCurrency($total_expenses['miscellaneous']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-secondary" 
                                 style="width: <?php echo $total_expenses['total'] > 0 ? ($total_expenses['miscellaneous'] / $total_expenses['total'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Total Expenses</span>
                            <span class="fw-bold text-danger"><?php echo formatCurrency($total_expenses['total']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="fw-bold">Revenue</span>
                            <span class="fw-bold text-success"><?php echo formatCurrency($project['payment_received']); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                            <span class="fw-bold">Net Profit</span>
                            <span class="fw-bold text-<?php echo $profit >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo formatCurrency($profit); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="card shadow mt-4">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit_project.php?id=<?php echo $project_id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i>Edit Project
                        </a>
                        <a href="view_projects.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i>View All Projects
                        </a>
                        <button type="button" class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Report
                        </button>
                        <a href="../../index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    /* Custom styles for project details */
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
    
    @media print {
        .btn, .card-header .badge {
            display: none !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>