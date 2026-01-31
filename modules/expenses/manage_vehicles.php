<?php
// manage_vehicles.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$vehicle_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'save') {
        try {
            $vehicle_data = [
                'vehicle_no' => escape($_POST['vehicle_no']),
                'vehicle_type' => escape($_POST['vehicle_type']),
                'make' => escape($_POST['make']),
                'model' => escape($_POST['model']),
                'year' => !empty($_POST['year']) ? intval($_POST['year']) : 'NULL',
                'assigned_to' => escape($_POST['assigned_to']),
                'notes' => escape($_POST['notes']),
                'status' => escape($_POST['status'])
            ];
            
            if ($vehicle_id > 0) {
                // Update existing vehicle
                $update_fields = [];
                foreach ($vehicle_data as $key => $value) {
                    if ($value === 'NULL') {
                        $update_fields[] = "$key = NULL";
                    } else {
                        $update_fields[] = "$key = '$value'";
                    }
                }
                $update_fields[] = "updated_at = NOW()";
                
                $sql = "UPDATE vehicles SET " . implode(', ', $update_fields) . " WHERE id = $vehicle_id";
                query($sql);
                $message = "Vehicle updated successfully!";
            } else {
                // Insert new vehicle
                $fields = [];
                $values = [];
                
                foreach ($vehicle_data as $key => $value) {
                    $fields[] = $key;
                    if ($value === 'NULL') {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'$value'";
                    }
                }
                
                $fields_str = implode(', ', $fields);
                $values_str = implode(', ', $values);
                
                $sql = "INSERT INTO vehicles ($fields_str, created_at) VALUES ($values_str, NOW())";
                query($sql);
                $vehicle_id = lastInsertId();
                $message = "Vehicle created successfully!";
            }
            
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($action == 'delete' && $vehicle_id > 0) {
        try {
            // Check if vehicle has any expenses before deleting
            $expense_count = fetchOne("SELECT COUNT(*) as count FROM expenses WHERE vehicle_id = $vehicle_id");
            
            if ($expense_count['count'] > 0) {
                $message = "Cannot delete vehicle. There are " . $expense_count['count'] . " expenses associated with this vehicle.";
                $message_type = 'error';
            } else {
                $sql = "DELETE FROM vehicles WHERE id = $vehicle_id";
                query($sql);
                $message = "Vehicle deleted successfully!";
                $message_type = 'success';
                header("Location: manage_vehicles.php");
                exit();
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get vehicle details if editing
$vehicle = $vehicle_id > 0 ? fetchOne("SELECT * FROM vehicles WHERE id = $vehicle_id") : null;
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo $action == 'add' ? 'Add New Vehicle' : ($action == 'edit' ? 'Edit Vehicle' : 'Vehicles'); ?></h1>
                <p class="text-muted mb-0">Manage support trucks and other vehicles</p>
            </div>
            <div>
                <?php if ($action == 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add Vehicle
                    </a>
                <?php else: ?>
                    <a href="manage_vehicles.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back to List
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($action == 'list'): ?>
        <!-- Vehicle List with Filters -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter Vehicles</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                               placeholder="Vehicle no, make, model...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Vehicle Type</label>
                        <select name="vehicle_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="Support Truck" <?php echo isset($_GET['vehicle_type']) && $_GET['vehicle_type'] == 'Support Truck' ? 'selected' : ''; ?>>Support Truck</option>
                            <option value="Service Vehicle" <?php echo isset($_GET['vehicle_type']) && $_GET['vehicle_type'] == 'Service Vehicle' ? 'selected' : ''; ?>> Test Pumping Truck</option>
                            <option value="Staff Car" <?php echo isset($_GET['vehicle_type']) && $_GET['vehicle_type'] == 'Staff Car' ? 'selected' : ''; ?>>Staff Car</option>
                            <option value="Equipment Transport" <?php echo isset($_GET['vehicle_type']) && $_GET['vehicle_type'] == 'Equipment Transport' ? 'selected' : ''; ?>>Equipment Transport</option>
                            <option value="Other" <?php echo isset($_GET['vehicle_type']) && $_GET['vehicle_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Vehicles Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Vehicles List</h5>
                <div class="btn-group">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="export_vehicles.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-excel me-1"></i>Export
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php 
                // Build query based on filters
                $where_conditions = [];
                
                if (isset($_GET['search']) && !empty($_GET['search'])) {
                    $search = escape($_GET['search']);
                    $where_conditions[] = "(vehicle_no LIKE '%$search%' OR 
                                          make LIKE '%$search%' OR 
                                          model LIKE '%$search%' OR 
                                          assigned_to LIKE '%$search%')";
                }
                
                if (isset($_GET['vehicle_type']) && !empty($_GET['vehicle_type'])) {
                    $vehicle_type = escape($_GET['vehicle_type']);
                    $where_conditions[] = "vehicle_type = '$vehicle_type'";
                }
                
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $status = escape($_GET['status']);
                    $where_conditions[] = "status = '$status'";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
                }
                
                $vehicles = fetchAll("SELECT * FROM vehicles $where_clause ORDER BY vehicle_type, vehicle_no");
                ?>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Showing <?php echo count($vehicles); ?> vehicles
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="vehiclesTable">
                        <thead>
                            <tr>
                                <th>Vehicle No</th>
                                <th>Type</th>
                                <th>Make & Model</th>
                                <th>Year</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicles as $veh): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($veh['vehicle_no']); ?></strong>
                                    <?php if (!empty($veh['notes'])): ?>
                                        <br><small class="text-muted" title="<?php echo htmlspecialchars($veh['notes']); ?>">
                                            <i class="bi bi-info-circle"></i> Has notes
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo htmlspecialchars($veh['vehicle_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $make_model = [];
                                    if (!empty($veh['make'])) $make_model[] = $veh['make'];
                                    if (!empty($veh['model'])) $make_model[] = $veh['model'];
                                    echo !empty($make_model) ? htmlspecialchars(implode(' ', $make_model)) : 'N/A';
                                    ?>
                                </td>
                                <td><?php echo !empty($veh['year']) ? $veh['year'] : 'N/A'; ?></td>
                                <td><?php echo !empty($veh['assigned_to']) ? htmlspecialchars($veh['assigned_to']) : 'Not assigned'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $veh['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($veh['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $veh['id']; ?>" class="btn btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="view_vehicle.php?id=<?php echo $veh['id']; ?>" class="btn btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $veh['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this vehicle?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($action == 'add' || $action == 'edit'): ?>
        <!-- Add/Edit Vehicle Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo $action == 'add' ? 'Add New Vehicle' : 'Edit Vehicle'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save&id=<?php echo $vehicle_id; ?>">
                            <div class="row g-3">
                                <!-- Vehicle Identification -->
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle Number *</label>
                                    <input type="text" name="vehicle_no" class="form-control" 
                                           value="<?php echo $vehicle ? htmlspecialchars($vehicle['vehicle_no']) : ''; ?>" 
                                           placeholder="e.g., KBX 123A" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle Type *</label>
                                    <select name="vehicle_type" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <option value="Support Truck" <?php echo ($vehicle && $vehicle['vehicle_type'] == 'Support Truck') ? 'selected' : ''; ?>>Support Truck</option>
                                        <option value="Service Vehicle" <?php echo ($vehicle && $vehicle['vehicle_type'] == 'Service Vehicle') ? 'selected' : ''; ?>>Service Vehicle</option>
                                        <option value="Staff Car" <?php echo ($vehicle && $vehicle['vehicle_type'] == 'Staff Car') ? 'selected' : ''; ?>>Staff Car</option>
                                        <option value="Equipment Transport" <?php echo ($vehicle && $vehicle['vehicle_type'] == 'Equipment Transport') ? 'selected' : ''; ?>>Equipment Transport</option>
                                        <option value="Other" <?php echo ($vehicle && $vehicle['vehicle_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <!-- Vehicle Details -->
                                <div class="col-md-4">
                                    <label class="form-label">Make</label>
                                    <input type="text" name="make" class="form-control" 
                                           value="<?php echo $vehicle ? htmlspecialchars($vehicle['make']) : ''; ?>" 
                                           placeholder="e.g., Toyota, Isuzu">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="model" class="form-control" 
                                           value="<?php echo $vehicle ? htmlspecialchars($vehicle['model']) : ''; ?>" 
                                           placeholder="e.g., Hilux, NKR">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Year</label>
                                    <input type="number" name="year" class="form-control" 
                                           value="<?php echo $vehicle ? $vehicle['year'] : ''; ?>" 
                                           min="1900" max="<?php echo date('Y'); ?>"
                                           placeholder="e.g., 2020">
                                </div>
                                
                                <!-- Assignment -->
                                <div class="col-md-6">
                                    <label class="form-label">Assigned To</label>
                                    <input type="text" name="assigned_to" class="form-control" 
                                           value="<?php echo $vehicle ? htmlspecialchars($vehicle['assigned_to']) : ''; ?>" 
                                           placeholder="e.g., John Doe, Site Manager">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo (!$vehicle || $vehicle['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($vehicle && $vehicle['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"><?php echo $vehicle ? htmlspecialchars($vehicle['notes']) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="manage_vehicles.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $action == 'add' ? 'Add Vehicle' : 'Update Vehicle'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Vehicle Statistics</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($vehicle_id > 0): ?>
                            <?php 
                            // Get vehicle statistics
                            $expense_count = fetchOne("SELECT COUNT(*) as count FROM expenses WHERE vehicle_id = $vehicle_id");
                            $total_spent = fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE vehicle_id = $vehicle_id AND status != 'cancelled'");
                            $recent_expenses = fetchAll("SELECT e.expense_code, e.amount, e.expense_date, p.project_code 
                                                        FROM expenses e
                                                        JOIN projects p ON e.project_id = p.id
                                                        WHERE e.vehicle_id = $vehicle_id
                                                        ORDER BY e.expense_date DESC LIMIT 3");
                            ?>
                            <div class="mb-3">
                                <h6><?php echo $vehicle ? htmlspecialchars($vehicle['vehicle_no']) : 'New Vehicle'; ?></h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total Expenses:</span>
                                    <span class="fw-bold"><?php echo $expense_count['count']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Total Spent:</span>
                                    <span class="fw-bold text-primary"><?php echo formatCurrency($total_spent['total']); ?></span>
                                </div>
                                
                                <?php if ($recent_expenses): ?>
                                <h6 class="mt-4 mb-2">Recent Expenses</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_expenses as $exp): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <small><?php echo htmlspecialchars($exp['expense_code']); ?></small><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($exp['project_code']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <small class="fw-bold"><?php echo formatCurrency($exp['amount']); ?></small><br>
                                            <small class="text-muted"><?php echo date('d/m/y', strtotime($exp['expense_date'])); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Save the vehicle to see statistics</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Vehicles -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Vehicles</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_vehicles = fetchAll("SELECT * FROM vehicles ORDER BY created_at DESC LIMIT 5");
                            foreach ($recent_vehicles as $recent): ?>
                            <a href="?action=edit&id=<?php echo $recent['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($recent['vehicle_no']); ?></h6>
                                    <span class="badge bg-<?php echo $recent['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($recent['status']); ?>
                                    </span>
                                </div>
                                <p class="mb-1 small"><?php echo htmlspecialchars($recent['vehicle_type']); ?></p>
                                <?php if (!empty($recent['make']) || !empty($recent['model'])): ?>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(trim($recent['make'] . ' ' . $recent['model'])); ?>
                                    </small>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
    
    .form-label {
        font-weight: 500;
        color: #5a5c69;
        margin-bottom: 0.5rem;
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable for vehicles table
    if ($('#vehiclesTable').length) {
        $('#vehiclesTable').DataTable({
            "pageLength": 50,
            "order": [[0, 'asc']]
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>