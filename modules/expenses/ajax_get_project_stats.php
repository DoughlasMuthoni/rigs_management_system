<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    // Not an AJAX request - show error
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit();
}

// Check if project_id is provided
if (!isset($_POST['project_id']) || empty($_POST['project_id'])) {
    echo '<div class="alert alert-warning">No project selected</div>';
    exit();
}

$project_id = intval($_POST['project_id']);

// Get project details
$project = fetchOne("SELECT 
    p.*,
    c.company_name,
    c.first_name,
    c.last_name,
    r.rig_name,
    r.rig_code
FROM projects p
LEFT JOIN customers c ON p.customer_id = c.id
LEFT JOIN rigs r ON p.rig_id = r.id
WHERE p.id = $project_id");

if (!$project) {
    echo '<div class="alert alert-danger">Project not found</div>';
    exit();
}

// Calculate project stats
$total_expenses = getTotalProjectExpensesAllSystems($project_id);

// Get expenses breakdown
$expenses_breakdown = getExpenseBreakdownByCategory($project_id);

// Get recent expenses for this project
$recent_expenses = fetchAll("SELECT 
    e.*,
    et.expense_name,
    et.category,
    s.supplier_name
FROM expenses e
JOIN expense_types et ON e.expense_type_id = et.id
LEFT JOIN suppliers s ON e.supplier_id = s.id
WHERE e.project_id = $project_id
ORDER BY e.expense_date DESC
LIMIT 3");

?>

<!-- Project Information -->
<div class="mb-3">
    <h6 class="fw-bold mb-2">Project: <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['project_name']); ?></h6>
    <?php if ($project['company_name']): ?>
        <p class="mb-1 small">Customer: <?php echo htmlspecialchars($project['company_name']); ?></p>
    <?php elseif ($project['first_name'] || $project['last_name']): ?>
        <p class="mb-1 small">Customer: <?php echo htmlspecialchars($project['first_name'] . ' ' . $project['last_name']); ?></p>
    <?php endif; ?>
    <?php if ($project['rig_name']): ?>
        <p class="mb-1 small">Rig: <?php echo htmlspecialchars($project['rig_name']); ?></p>
    <?php endif; ?>
</div>

<!-- Financial Summary -->
<div class="row g-2 mb-3">
    <div class="col-6">
        <div class="card border-primary">
            <div class="card-body p-2">
                <small class="text-muted d-block">Contract Amount</small>
                <strong class="text-primary"><?php echo formatCurrency($project['contract_amount']); ?></strong>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card border-warning">
            <div class="card-body p-2">
                <small class="text-muted d-block">Payment Received</small>
                <strong class="text-warning"><?php echo formatCurrency($project['payment_received']); ?></strong>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card border-danger">
            <div class="card-body p-2">
                <small class="text-muted d-block">Total Expenses</small>
                <strong class="text-danger"><?php echo formatCurrency($total_expenses); ?></strong>
            </div>
        </div>
    </div>
    <div class="col-6">
        <div class="card border-<?php echo ($project['payment_received'] - $total_expenses) >= 0 ? 'success' : 'danger'; ?>">
            <div class="card-body p-2">
                <small class="text-muted d-block">Profit/Loss</small>
                <strong class="text-<?php echo ($project['payment_received'] - $total_expenses) >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo formatCurrency($project['payment_received'] - $total_expenses); ?>
                </strong>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Breakdown -->
<div class="mb-3">
    <h6 class="fw-bold mb-2">Expenses by Category</h6>
    <?php if (!empty($expenses_breakdown)): ?>
        <div class="list-group list-group-flush small">
            <?php 
            $total_breakdown = 0;
            foreach ($expenses_breakdown as $category => $data): 
                $total_breakdown += $data['total'];
            ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                <span><?php echo htmlspecialchars($category); ?></span>
                <span class="fw-bold"><?php echo formatCurrency($data['total']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 bg-light">
                <strong>Total</strong>
                <strong><?php echo formatCurrency($total_breakdown); ?></strong>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted small mb-0">No expenses recorded yet</p>
    <?php endif; ?>
</div>

<!-- Recent Expenses -->
<?php if (!empty($recent_expenses)): ?>
<div class="mt-3">
    <h6 class="fw-bold mb-2">Recent Expenses</h6>
    <div class="list-group list-group-flush small">
        <?php foreach ($recent_expenses as $exp): ?>
        <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
            <div>
                <div><?php echo htmlspecialchars($exp['expense_name']); ?></div>
                <small class="text-muted">
                    <?php echo date('d/m', strtotime($exp['expense_date'])); ?>
                    <?php if ($exp['supplier_name']): ?> | <?php echo htmlspecialchars($exp['supplier_name']); ?><?php endif; ?>
                </small>
            </div>
            <span class="fw-bold"><?php echo formatCurrency($exp['amount']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Add Expense Button -->
<div class="mt-3 text-center">
    <a href="manage_expenses.php?action=add&project_id=<?php echo $project_id; ?>" class="btn btn-sm btn-primary w-100">
        <i class="bi bi-plus-circle me-1"></i>Add Expense to this Project
    </a>
</div>

<?php
// Close database connection if needed
?>