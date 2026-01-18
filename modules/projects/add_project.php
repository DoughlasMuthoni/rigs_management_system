<?php
require_once '../../includes/header.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start transaction
        query("START TRANSACTION");
        
        // Insert project
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
        
        $project_sql = "INSERT INTO projects (
            project_code, project_name, client_name, rig_id, 
            contract_amount, payment_received, start_date, 
            completion_date, payment_date, notes, status
        ) VALUES (
            '$project_code', '$project_name', '$client_name', $rig_id,
            $contract_amount, $payment_received, '$start_date',
            '$completion_date', '$payment_date', '$notes', 'completed'
        )";
        
        if (!query($project_sql)) {
            throw new Exception("Failed to save project: " . mysqli_error($conn));
        }
        
        $project_id = lastInsertId();
        
        // Insert fixed expenses
        $salaries = floatval($_POST['salaries']);
        $fuel_rig = floatval($_POST['fuel_rig']);
        $fuel_truck = floatval($_POST['fuel_truck']);
        $fuel_pump = floatval($_POST['fuel_pump']);
        $fuel_hired = floatval($_POST['fuel_hired']);
        $casing_surface = floatval($_POST['casing_surface']);
        $casing_screened = floatval($_POST['casing_screened']);
        $casing_plain = floatval($_POST['casing_plain']);
        
        $expense_sql = "INSERT INTO fixed_expenses (
            project_id, salaries, fuel_rig, fuel_truck, fuel_pump, 
            fuel_hired, casing_surface, casing_screened, casing_plain
        ) VALUES (
            $project_id, $salaries, $fuel_rig, $fuel_truck, $fuel_pump,
            $fuel_hired, $casing_surface, $casing_screened, $casing_plain
        )";
        
        if (!query($expense_sql)) {
            throw new Exception("Failed to save expenses: " . mysqli_error($conn));
        }
        
        // Save consumables
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
        
        // Save miscellaneous
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
        
        $message = "Project saved successfully! Project Profit: " . 
                   formatCurrency(calculateProjectProfit($project_id));
        $message_type = "success";
        
    } catch (Exception $e) {
        query("ROLLBACK");
        $message = $e->getMessage();
        $message_type = "error";
    }
}

// Get all active rigs for dropdown
$rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
?>

