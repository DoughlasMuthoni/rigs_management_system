<?php
// 1. Load config.php first to define BASE_URL and other constants
require_once '../../config.php';

// 2. Define month/year variables for header
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
}

// Validate year
if ($selected_year < 2020 || $selected_year > 2100) {
    $selected_year = date('Y');
}

// 3. Set other required variables for header.php
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$current_page = basename($_SERVER['PHP_SELF']);

// 4. Load functions.php
require_once ROOT_PATH . '/includes/functions.php';

// 5. Load header.php (now all variables are defined)
require_once ROOT_PATH . '/includes/header.php';


// Check if project ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_projects.php');
    exit();
}

$project_id = intval($_GET['id']);

// Get project details
$project = fetchOne("SELECT * FROM projects WHERE id = $project_id");
if (!$project) {
    header('Location: view_projects.php');
    exit();
}

// Get current salary from new expenses system
$salary_data = fetchOne("SELECT SUM(amount) as total_salary 
                         FROM expenses 
                         WHERE project_id = $project_id 
                         AND ref_number = 'MONTHLY-SALARY'");

$current_salary = $salary_data ? $salary_data['total_salary'] : 0;

// Calculate project expenses summary using the function
$expenses_summary = getProjectExpenses($project_id);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start transaction
        query("START TRANSACTION");
        
        // Update project
        $project_code = escape($_POST['project_code']);
        $project_name = escape($_POST['project_name']);
        $customer_id = intval($_POST['customer_id']);
        $rig_id = !empty($_POST['rig_id']) ? intval($_POST['rig_id']) : 'NULL';
        $depth = escape($_POST['depth']);
        $project_type = escape($_POST['project_type']);
        $contract_amount = floatval($_POST['contract_amount']);
        $payment_received = floatval($_POST['payment_received']);
        $start_date = escape($_POST['start_date']);
        $completion_date = escape($_POST['completion_date']);
        $payment_date = escape($_POST['payment_date']);
        $notes = escape($_POST['notes']);
        $estimate_cost = floatval($_POST['estimate_cost']);
        
        $project_sql = "UPDATE projects SET
            project_code = '$project_code',
            project_name = '$project_name',
            customer_id = $customer_id,
            rig_id = $rig_id,
            depth = '$depth',
            project_type = '$project_type',
            contract_amount = $contract_amount,
            payment_received = $payment_received,
            start_date = '$start_date',
            completion_date = '$completion_date',
            payment_date = '$payment_date',
            notes = '$notes',
            estimate_cost = $estimate_cost
            WHERE id = $project_id";
        
        if (!query($project_sql)) {
            throw new Exception("Failed to update project: " . mysqli_error($conn));
        }
        
        // Note: Salaries are now managed through the monthly salary allocation system
        // or through the expenses module. No need to save them here.
        
        // Commit transaction
        query("COMMIT");
        
        $message = "Project updated successfully!";
        $message_type = "success";
        
        // Refresh data
        $project = fetchOne("SELECT * FROM projects WHERE id = $project_id");
        $expenses_summary = getProjectExpenses($project_id);
        
    } catch (Exception $e) {
        query("ROLLBACK");
        $message = $e->getMessage();
        $message_type = "error";
    }
}

