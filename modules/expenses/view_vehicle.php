<?php
// view_vehicle.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if vehicle ID is provided
if (!isset($_GET['id'])) {
    header('Location: manage_vehicles.php');
    exit();
}

$vehicle_id = intval($_GET['id']);

// Get vehicle details
$vehicle = fetchOne("SELECT * FROM vehicles WHERE id = $vehicle_id");

if (!$vehicle) {
    header('Location: manage_vehicles.php');
    exit();
}

// Now include header AFTER checking and fetching data
require_once ROOT_PATH . '/includes/header.php';

// Get vehicle statistics
$expense_stats = fetchOne("SELECT 
    COUNT(*) as total_expenses,
    COALESCE(SUM(amount), 0) as total_amount,
    MIN(expense_date) as first_expense,
    MAX(expense_date) as last_expense
    FROM expenses 
    WHERE vehicle_id = $vehicle_id 
    AND status != 'cancelled'");

// Get recent expenses
$recent_expenses = fetchAll("SELECT 
    e.*, 
    p.project_code, 
    p.project_name,
    et.expense_name,
    et.category
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    JOIN expense_types et ON e.expense_type_id = et.id
    WHERE e.vehicle_id = $vehicle_id
    ORDER BY e.expense_date DESC 
    LIMIT 10");

// Get next and previous vehicles
$prev_vehicle = fetchOne("SELECT id, vehicle_no FROM vehicles WHERE id < $vehicle_id ORDER BY id DESC LIMIT 1");
$next_vehicle = fetchOne("SELECT id, vehicle_no FROM vehicles WHERE id > $vehicle_id ORDER BY id ASC LIMIT 1");
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Vehicle Details</h1>
                <p class="text-muted mb-0">Complete information for <?php echo htmlspecialchars($vehicle['vehicle_no']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($prev_vehicle): ?>
                    <a href="view_vehicle.php?id=<?php echo $prev_vehicle['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <a href="manage_vehicles.php?action=edit&id=<?php echo $vehicle_id; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                
                <?php if ($next_vehicle): ?>
                    <a href="view_vehicle.php?id=<?php echo $next_vehicle['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
                
                <a href="manage_vehicles.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-list me-1"></i> All Vehicles
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Vehicle Info & Expenses -->
        <div class="col-lg-8">
            <!-- Vehicle Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-truck me-2"></i>Vehicle Information
                    </h6>
                    <span class="badge bg-<?php echo $vehicle['status'] == 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($vehicle['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Vehicle Number</label>
                            <p class="fs-5"><?php echo htmlspecialchars($vehicle['vehicle_no']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Vehicle Type</label>
                            <p class="fs-5">
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($vehicle['vehicle_type']); ?>
                                </span>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Make</label>
                            <p class="fs-5"><?php echo !empty($vehicle['make']) ? htmlspecialchars($vehicle['make']) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Model</label>
                            <p class="fs-5"><?php echo !empty($vehicle['model']) ? htmlspecialchars($vehicle['model']) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Year</label>
                            <p class="fs-5"><?php echo !empty($vehicle['year']) ? $vehicle['year'] : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Assigned To</label>
                            <p class="fs-5"><?php echo !empty($vehicle['assigned_to']) ? htmlspecialchars($vehicle['assigned_to']) : 'Not assigned'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Created Date</label>
                            <p><?php echo date('M d, Y', strtotime($vehicle['created_at'])); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Last Updated</label>
                            <p><?php echo !empty($vehicle['updated_at']) ? date('M d, Y', strtotime($vehicle['updated_at'])) : 'Never'; ?></p>
                        </div>
                        
                        <?php if (!empty($vehicle['notes'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($vehicle['notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Expenses Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-warning">
                        <i class="bi bi-receipt me-2"></i>Recent Expenses
                    </h6>
                    <a href="../expenses/manage_expenses.php?vehicle_id=<?php echo $vehicle_id; ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-eye me-1"></i>View All
                    </a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_expenses)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Expense Code</th>
                                        <th>Project</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_expenses as $exp): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $exp['expense_code']; ?></span></td>
                                        <td>
                                            <a href="../projects/project_details.php?id=<?php echo $exp['project_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($exp['project_code']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info" title="<?php echo htmlspecialchars($exp['category']); ?>">
                                                <?php echo htmlspecialchars($exp['expense_name']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo !empty($exp['ref_number']) ? htmlspecialchars($exp['ref_number']) : 'N/A'; ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($exp['amount']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $exp['status'] == 'paid' ? 'success' : 
                                                     ($exp['status'] == 'approved' ? 'primary' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($exp['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../expenses/view_expense.php?id=<?php echo $exp['id']; ?>" class="btn btn-outline-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-receipt display-4 text-muted mb-3"></i>
                            <p class="text-muted">No expenses recorded for this vehicle</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Statistics & Actions -->
        <div class="col-lg-4">
            <!-- Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-graph-up me-2"></i>Vehicle Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Expenses:</span>
                        <span class="fw-bold"><?php echo $expense_stats['total_expenses']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Amount:</span>
                        <span class="fw-bold text-primary"><?php echo formatCurrency($expense_stats['total_amount']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>First Expense:</span>
                        <span><?php echo $expense_stats['first_expense'] ? date('M d, Y', strtotime($expense_stats['first_expense'])) : 'N/A'; ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Last Expense:</span>
                        <span><?php echo $expense_stats['last_expense'] ? date('M d, Y', strtotime($expense_stats['last_expense'])) : 'N/A'; ?></span>
                    </div>
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
                        <a href="manage_vehicles.php?action=edit&id=<?php echo $vehicle_id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i>Edit Vehicle
                        </a>
                        
                        <a href="../expenses/manage_expenses.php?action=add&vehicle_id=<?php echo $vehicle_id; ?>" class="btn btn-success">
                            <i class="bi bi-plus-circle me-2"></i>Add New Expense
                        </a>
                        
                        <a href="../expenses/manage_expenses.php?vehicle_id=<?php echo $vehicle_id; ?>" class="btn btn-outline-warning">
                            <i class="bi bi-receipt me-2"></i>View All Expenses
                        </a>
                        
                        <button type="button" class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Details
                        </button>
                        
                        <a href="manage_vehicles.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i>View All Vehicles
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>