<main class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Add New Project</h1>
                <p class="text-muted mb-0">Enter project details and expenses from finance summary</p>
            </div>
            <div>
                <a href="../../index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
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
    
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-clipboard-plus me-2"></i>Project Information
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="projectForm">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="project_code" class="form-label fw-bold">
                                    <i class="bi bi-tag text-primary me-1"></i>Project Code *
                                </label>
                                <input type="text" class="form-control" id="project_code" name="project_code" 
                                       required placeholder="WL-2024-001">
                                <div class="form-text">Unique project identifier</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="project_name" class="form-label fw-bold">
                                    <i class="bi bi-building text-primary me-1"></i>Project Name *
                                </label>
                                <input type="text" class="form-control" id="project_name" name="project_name" required>
                                <div class="form-text">Name of the drilling project</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="client_name" class="form-label fw-bold">
                                    <i class="bi bi-person text-primary me-1"></i>Client Name
                                </label>
                                <input type="text" class="form-control" id="client_name" name="client_name">
                                <div class="form-text">Client or company name</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="rig_id" class="form-label fw-bold">
                                    <i class="bi bi-truck text-primary me-1"></i>Assigned Rig *
                                </label>
                                <select class="form-select" id="rig_id" name="rig_id" required>
                                    <option value="">Select Rig</option>
                                    <?php foreach ($rigs as $rig): ?>
                                        <option value="<?php echo $rig['id']; ?>">
                                            <?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the rig used for this project</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contract_amount" class="form-label fw-bold">
                                    <i class="bi bi-currency-dollar text-primary me-1"></i>Contract Amount (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="contract_amount" name="contract_amount" 
                                       required step="0.01" min="0">
                                <div class="form-text">Total contract value</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="payment_received" class="form-label fw-bold">
                                    <i class="bi bi-cash text-primary me-1"></i>Payment Received (Ksh) *
                                </label>
                                <input type="number" class="form-control" id="payment_received" name="payment_received" 
                                       required step="0.01" min="0">
                                <div class="form-text">Actual payment received from client</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="start_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-start text-primary me-1"></i>Start Date
                                </label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="completion_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-check text-primary me-1"></i>Completion Date *
                                </label>
                                <input type="date" class="form-control" id="completion_date" name="completion_date" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="payment_date" class="form-label fw-bold">
                                    <i class="bi bi-calendar-date text-primary me-1"></i>Payment Date
                                </label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date">
                            </div>
                            
                            <div class="col-12">
                                <label for="notes" class="form-label fw-bold">
                                    <i class="bi bi-card-text text-primary me-1"></i>Notes (Optional)
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional notes about the project..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Salary Expenses -->
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-primary text-white">
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
                                               required step="0.01" min="0">
                                        <div class="form-text">Total salary cost for the entire team</div>
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
                                               step="0.01" min="0" value="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_truck" class="form-label fw-bold">
                                            <i class="bi bi-truck text-warning me-1"></i>Support Truck Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_truck" name="fuel_truck" 
                                               step="0.01" min="0" value="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_pump" class="form-label fw-bold">
                                            <i class="bi bi-droplet text-warning me-1"></i>Test Pumping Truck Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_pump" name="fuel_pump" 
                                               step="0.01" min="0" value="0">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label for="fuel_hired" class="form-label fw-bold">
                                            <i class="bi bi-car-front text-warning me-1"></i>Hired Vehicle Fuel (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="fuel_hired" name="fuel_hired" 
                                               step="0.01" min="0" value="0">
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
                                               step="0.01" min="0" value="0">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="casing_screened" class="form-label fw-bold">
                                            <i class="bi bi-grid-3x3 text-info me-1"></i>Screened Casings (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="casing_screened" name="casing_screened" 
                                               step="0.01" min="0" value="0">
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label for="casing_plain" class="form-label fw-bold">
                                            <i class="bi bi-circle-fill text-info me-1"></i>Plain Casings (Ksh)
                                        </label>
                                        <input type="number" class="form-control" id="casing_plain" name="casing_plain" 
                                               step="0.01" min="0" value="0">
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
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addMiscItem()">
                                    <i class="bi bi-plus-circle me-1"></i>Add Miscellaneous Item
                                </button>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between border-top pt-4">
                            <div>
                                <button type="reset" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset Form
                                </button>
                                <a href="../../index.php" class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-2"></i>Save Project
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Counter for dynamic items
let consumableCount = 1;
let miscCount = 1;

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
    consumableCount++;
    
    // Enable delete button on first item
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
    miscCount++;
    
    // Enable delete button on first item
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

// Set default dates
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    const completionDate = document.getElementById('completion_date');
    const paymentDate = document.getElementById('payment_date');
    const startDate = document.getElementById('start_date');
    
    if (completionDate) completionDate.value = today;
    if (paymentDate) paymentDate.value = today;
    
    // Set start date to 7 days ago
    if (startDate) {
        const weekAgo = new Date();
        weekAgo.setDate(weekAgo.getDate() - 7);
        startDate.value = weekAgo.toISOString().split('T')[0];
    }
    
    // Auto-calculate profit on form submit
    document.getElementById('projectForm').addEventListener('submit', function(e) {
        // You can add validation or calculations here
        const contractAmount = parseFloat(document.getElementById('contract_amount').value) || 0;
        const paymentReceived = parseFloat(document.getElementById('payment_received').value) || 0;
        
        if (paymentReceived > contractAmount) {
            if (!confirm('Payment received is greater than contract amount. Continue anyway?')) {
                e.preventDefault();
            }
        }
    });
});
</script>

<style>
    /* Custom styles for the form */
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
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .btn-primary {
            width: 100%;
            margin-top: 10px;
        }
        
        .d-flex {
            flex-direction: column;
            gap: 10px;
        }
        
        .col-md-1 .btn-danger {
            height: 38px;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>