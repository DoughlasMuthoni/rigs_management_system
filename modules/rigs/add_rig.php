<?php
// 1. Load config.php
require_once '../../config.php';

// 2. Load init.php
require_once ROOT_PATH . '/includes/init.php';

// 3. Load functions.php
require_once ROOT_PATH . '/includes/functions.php';

// 4. Load header.php
require_once ROOT_PATH . '/includes/header.php';

// Check user permissions
if (!hasRole('admin') && !hasRole('manager')) {
    header('Location: ../../index.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rig_name = escape($_POST['rig_name']);
    $rig_code = escape($_POST['rig_code']);
    $status = escape($_POST['status']);
    $purchase_date = escape($_POST['purchase_date']);
    $description = escape($_POST['description']);
    
    // Validate rig code uniqueness
    $check_sql = "SELECT id FROM rigs WHERE rig_code = '$rig_code'";
    if (countRows($check_sql) > 0) {
        $message = '<div class="alert alert-danger">Rig code already exists! Please use a different code.</div>';
        $message_type = 'error';
    } else {
        $sql = "INSERT INTO rigs (rig_name, rig_code, status, purchase_date, description) 
                VALUES ('$rig_name', '$rig_code', '$status', '$purchase_date', '$description')";
        
        if (query($sql)) {
            $message = '<div class="alert alert-success">Rig added successfully! <a href="view_rigs.php" class="alert-link">View all rigs</a></div>';
            $message_type = 'success';
            
            // Reset form on success
            $_POST = array();
        } else {
            $message = '<div class="alert alert-danger">Failed to add rig! Please try again.</div>';
            $message_type = 'error';
        }
    }
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Add New Rig</h1>
                <p>Register a new drilling rig for performance tracking</p>
            </div>
            <div>
                <a href="view_rigs.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Rigs
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Form Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Rig Information</h6>
                </div>
                <div class="card-body">
                    <?php echo $message; ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="rig_name" class="form-label">
                                    <i class="bi bi-truck me-1"></i>Rig Name *
                                </label>
                                <input type="text" class="form-control" id="rig_name" name="rig_name" 
                                       value="<?php echo $_POST['rig_name'] ?? ''; ?>" 
                                       required placeholder="e.g., Rig Alpha">
                                <div class="form-text">Give your rig a descriptive name</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="rig_code" class="form-label">
                                    <i class="bi bi-tag me-1"></i>Rig Code *
                                </label>
                                <input type="text" class="form-control" id="rig_code" name="rig_code" 
                                       value="<?php echo $_POST['rig_code'] ?? ''; ?>" 
                                       required placeholder="e.g., RA">
                                <div class="form-text">Unique code for the rig (e.g., RA, RB, RG)</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">
                                    <i class="bi bi-power me-1"></i>Status *
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo ($_POST['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($_POST['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <div class="form-text">Active rigs can be assigned to projects</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="purchase_date" class="form-label">
                                    <i class="bi bi-calendar me-1"></i>Purchase Date
                                </label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                       value="<?php echo $_POST['purchase_date'] ?? ''; ?>">
                                <div class="form-text">When was this rig acquired?</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">
                                <i class="bi bi-card-text me-1"></i>Description
                            </label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" placeholder="Add any additional details about the rig..."><?php echo $_POST['description'] ?? ''; ?></textarea>
                            <div class="form-text">Optional: Specifications, special features, or notes</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset Form
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Rig
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Help Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-info-circle me-2"></i>Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="bi bi-lightbulb me-2"></i>Tips</h6>
                        <ul class="mb-0 small">
                            <li>Use descriptive names for easy identification</li>
                            <li>Rig codes should be unique and memorable</li>
                            <li>Mark rigs as inactive during maintenance periods</li>
                            <li>Add purchase date for depreciation tracking</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Important</h6>
                        <ul class="mb-0 small">
                            <li>Rig codes cannot be changed after creation</li>
                            <li>Active rigs will appear in project assignment dropdowns</li>
                            <li>Inactive rigs cannot be assigned to new projects</li>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <a href="view_rigs.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>View All Rigs
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Existing Rigs Preview -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-list-ul me-2"></i>Existing Rigs
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    $existing_rigs = fetchAll("SELECT rig_name, rig_code, status FROM rigs ORDER BY rig_name LIMIT 5");
                    if (count($existing_rigs) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($existing_rigs as $rig): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div>
                                    <h6 class="mb-0 small"><?php echo $rig['rig_name']; ?></h6>
                                    <small class="text-muted"><?php echo $rig['rig_code']; ?></small>
                                </div>
                                <span class="badge bg-<?php echo $rig['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo $rig['status']; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($existing_rigs) >= 5): ?>
                            <div class="text-center mt-2">
                                <a href="view_rigs.php" class="btn btn-sm btn-link">View all rigs →</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-truck display-6 text-muted mb-3"></i>
                            <p class="text-muted">No rigs registered yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default purchase date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('purchase_date').value = today;
    
    // Auto-generate rig code from name
    document.getElementById('rig_name').addEventListener('input', function() {
        const rigCodeField = document.getElementById('rig_code');
        if (!rigCodeField.value) {
            const name = this.value;
            if (name.length >= 2) {
                // Take first letter of each word
                const words = name.split(' ');
                if (words.length >= 2) {
                    const code = words.map(word => word[0]).join('').toUpperCase();
                    rigCodeField.value = code;
                } else if (name.length >= 2) {
                    rigCodeField.value = name.substring(0, 2).toUpperCase();
                }
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>