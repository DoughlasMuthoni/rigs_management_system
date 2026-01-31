<?php
// view_expense.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if expense ID is provided
if (!isset($_GET['id'])) {
    header('Location: manage_expenses.php');
    exit();
}

$expense_id = intval($_GET['id']);

// Get expense details with all related information
$sql = "SELECT e.*, 
               p.project_code, p.project_name, p.status as project_status,
               c.first_name, c.last_name, c.company_name, c.phone as customer_phone,
               et.expense_name, et.category,
               s.supplier_name, s.contact_person, s.phone as supplier_phone,
               v.vehicle_no, v.vehicle_type, v.make, v.model, v.year,
               u.full_name as created_by_name
        FROM expenses e
        LEFT JOIN projects p ON e.project_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN expense_types et ON e.expense_type_id = et.id
        LEFT JOIN suppliers s ON e.supplier_id = s.id
        LEFT JOIN vehicles v ON e.vehicle_id = v.id
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = $expense_id";

$expense = fetchOne($sql);

if (!$expense) {
    header('Location: manage_expenses.php');
    exit();
}

// Now include header AFTER checking and fetching data
require_once ROOT_PATH . '/includes/header.php';

// Get extra expenses
$extra_expenses = fetchAll("SELECT * FROM extra_expenses WHERE expense_id = $expense_id ORDER BY id");

// Get next and previous expenses
$prev_expense = fetchOne("SELECT id, expense_code FROM expenses WHERE id < $expense_id ORDER BY id DESC LIMIT 1");
$next_expense = fetchOne("SELECT id, expense_code FROM expenses WHERE id > $expense_id ORDER BY id ASC LIMIT 1");


