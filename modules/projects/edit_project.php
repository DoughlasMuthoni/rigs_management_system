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

// Get fixed expenses - Direct query from fixed_expenses table
$fixed_expenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = $project_id");

// Get consumables
$consumables = fetchAll("SELECT * FROM consumables WHERE project_id = $project_id ORDER BY id");

// Get miscellaneous
$miscellaneous = fetchAll("SELECT * FROM miscellaneous WHERE project_id = $project_id ORDER BY id");

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
        $client_name = escape($_POST['client_name']);
        $rig_id = intval($_POST['rig_id']);
        $contract_amount = floatval($_POST['contract_amount']);
        $payment_received = floatval($_POST['payment_received']);
        $start_date = escape($_POST['start_date']);
        $completion_date = escape($_POST['completion_date']);
        $payment_date = escape($_POST['payment_date']);
        $notes = escape($_POST['notes']);
        
        $project_sql = "UPDATE projects SET
            project_code = '$project_code',
            project_name = '$project_name',
            client_name = '$client_name',
            rig_id = $rig_id,
            contract_amount = $contract_amount,
            payment_received = $payment_received,
            start_date = '$start_date',
            completion_date = '$completion_date',
            payment_date = '$payment_date',
            notes = '$notes'
            WHERE id = $project_id";
        
        if (!query($project_sql)) {
            throw new Exception("Failed to update project: " . mysqli_error($conn));
        }
        
        // Update fixed expenses
        $salaries = floatval($_POST['salaries']);
        $fuel_rig = floatval($_POST['fuel_rig']);
        $fuel_truck = floatval($_POST['fuel_truck']);
        $fuel_pump = floatval($_POST['fuel_pump']);
        $fuel_hired = floatval($_POST['fuel_hired']);
        $casing_surface = floatval($_POST['casing_surface']);
        $casing_screened = floatval($_POST['casing_screened']);
        $casing_plain = floatval($_POST['casing_plain']);
        
        if ($fixed_expenses) {
            // Update existing expenses
            $expense_sql = "UPDATE fixed_expenses SET
                salaries = $salaries,
                fuel_rig = $fuel_rig,
                fuel_truck = $fuel_truck,
                fuel_pump = $fuel_pump,
                fuel_hired = $fuel_hired,
                casing_surface = $casing_surface,
                casing_screened = $casing_screened,
                casing_plain = $casing_plain
                WHERE project_id = $project_id";
        } else {
            // Insert new expenses
            $expense_sql = "INSERT INTO fixed_expenses (
                project_id, salaries, fuel_rig, fuel_truck, fuel_pump, 
                fuel_hired, casing_surface, casing_screened, casing_plain
            ) VALUES (
                $project_id, $salaries, $fuel_rig, $fuel_truck, $fuel_pump,
                $fuel_hired, $casing_surface, $casing_screened, $casing_plain
            )";
        }
        
        if (!query($expense_sql)) {
            throw new Exception("Failed to save expenses: " . mysqli_error($conn));
        }
        
        // Delete existing consumables
        query("DELETE FROM consumables WHERE project_id = $project_id");
        
        // Save new consumables
        if (isset($_POST['consumables_item']) && is_array($_POST['consumables_item'])) {
            for ($i = 0; $i < count($_POST['consumables_item']); $i++) {
                $item_name = escape($_POST['consumables_item'][$i]);
                $amount = floatval($_POST['consumables_amount'][$i]);
                
                if (!empty($item_name) && $amount > 0) {
                    $sql = "INSERT INTO consumables (project_id, item_name, amount) 
                            VALUES ($project_id, '$item_name', $amount)";
                    query($sql);
                }
            }
        }
        
        // Delete existing miscellaneous
        query("DELETE FROM miscellaneous WHERE project_id = $project_id");
        
        // Save new miscellaneous
        if (isset($_POST['misc_item']) && is_array($_POST['misc_item'])) {
            for ($i = 0; $i < count($_POST['misc_item']); $i++) {
                $item_name = escape($_POST['misc_item'][$i]);
                $amount = floatval($_POST['misc_amount'][$i]);
                
                if (!empty($item_name) && $amount > 0) {
                    $sql = "INSERT INTO miscellaneous (project_id, item_name, amount) 
                            VALUES ($project_id, '$item_name', $amount)";
                    query($sql);
                }
            }
        }
        
        // Commit transaction
        query("COMMIT");
        
        $message = "Project updated successfully!";
        $message_type = "success";
        
        // Refresh data
        $project = fetchOne("SELECT * FROM projects WHERE id = $project_id");
        $fixed_expenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = $project_id");
        $consumables = fetchAll("SELECT * FROM consumables WHERE project_id = $project_id ORDER BY id");
        $miscellaneous = fetchAll("SELECT * FROM miscellaneous WHERE project_id = $project_id ORDER BY id");
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
                                       value="<?php echo htmlspecialchars($project['project_code']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="project_name" class="form-label fw-bold">
                                    <i class="bi bi-building text-primary me-1"></i>Project Name *
                                </label>
                                <input type="text" class="form-control" id="project_name" name="project_name" 
                                       value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="client_name" class="form-label fw-bold">
                                    <i class="bi bi-person text-primary me-1"></i>Client Name
                                </label>
                                <input type="text" class="form-control" id="client_name" name="client_name"
                                       value="<?php echo htmlspecialchars($project['client_name']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="rig_id" class="form-label fw-bold">
                                    <i class="bi bi-truck text-primary me-1"></i>Assigned Rig *
                                </label>
                                <select class="form-select" id="rig_id" name="rig_id" required>
                                    <option value="">Select Rig</option>
                                    <?php foreach ($rigs as $rig): ?>
                                        <option value="<?php echo $rig['id']; ?>" 
                                            <?php echo $rig['id'] == $project['rig_id'] ? 'selected' : ''; ?>>
                                            <?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contract_amount" class="form-label fw-bold">
                                    <i class="bi bi-currency-dollar text-primary me-1"></i>Contract Amount (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="contract_amount" name="contract_amount" 
                                       value="<?php echo $project['contract_amount']; ?>" required step="0.01" min="0">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="payment_received" class="form-label fw-bold">
                                    <i class="bi bi-cash text-primary me-1"></i>Payment Received (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="payment_received" name="payment_received" 
                                       value="<?php echo $project['payment_received']; ?>" required step="0.01" min="0">
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
                                    <i class="bi bi-card-text text-primary me-1"></i>Notes
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($project['notes']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Salary Expenses -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="m-0 fw-bold">
                                    <i class="bi bi-people-fill me-2"></i>Salary Expenses
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="salaries" class="form-label fw-bold">
                                            <i class="bi bi-wallet2 text-success me-1"></i>Total Team Salaries (Ksh) *
                                        </label>
                                        <input type="number" class="form-control" id="salaries" name="salaries" 
                                               value="<?php echo isset($fixed_expenses['salaries']) ? $fixed_expenses['salaries'] : ''; ?>" required step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fuel Expenses -->
                        <div class="card border-warning mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="m-0 fw-bold">
                                    <i class="bi bi-fuel-pump me-2"></i>Fuel Expenses
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="fuel_rig" class="form-label fw-bold">
                                            <i class="bi bi-truck text-warning me-1"></i>Rig Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_rig" name="fuel_rig" 
                                               value="<?php echo isset($fixed_expenses['fuel_rig']) ? $fixed_expenses['fuel_rig'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_truck" class="form-label fw-bold">
                                            <i class="bi bi-truck text-warning me-1"></i>Support Truck Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_truck" name="fuel_truck" 
                                               value="<?php echo isset($fixed_expenses['fuel_truck']) ? $fixed_expenses['fuel_truck'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_pump" class="form-label fw-bold">
                                            <i class="bi bi-droplet text-warning me-1"></i>Test Pumping Truck Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_pump" name="fuel_pump" 
                                               value="<?php echo isset($fixed_expenses['fuel_pump']) ? $fixed_expenses['fuel_pump'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_hired" class="form-label fw-bold">
                                            <i class="bi bi-car-front text-warning me-1"></i>Hired Vehicle Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_hired" name="fuel_hired" 
                                               value="<?php echo isset($fixed_expenses['fuel_hired']) ? $fixed_expenses['fuel_hired'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Casing & Materials -->
                        <div class="card border-info mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="m-0 fw-bold">
                                    <i class="bi bi-pipe me-2"></i>Casing & Materials
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="casing_surface" class="form-label fw-bold">
                                            <i class="bi bi-circle text-info me-1"></i>Surface Casings (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="casing_surface" name="casing_surface" 
                                               value="<?php echo isset($fixed_expenses['casing_surface']) ? $fixed_expenses['casing_surface'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="casing_screened" class="form-label fw-bold">
                                            <i class="bi bi-grid-3x3 text-info me-1"></i>Screened Casings (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="casing_screened" name="casing_screened" 
                                               value="<?php echo isset($fixed_expenses['casing_screened']) ? $fixed_expenses['casing_screened'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="casing_plain" class="form-label fw-bold">
                                            <i class="bi bi-circle-fill text-info me-1"></i>Plain Casings (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="casing_plain" name="casing_plain" 
                                               value="<?php echo isset($fixed_expenses['casing_plain']) ? $fixed_expenses['casing_plain'] : ''; ?>" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Consumables -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="m-0 fw-bold">
                                    <i class="bi bi-tools me-2"></i>Consumables
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="consumables-container">
                                    <?php if (count($consumables) == 0): ?>
                                    <div class="row g-3 mb-3 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Item Name</label>
                                            <input type="text" name="consumables_item[]" class="form-control" 
                                                   placeholder="e.g., Drilling Bits, Grease, Safety Equipment">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Amount (Ksh)</label>
                                            <input type="number" name="consumables_amount[]" class="form-control" 
                                                   step="0.01" min="0" placeholder="0.00">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($consumables as $index => $item): ?>
                                        <div class="row g-3 mb-3 align-items-end">
                                            <div class="col-md-8">
                                                <label class="form-label fw-bold">Item Name</label>
                                                <input type="text" name="consumables_item[]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold">Amount (Ksh)</label>
                                                <input type="number" name="consumables_amount[]" class="form-control" 
                                                       value="<?php echo $item['amount']; ?>" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="addConsumableItem()">
                                    <i class="bi bi-plus-circle me-1"></i>Add Consumable Item
                                </button>
                            </div>
                        </div>
                        
                        <!-- Miscellaneous Expenses -->
                        <div class="card border-secondary mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="m-0 fw-bold">
                                    <i class="bi bi-cart-plus me-2"></i>Miscellaneous Expenses
                                </h6>
                            </div>
                            <div class="card-body">
                                <div id="misc-container">
                                    <?php if (count($miscellaneous) == 0): ?>
                                    <div class="row g-3 mb-3 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label fw-bold">Item Name</label>
                                            <input type="text" name="misc_item[]" class="form-control" 
                                                   placeholder="e.g., Accommodation, Transport, Client Meetings">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Amount (Ksh)</label>
                                            <input type="number" name="misc_amount[]" class="form-control" 
                                                   step="0.01" min="0" placeholder="0.00">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($miscellaneous as $index => $item): ?>
                                        <div class="row g-3 mb-3 align-items-end">
                                            <div class="col-md-8">
                                                <label class="form-label fw-bold">Item Name</label>
                                                <input type="text" name="misc_item[]" class="form-control" 
                                                       value="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label fw-bold">Amount (Ksh)</label>
                                                <input type="number" name="misc_amount[]" class="form-control" 
                                                       value="<?php echo $item['amount']; ?>" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-1">
                                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addMiscItem()">
                                    <i class="bi bi-plus-circle me-1"></i>Add Miscellaneous Item
                                </button>
                            </div>
                        </div>
                        
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

<script>
// Counter for dynamic items
function addConsumableItem() {
    const container = document.getElementById('consumables-container');
    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 align-items-end';
    div.innerHTML = `
        <div class="col-md-8">
            <label class="form-label fw-bold">Item Name</label>
            <input type="text" name="consumables_item[]" class="form-control" 
                   placeholder="e.g., Drilling Bits, Grease, Safety Equipment">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">Amount (Ksh)</label>
            <input type="number" name="consumables_amount[]" class="form-control" 
                   step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
    
    // Enable delete button on first item if it was disabled
    if (container.children.length > 1) {
        container.firstElementChild.querySelector('.btn-danger').disabled = false;
    }
}

function addMiscItem() {
    const container = document.getElementById('misc-container');
    const div = document.createElement('div');
    div.className = 'row g-3 mb-3 align-items-end';
    div.innerHTML = `
        <div class="col-md-8">
            <label class="form-label fw-bold">Item Name</label>
            <input type="text" name="misc_item[]" class="form-control" 
                   placeholder="e.g., Accommodation, Transport, Client Meetings">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-bold">Amount (Ksh)</label>
            <input type="number" name="misc_amount[]" class="form-control" 
                   step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(div);
    
    // Enable delete button on first item if it was disabled
    if (container.children.length > 1) {
        container.firstElementChild.querySelector('.btn-danger').disabled = false;
    }
}

function removeItem(button) {
    const row = button.closest('.row');
    const container = row.parentElement;
    
    // Don't remove if it's the last item
    if (container.children.length > 1) {
        row.remove();
        
        // Disable delete button if only one item left
        if (container.children.length === 1) {
            container.firstElementChild.querySelector('.btn-danger').disabled = true;
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>