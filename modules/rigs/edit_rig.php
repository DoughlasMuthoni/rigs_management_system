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

// Check if rig ID is provided
if (!isset($_GET['id'])) {
    header('Location: view_rigs.php');
    exit();
}

$rig_id = intval($_GET['id']);

// Get rig details
$rig = fetchOne("SELECT * FROM rigs WHERE id = $rig_id");
if (!$rig) {
    header('Location: view_rigs.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rig_name = escape($_POST['rig_name']);
    $status = escape($_POST['status']);
    $purchase_date = escape($_POST['purchase_date']);
    $description = escape($_POST['description']);
    
    $sql = "UPDATE rigs SET 
            rig_name = '$rig_name',
            status = '$status',
            purchase_date = '$purchase_date',
            description = '$description'
            WHERE id = $rig_id";
    
    if (query($sql)) {
        $message = '<div class="alert alert-success">Rig updated successfully!</div>';
        $message_type = 'success';
        
        // Refresh rig data
        $rig = fetchOne("SELECT * FROM rigs WHERE id = $rig_id");
    } else {
        $message = '<div class="alert alert-danger">Failed to update rig! Please try again.</div>';
        $message_type = 'error';
    }
}
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Edit Rig: <?php echo $rig['rig_name']; ?></h1>
                <p>Update rig information and status</p>
            </div>
            <div>
                <a href="view_rigs.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Rigs
                </a>
                <a href="rig_details.php?id=<?php echo $rig_id; ?>" class="btn btn-info">
                    <i class="bi bi-eye me-2"></i>View Details
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Form Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold text-primary">Edit Rig Information</h6>
                    <span class="badge bg-<?php echo $rig['status'] == 'active' ? 'success' : 'secondary'; ?>">
                        <?php echo ucfirst($rig['status']); ?>
                    </span>
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
                                       value="<?php echo $rig['rig_name']; ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="rig_code" class="form-label">
                                    <i class="bi bi-tag me-1"></i>Rig Code
                                </label>
                                <input type="text" class="form-control" id="rig_code" 
                                       value="<?php echo $rig['rig_code']; ?>" readonly disabled>
                                <div class="form-text text-muted">Rig code cannot be changed</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">
                                    <i class="bi bi-power me-1"></i>Status *
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $rig['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $rig['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="purchase_date" class="form-label">
                                    <i class="bi bi-calendar me-1"></i>Purchase Date
                                </label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                       value="<?php echo $rig['purchase_date']; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="description" class="form-label">
                                <i class="bi bi-card-text me-1"></i>Description
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo $rig['description']; ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view_rigs.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Update Rig
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Rig Stats Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">
                        <i class="bi bi-graph-up me-2"></i>Rig Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    // Get rig statistics
                    $stats_sql = "SELECT 
                                    COUNT(p.id) as total_projects,
                                    COALESCE(SUM(p.payment_received), 0) as total_revenue,
                                    COALESCE(SUM(p.payment_received) / COUNT(p.id), 0) as avg_revenue
                                 FROM projects p
                                 WHERE p.rig_id = $rig_id";
                    $stats = fetchOne($stats_sql);
                    ?>
                    
                    <div class="text-center mb-4">
                        <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 80px; height: 80px;">
                            <i class="bi bi-truck text-white" style="font-size: 2rem;"></i>
                        </div>
                        <h5 class="mt-3"><?php echo $rig['rig_name']; ?></h5>
                        <p class="text-muted"><?php echo $rig['rig_code']; ?></p>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Total Projects</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['total_projects']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Total Revenue</span>
                            <span class="fw-bold text-success"><?php echo formatCurrency($stats['total_revenue']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Average Revenue</span>
                            <span class="fw-bold text-info"><?php echo formatCurrency($stats['avg_revenue']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Status</span>
                            <span class="badge bg-<?php echo $rig['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($rig['status']); ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Created</span>
                            <span><?php echo date('M d, Y', strtotime($rig['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="rig_details.php?id=<?php echo $rig_id; ?>" class="btn btn-outline-info btn-sm w-100">
                            <i class="bi bi-bar-chart me-2"></i>View Detailed Performance
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="card shadow border-danger">
                <div class="card-header py-3 bg-danger text-white">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">These actions are irreversible. Use with caution.</p>
                    
                    <div class="d-grid gap-2">
                        <a href="?toggle_status=1&rig_id=<?php echo $rig_id; ?>" 
                           class="btn btn-outline-<?php echo $rig['status'] == 'active' ? 'warning' : 'success'; ?>"
                           onclick="return confirm('Are you sure you want to <?php echo $rig['status'] == 'active' ? 'deactivate' : 'activate'; ?> this rig?')">
                            <i class="bi bi-power me-2"></i>
                            <?php echo $rig['status'] == 'active' ? 'Deactivate Rig' : 'Activate Rig'; ?>
                        </a>
                        
                        <a href="?delete=1&rig_id=<?php echo $rig_id; ?>" 
                           class="btn btn-outline-danger"
                           onclick="return confirm('Are you sure you want to delete this rig? This action cannot be undone.')">
                            <i class="bi bi-trash me-2"></i>Delete Rig
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>