?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Expense Details</h1>
                <p class="text-muted mb-0">Complete details for expense: <?php echo $expense['expense_code']; ?></p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($prev_expense): ?>
                    <a href="view_expense.php?id=<?php echo $prev_expense['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <a href="manage_expenses.php?action=edit&id=<?php echo $expense_id; ?>" class="btn btn-warning btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                
                <?php if ($next_expense): ?>
                    <a href="view_expense.php?id=<?php echo $next_expense['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        Next <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
                
                <a href="manage_expenses.php" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-list me-1"></i> All Expenses
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Left Column - Expense Details -->
        <div class="col-lg-8">
            <!-- Expense Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-receipt me-2"></i>Expense Information
                    </h6>
                    <span class="badge bg-<?php 
                        echo $expense['status'] == 'paid' ? 'success' : 
                             ($expense['status'] == 'approved' ? 'primary' : 'warning'); 
                    ?>">
                        <?php echo ucfirst($expense['status']); ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Expense Code</label>
                            <p class="fs-5"><span class="badge bg-secondary"><?php echo $expense['expense_code']; ?></span></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Reference Number</label>
                            <p class="fs-5"><?php echo !empty($expense['ref_number']) ? $expense['ref_number'] : 'N/A'; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Expense Date</label>
                            <p class="fs-5"><?php echo date('M d, Y', strtotime($expense['expense_date'])); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Created By</label>
                            <p class="fs-5"><?php echo htmlspecialchars($expense['created_by_name']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Project</label>
                            <p class="fs-5">
                                <a href="../projects/project_details.php?id=<?php echo $expense['project_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($expense['project_code'] . ' - ' . $expense['project_name']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Customer</label>
                            <p class="fs-5">
                                <?php 
                                if (!empty($expense['first_name']) || !empty($expense['last_name'])) {
                                    $customer_name = trim($expense['first_name'] . ' ' . $expense['last_name']);
                                    echo htmlspecialchars($customer_name);
                                    if (!empty($expense['company_name'])) {
                                        echo ' (' . htmlspecialchars($expense['company_name']) . ')';
                                    }
                                } elseif (!empty($expense['company_name'])) {
                                    echo htmlspecialchars($expense['company_name']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Expense Type</label>
                            <p>
                                <span class="badge bg-info">
                                    <?php echo htmlspecialchars($expense['expense_name']); ?>
                                </span>
                                <span class="text-muted">(<?php echo htmlspecialchars($expense['category']); ?>)</span>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Supplier</label>
                            <p class="fs-5">
                                <?php if (!empty($expense['supplier_name'])): ?>
                                    <?php echo htmlspecialchars($expense['supplier_name']); ?>
                                    <?php if (!empty($expense['contact_person'])): ?>
                                        <br><small class="text-muted">Contact: <?php echo htmlspecialchars($expense['contact_person']); ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($expense['supplier_phone'])): ?>
                                        <br><small class="text-muted">Phone: <?php echo htmlspecialchars($expense['supplier_phone']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No supplier</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-muted">Vehicle</label>
                            <p class="fs-5">
                                <?php if (!empty($expense['vehicle_no'])): ?>
                                    <?php echo htmlspecialchars($expense['vehicle_no']); ?>
                                    (<?php echo htmlspecialchars($expense['vehicle_type']); ?>)
                                    <?php if (!empty($expense['make']) || !empty($expense['model']) || !empty($expense['year'])): ?>
                                        <br><small class="text-muted">
                                            <?php 
                                            $vehicle_details = [];
                                            if (!empty($expense['make'])) $vehicle_details[] = $expense['make'];
                                            if (!empty($expense['model'])) $vehicle_details[] = $expense['model'];
                                            if (!empty($expense['year'])) $vehicle_details[] = $expense['year'];
                                            echo htmlspecialchars(implode(' ', $vehicle_details));
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No vehicle</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Amount Details -->
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Quantity</label>
                            <p class="fs-5"><?php echo number_format($expense['quantity'], 2); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Unit Price</label>
                            <p class="fs-5"><?php echo formatCurrency($expense['unit_price']); ?></p>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-muted">Total Amount</label>
                            <p class="fs-5 text-primary fw-bold"><?php echo formatCurrency($expense['amount']); ?></p>
                        </div>
                        
                        <?php if (!empty($expense['notes'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold text-muted">Notes</label>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($expense['notes'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Extra Expenses Card -->
            <?php if (!empty($extra_expenses)): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-warning">
                        <i class="bi bi-plus-circle me-2"></i>Extra Expenses
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $extra_total = 0;
                                foreach ($extra_expenses as $extra): 
                                    $extra_total += $extra['amount'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($extra['description']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($extra['amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <th class="text-end">Total Extra Expenses:</th>
                                    <th class="text-end"><?php echo formatCurrency($extra_total); ?></th>
                                </tr>
                                <tr class="table-light">
                                    <th class="text-end">Grand Total:</th>
                                    <th class="text-end text-primary fw-bold">
                                        <?php echo formatCurrency($expense['amount'] + $extra_total); ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Receipt Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-file-earmark me-2"></i>Receipt/Invoice
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($expense['receipt_path'])): ?>
                        <div class="text-center">
                            <?php 
                            $file_ext = pathinfo($expense['receipt_path'], PATHINFO_EXTENSION);
                            if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])): 
                            ?>
                                <img src="<?php echo ROOT_PATH . $expense['receipt_path']; ?>" 
                                     alt="Receipt" 
                                     class="img-fluid rounded shadow" 
                                     style="max-height: 500px;">
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-file-earmark-pdf fs-1"></i>
                                    <p class="mt-3">PDF receipt attached</p>
                                    <a href="<?php echo ROOT_PATH . $expense['receipt_path']; ?>" 
                                       target="_blank" 
                                       class="btn btn-primary">
                                        <i class="bi bi-download me-2"></i>Download PDF
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-x display-4 text-muted mb-3"></i>
                            <p class="text-muted">No receipt attached</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Summary & Actions -->
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
                        <a href="manage_expenses.php?action=edit&id=<?php echo $expense_id; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i>Edit Expense
                        </a>
                        
                        <?php if ($expense['status'] == 'pending'): ?>
                            <button type="button" class="btn btn-success" onclick="updateExpenseStatus('approved')">
                                <i class="bi bi-check-circle me-2"></i>Approve Expense
                            </button>
                        <?php elseif ($expense['status'] == 'approved'): ?>
                            <button type="button" class="btn btn-primary" onclick="updateExpenseStatus('paid')">
                                <i class="bi bi-cash me-2"></i>Mark as Paid
                            </button>
                        <?php endif; ?>
                        
                        <a href="../projects/project_details.php?id=<?php echo $expense['project_id']; ?>" class="btn btn-outline-info">
                            <i class="bi bi-folder me-2"></i>View Project
                        </a>
                        
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Details
                        </button>
                        
                        <a href="manage_expenses.php" class="btn btn-outline-primary">
                            <i class="bi bi-list me-2"></i>View All Expenses
                        </a>
                    </div>
                </div>
            </div>
            
           
            
            <!-- Project Quick Info -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-success">
                        <i class="bi bi-briefcase me-2"></i>Project Information
                    </h6>
                </div>
                <div class="card-body">
                    <h6 class="mb-2">
                        <a href="../projects/project_details.php?id=<?php echo $expense['project_id']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($expense['project_code']); ?>
                        </a>
                    </h6>
                    <p class="mb-2"><?php echo htmlspecialchars($expense['project_name']); ?></p>
                    
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Status:</span>
                        <span class="badge bg-<?php echo $expense['project_status'] == 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($expense['project_status']); ?>
                        </span>
                    </div>
                    
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Customer:</span>
                        <span>
                            <?php 
                            if (!empty($expense['first_name']) || !empty($expense['last_name'])) {
                                echo htmlspecialchars(substr(trim($expense['first_name'] . ' ' . $expense['last_name']), 0, 20));
                            } elseif (!empty($expense['company_name'])) {
                                echo htmlspecialchars(substr($expense['company_name'], 0, 20));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($expense['customer_phone'])): ?>
                    <div class="d-flex justify-content-between small mb-3">
                        <span>Phone:</span>
                        <span><?php echo htmlspecialchars($expense['customer_phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-center pt-2">
                        <a href="../projects/project_details.php?id=<?php echo $expense['project_id']; ?>" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-eye me-1"></i>View Project Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
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
<script>
function updateExpenseStatus(newStatus) {
    if (confirm('Are you sure you want to update the expense status to "' + newStatus + '"?')) {
        $.ajax({
            url: 'ajax_update_expense_status.php',
            method: 'POST',
            data: {
                expense_id: <?php echo $expense_id; ?>,
                status: newStatus,
                notes: 'Status updated from expense details page'
            },
            success: function(response) {
                try {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('Expense status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (e) {
                    alert('Error parsing response: ' + response);
                }
            },
            error: function() {
                alert('Error updating expense status. Please try again.');
            }
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>