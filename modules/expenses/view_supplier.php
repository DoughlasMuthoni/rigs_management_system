<?php
// view_supplier.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if supplier ID is provided
if (!isset($_GET['id'])) {
    header('Location: manage_suppliers.php');
    exit();
}

$supplier_id = intval($_GET['id']);

// Get supplier details
$supplier = fetchOne("SELECT * FROM suppliers WHERE id = $supplier_id");

if (!$supplier) {
    header('Location: manage_suppliers.php');
    exit();
}

// Now include header AFTER checking and fetching data
require_once ROOT_PATH . '/includes/header.php';

// Get supplier statistics
$expense_stats = fetchOne("SELECT 
    COUNT(*) as total_expenses,
    COALESCE(SUM(amount), 0) as total_amount,
    MIN(expense_date) as first_expense,
    MAX(expense_date) as last_expense
    FROM expenses 
    WHERE supplier_id = $supplier_id 
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
    WHERE e.supplier_id = $supplier_id
    ORDER BY e.expense_date DESC 
    LIMIT 10");

// Get next and previous suppliers
$prev_supplier = fetchOne("SELECT id, supplier_name FROM suppliers WHERE id < $supplier_id ORDER BY id DESC LIMIT 1");
$next_supplier = fetchOne("SELECT id, supplier_name FROM suppliers WHERE id > $supplier_id ORDER BY id ASC LIMIT 1");

// Get expenses by project
$expenses_by_project = fetchAll("SELECT 
    p.id as project_id,
    p.project_code,
    p.project_name,
    COUNT(e.id) as expense_count,
    COALESCE(SUM(e.amount), 0) as total_amount
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE e.supplier_id = $supplier_id
    GROUP BY p.id, p.project_code, p.project_name
    ORDER BY total_amount DESC");
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Supplier Details</h1>
                <p class="text-muted mb-0">Complete information for <?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($prev_supplier): ?>
                    <a href="view_supplier.php?id=<?php echo $prev_supplier['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <a href="manage_suppliers.php?action=edit&id=<?php echo $supplier_id; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                
                <?php if ($next_supplier): ?>
                    <a href="view_supplier.php?id=<?php echo $next_supplier['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
                
                <a href="manage_suppliers.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-list me-1"></i> All Suppliers
                </a>
            </div>
        </div>
    </div>
    
    <!-- Supplier Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Total Expenses
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $expense_stats['total_expenses']; ?>
                            </div>
                        </div>
                        <div class="text-primary opacity-50">
                            <i class="bi bi-receipt display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Total Amount
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo formatCurrency($expense_stats['total_amount']); ?>
                            </div>
                        </div>
                        <div class="text-success opacity-50">
                            <i class="bi bi-cash-coin display-6"></i>
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
                                First Expense
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $expense_stats['first_expense'] ? date('M d, Y', strtotime($expense_stats['first_expense'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="text-info opacity-50">
                            <i class="bi bi-calendar-event display-6"></i>
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
                                Last Expense
                            </div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo $expense_stats['last_expense'] ? date('M d, Y', strtotime($expense_stats['last_expense'])) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="text-warning opacity-50">
                            <i class="bi bi-calendar-check display-6"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Supplier Info & Expenses -->
        <div class="col-lg-8">
            <!-- Supplier Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-building me-2"></i>Supplier Information
                    </h6>
                    <span class="badge bg-<?php echo $supplier['status'] == 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($supplier['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Supplier Name</label>
                            <p class="fs-5"><?php echo htmlspecialchars($supplier['supplier_name']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Contact Person</label>
                            <p class="fs-5"><?php echo !empty($supplier['contact_person']) ? htmlspecialchars($supplier['contact_person']) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Email</label>
                            <p class="fs-5">
                                <?php if (!empty($supplier['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($supplier['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($supplier['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Phone</label>
                            <p class="fs-5">
                                <?php if (!empty($supplier['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($supplier['phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($supplier['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Tax ID</label>
                            <p class="fs-5"><?php echo !empty($supplier['tax_id']) ? htmlspecialchars($supplier['tax_id']) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Created Date</label>
                            <p><?php echo date('M d, Y', strtotime($supplier['created_at'])); ?></p>
                        </div>
                        
                        <?php if (!empty($supplier['address'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-muted">Address</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($supplier['address'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($supplier['notes'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($supplier['notes'])); ?>
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
                    <a href="../expenses/manage_expenses.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-sm btn-warning">
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
                            <p class="text-muted">No expenses recorded for this supplier</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Statistics & Actions -->
        <div class="col-lg-4">
            <!-- Action Buttons -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="manage_suppliers.php?action=edit&id=<?php echo $supplier_id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i>Edit Supplier
                        </a>
                        
                        <a href="../expenses/manage_expenses.php?action=add&supplier_id=<?php echo $supplier_id; ?>" class="btn btn-success">
                            <i class="bi bi-plus-circle me-2"></i>Add New Expense
                        </a>
                        
                        <a href="../expenses/manage_expenses.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-outline-warning">
                            <i class="bi bi-receipt me-2"></i>View All Expenses
                        </a>
                        
                        <button type="button" class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Details
                        </button>
                        
                        <a href="manage_suppliers.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i>View All Suppliers
                        </a>
                        
                        <a href="../../index.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Expenses by Project -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-pie-chart me-2"></i>Expenses by Project
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($expenses_by_project)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($expenses_by_project as $project): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="../projects/project_details.php?id=<?php echo $project['project_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($project['project_code']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo $project['expense_count']; ?> expense(s)</small>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold"><?php echo formatCurrency($project['total_amount']); ?></div>
                                    <small class="text-muted">
                                        <?php 
                                        $percentage = $expense_stats['total_amount'] > 0 ? 
                                                     ($project['total_amount'] / $expense_stats['total_amount']) * 100 : 0;
                                        echo number_format($percentage, 1); ?>%
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-pie-chart display-4 text-muted mb-3"></i>
                            <p class="text-muted">No project expenses data</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Supplier Activity -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-info">
                        <i class="bi bi-clock-history me-2"></i>Supplier Activity
                    </h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if ($expense_stats['first_expense']): ?>
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">First Expense</h6>
                                <p class="small text-muted mb-1">
                                    <?php echo date('M d, Y', strtotime($expense_stats['first_expense'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($expense_stats['last_expense']): ?>
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Last Expense</h6>
                                <p class="small text-muted mb-1">
                                    <?php echo date('M d, Y', strtotime($expense_stats['last_expense'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Total Transactions</h6>
                                <p class="small text-muted mb-1">
                                    <?php echo $expense_stats['total_expenses']; ?> expenses
                                </p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Supplier Added</h6>
                                <p class="small text-muted mb-1">
                                    <?php echo date('M d, Y', strtotime($supplier['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .border-left-success { border-left: 4px solid #1cc88a !important; }
    .border-left-info { border-left: 4px solid #36b9cc !important; }
    .border-left-warning { border-left: 4px solid #f6c23e !important; }
    
    .card {
        border: 1px solid #e3e6f0;
        border-radius: 10px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }
    
    .card-header {
        border-bottom: 1px solid rgba(0,0,0,.125);
        border-radius: 10px 10px 0 0 !important;
    }
    
    .timeline {
        position: relative;
        padding-left: 20px;
    }
    
    .timeline-item {
        position: relative;
        padding-bottom: 10px;
    }
    
    .timeline-marker {
        position: absolute;
        left: -20px;
        top: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #4e73df;
    }
    
    .timeline-content {
        padding-left: 10px;
    }
    
    @media print {
        .btn, .card-header .badge, .action-buttons {
            display: none !important;
        }
        
        .card {
            box-shadow: none !important;
            border: 1px solid #ddd !important;
        }
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php require_once '../../includes/footer.php'; ?>