// Get all active rigs for dropdown
$rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Edit Project</h1>
                <p class="text-muted mb-0">Update project: <?php echo $project['project_code']; ?> - <?php echo $project['project_name']; ?></p>
            </div>
            <div>
                <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-outline-info me-2">
                    <i class="bi bi-eye me-2"></i>View Details
                </a>
                <a href="view_projects.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Projects
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <!-- Project Summary Card -->
    <div class="card border-primary shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h6 class="text-primary mb-1">Current Profit</h6>
                    <h3 class="text-<?php echo calculateProjectProfit($project_id) >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo formatCurrency(calculateProjectProfit($project_id)); ?>
                    </h3>
                </div>
                <div class="col-md-4">
                    <h6 class="text-primary mb-1">Revenue vs Expenses</h6>
                    <div class="d-flex align-items-center">
                        <div class="text-success me-3">
                            <i class="bi bi-arrow-up-circle fs-4"></i>
                            <span class="fs-5"><?php echo formatCurrency($project['payment_received']); ?></span>
                        </div>
                        <div class="text-warning">
                            <i class="bi bi-arrow-down-circle fs-4"></i>
                            <span class="fs-5">
                                <?php echo formatCurrency($expenses_summary['total']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <h6 class="text-primary mb-1">Profit Margin</h6>
                    <h4 class="text-<?php echo (($project['payment_received'] - $expenses_summary['total']) / $project['payment_received'] * 100) >= 0 ? 'success' : 'danger'; ?>">
                        <?php 
                        $profit = calculateProjectProfit($project_id);
                        $margin = $project['payment_received'] > 0 ? ($profit / $project['payment_received']) * 100 : 0;
                        echo number_format($margin, 2); 
                        ?>%
                    </h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-pencil-square me-2"></i>Edit Project Details
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="editProjectForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="project_code" class="form-label fw-bold">
                                    <i class="bi bi-tag text-primary me-1"></i>Project Code *
                                </label>
                                <input type="text" class="form-control" id="project_code" name="project_code" 
                                       value="<?php echo htmlspecialchars($project['project_code']); ?>" required placeholder="WL-2024-001">
                                <div class="form-text">Unique project identifier</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="project_name" class="form-label fw-bold">
                                    <i class="bi bi-building text-primary me-1"></i>Project Name *
                                </label>
                                <input type="text" class="form-control" id="project_name" name="project_name" 
                                       value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                                <div class="form-text">Name of the drilling project</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="customer_id" class="form-label fw-bold">
                                    <i class="bi bi-person text-primary me-1"></i>Customer *
                                </label>
                                <select class="form-select select2" id="customer_id" name="customer_id" required>
                                    <option value="">Select Customer</option>
                                    <?php 
                                    $customers = getAllCustomers('active');
                                    foreach ($customers as $customer): 
                                        $display_name = $customer['first_name'] . ' ' . $customer['last_name'];
                                        if ($customer['company_name']) {
                                            $display_name .= ' (' . $customer['company_name'] . ')';
                                        }
                                        $selected = ($project['customer_id'] == $customer['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $customer['id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($display_name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <a href="../customer/manage_customers.php?action=add" target="_blank" class="small">
                                        <i class="bi bi-plus-circle"></i> Add new customer
                                    </a>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="project_type" class="form-label fw-bold">
                                    <i class="bi bi-gear text-primary me-1"></i>Project Type *
                                </label>
                                <select class="form-select" id="project_type" name="project_type" required>
                                    <option value="Survey" <?php echo $project['project_type'] == 'Survey' ? 'selected' : ''; ?>>Survey</option>
                                    <option value="Drilling" <?php echo $project['project_type'] == 'Drilling' ? 'selected' : ''; ?>>Drilling</option>
                                    <option value="Test Pumping" <?php echo $project['project_type'] == 'Test Pumping' ? 'selected' : ''; ?>>Test Pumping</option>
                                    <option value="Equipping" <?php echo $project['project_type'] == 'Equipping' ? 'selected' : ''; ?>>Equipping</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="depth" class="form-label fw-bold">
                                    <i class="bi bi-arrow-down text-primary me-1"></i>Depth (meters)
                                </label>
                                <input type="number" class="form-control" id="depth" name="depth" 
                                       value="<?php echo $project['depth']; ?>" step="0.1" min="0" placeholder="e.g., 100.5">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="rig_id" class="form-label fw-bold">
                                    <i class="bi bi-truck text-primary me-1"></i>Assigned Rig
                                </label>
                                <select class="form-select" id="rig_id" name="rig_id">
                                    <option value="">-- No Rig Assigned --</option>
                                    <?php foreach ($rigs as $rig): ?>
                                        <option value="<?php echo $rig['id']; ?>" 
                                            <?php echo $rig['id'] == $project['rig_id'] ? 'selected' : ''; ?>>
                                            <?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Optional - projects can be completed without rig assignment</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contract_amount" class="form-label fw-bold">
                                    <i class="bi bi-currency-dollar text-primary me-1"></i>Contract Amount (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="contract_amount" name="contract_amount" 
                                       value="<?php echo $project['contract_amount']; ?>" required step="0.01" min="0">
                                <div class="form-text">Total contract value</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="payment_received" class="form-label fw-bold">
                                    <i class="bi bi-cash text-primary me-1"></i>Payment Received (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="payment_received" name="payment_received" 
                                       value="<?php echo $project['payment_received']; ?>" required step="0.01" min="0">
                                <div class="form-text">Actual payment received from client</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="estimate_cost" class="form-label fw-bold">
                                    <i class="bi bi-calculator text-primary me-1"></i>Estimated Cost (Ksh)
                                </label>
                                <input type="number" class="form-control" id="estimate_cost" name="estimate_cost" 
                                       value="<?php echo $project['estimate_cost']; ?>" step="0.01" min="0">
                                <div class="form-text">Estimated total cost for planning purposes</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="start_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-start text-primary me-1"></i>Start Date
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date"
                                       value="<?php echo $project['start_date']; ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="completion_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-check text-primary me-1"></i>Completion Date *
                                </label>
                                <input type="date" class="form-control" id="completion_date" name="completion_date"
                                       value="<?php echo $project['completion_date']; ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="payment_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-date text-primary me-1"></i>Payment Date
                                </label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date"
                                       value="<?php echo $project['payment_date']; ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="notes" class="form-label fw-bold">
                                    <i class="bi bi-card-text text-primary me-1"></i>Notes (Optional)
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional notes about the project..."><?php echo htmlspecialchars($project['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- REMOVE THE ENTIRE Salary Information Only CARD -->
                        <!-- No need to edit salaries here anymore -->
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between border-top pt-4">
                            <div>
                                <a href="project_details.php?id=<?php echo $project_id; ?>" class="btn btn-outline-info me-2">
                                    <i class="bi bi-eye me-2"></i>View Details
                                </a>
                                <a href="view_projects.php" class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                            <div>
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                </button>
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="bi bi-save me-2"></i>Update Project
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Initialize Select2 for customer dropdown
$(document).ready(function() {
    $('.select2').select2({
        placeholder: "Select customer",
        allowClear: true,
        width: '100%'
    });
    
    // Form validation
    $('#editProjectForm').on('submit', function(e) {
        const contractAmount = parseFloat($('#contract_amount').val()) || 0;
        const paymentReceived = parseFloat($('#payment_received').val()) || 0;
        
        // Validate payment received doesn't exceed contract amount
        if (paymentReceived > contractAmount) {
            if (!confirm('Payment received is greater than contract amount. Continue anyway?')) {
                e.preventDefault();
                return false;
            }
        }
        
        // Validate completion date is after start date
        const startDateVal = $('#start_date').val();
        const completionDateVal = $('#completion_date').val();
        
        if (startDateVal && completionDateVal) {
            const start = new Date(startDateVal);
            const completion = new Date(completionDateVal);
            
            if (completion < start) {
                alert('Completion date must be after start date!');
                e.preventDefault();
                return false;
            }
        }
        
        return true;
    });
});
</script>

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
    
    .form-label {
        font-weight: 500;
        color: #5a5c69;
        margin-bottom: 0.5rem;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        border: none;
        padding: 0.75rem 2rem;
        font-weight: 600;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #162b5c 0%, #1e3c72 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(30, 60, 114, 0.2);
    }
    
    .alert {
        border-radius: 8px;
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .page-header {
        background: white;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
    }
    
    .page-header h1 {
        color: #1e3c72;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .page-header p {
        color: #6c757d;
        margin-bottom: 0;
    }
    
    .select2-container--default .select2-selection--single {
        height: 38px;
        border: 1px solid #ced4da;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>