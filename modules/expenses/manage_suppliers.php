<?php
// manage_suppliers.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'save') {
        try {
            $supplier_data = [
                'supplier_name' => escape($_POST['supplier_name']),
                'contact_person' => escape($_POST['contact_person']),
                'phone' => escape($_POST['phone']),
                'email' => escape($_POST['email']),
                'address' => escape($_POST['address']),
                'tax_id' => escape($_POST['tax_id']),
                'notes' => escape($_POST['notes']),
                'status' => escape($_POST['status'])
            ];
            
            if ($supplier_id > 0) {
                // Update existing supplier
                $update_fields = [];
                foreach ($supplier_data as $key => $value) {
                    $update_fields[] = "$key = '$value'";
                }
                $update_fields[] = "updated_at = NOW()";
                
                $sql = "UPDATE suppliers SET " . implode(', ', $update_fields) . " WHERE id = $supplier_id";
                query($sql);
                $message = "Supplier updated successfully!";
            } else {
                // Insert new supplier
                $fields = array_keys($supplier_data);
                $values = array_values($supplier_data);
                
                $fields_str = implode(', ', $fields);
                $values_str = "'" . implode("', '", $values) . "'";
                
                $sql = "INSERT INTO suppliers ($fields_str, created_at) VALUES ($values_str, NOW())";
                query($sql);
                $supplier_id = lastInsertId();
                $message = "Supplier created successfully!";
            }
            
            $message_type = 'success';
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } elseif ($action == 'delete' && $supplier_id > 0) {
        try {
            // Check if supplier has any expenses before deleting
            $expense_count = fetchOne("SELECT COUNT(*) as count FROM expenses WHERE supplier_id = $supplier_id");
            
            if ($expense_count['count'] > 0) {
                $message = "Cannot delete supplier. There are " . $expense_count['count'] . " expenses associated with this supplier.";
                $message_type = 'error';
            } else {
                $sql = "DELETE FROM suppliers WHERE id = $supplier_id";
                query($sql);
                $message = "Supplier deleted successfully!";
                $message_type = 'success';
                header("Location: manage_suppliers.php");
                exit();
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get supplier details if editing
$supplier = $supplier_id > 0 ? fetchOne("SELECT * FROM suppliers WHERE id = $supplier_id") : null;
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo $action == 'add' ? 'Add New Supplier' : ($action == 'edit' ? 'Edit Supplier' : 'Suppliers'); ?></h1>
                <p class="text-muted mb-0">Manage suppliers and vendor information</p>
            </div>
            <div>
                <?php if ($action == 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add Supplier
                    </a>
                <?php else: ?>
                    <a href="manage_suppliers.php" class="btn btn-secondary">
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
        <!-- Supplier List with Filters -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter Suppliers</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                               placeholder="Supplier name, contact person, phone...">
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
        
        <!-- Suppliers Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Suppliers List</h5>
                <div class="btn-group">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="export_suppliers.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">
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
                    $where_conditions[] = "(supplier_name LIKE '%$search%' OR 
                                          contact_person LIKE '%$search%' OR 
                                          phone LIKE '%$search%' OR 
                                          email LIKE '%$search%')";
                }
                
                // if (isset($_GET['supplier_type']) && !empty($_GET['supplier_type'])) {
                //     $supplier_type = escape($_GET['supplier_type']);
                //     $where_conditions[] = "supplier_type = '$supplier_type'";
                // }
                
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $status = escape($_GET['status']);
                    $where_conditions[] = "status = '$status'";
                }
                
                $where_clause = '';
                if (!empty($where_conditions)) {
                    $where_clause = "WHERE " . implode(' AND ', $where_conditions);
                }
                
                $suppliers = fetchAll("SELECT * FROM suppliers $where_clause ORDER BY supplier_name");
                ?>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Showing <?php echo count($suppliers); ?> suppliers
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="suppliersTable">
                        <thead>
                            <tr>
                                <th>Supplier Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Tax ID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($sup['supplier_name']); ?></strong>
                                    <?php if (!empty($sup['address'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($sup['address']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($sup['contact_person']); ?></td>
                                <td><?php echo htmlspecialchars($sup['phone']); ?></td>
                                <td><?php echo htmlspecialchars($sup['email']); ?></td>
                                <td><?php echo !empty($sup['tax_id']) ? htmlspecialchars($sup['tax_id']) : 'N/A'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $sup['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($sup['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?action=edit&id=<?php echo $sup['id']; ?>" class="btn btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="view_supplier.php?id=<?php echo $sup['id']; ?>" class="btn btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="?action=delete&id=<?php echo $sup['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this supplier?')">
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
        <!-- Add/Edit Supplier Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo $action == 'add' ? 'Add New Supplier' : 'Edit Supplier'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save&id=<?php echo $supplier_id; ?>">
                            <div class="row g-3">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <label class="form-label">Supplier Name *</label>
                                    <input type="text" name="supplier_name" class="form-control" 
                                           value="<?php echo $supplier ? htmlspecialchars($supplier['supplier_name']) : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control" 
                                           value="<?php echo $supplier ? htmlspecialchars($supplier['contact_person']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo $supplier ? htmlspecialchars($supplier['phone']) : ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo $supplier ? htmlspecialchars($supplier['email']) : ''; ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo $supplier ? htmlspecialchars($supplier['address']) : ''; ?></textarea>
                                </div>
                                
                            
                                
                                
                               <div class="col-md-6">
                                    <label class="form-label">Tax ID</label>
                                    <input type="text" name="tax_id" class="form-control" 
                                        value="<?php echo $supplier ? htmlspecialchars($supplier['tax_id']) : ''; ?>"
                                        placeholder="Tax Identification Number">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active" <?php echo (!$supplier || $supplier['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($supplier && $supplier['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"><?php echo $supplier ? htmlspecialchars($supplier['notes']) : ''; ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="manage_suppliers.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo $action == 'add' ? 'Add Supplier' : 'Update Supplier'; ?>
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
                        <h6 class="card-title mb-0">Supplier Statistics</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($supplier_id > 0): ?>
                            <?php 
                            // Get supplier statistics
                            $expense_count = fetchOne("SELECT COUNT(*) as count FROM expenses WHERE supplier_id = $supplier_id");
                            $total_spent = fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE supplier_id = $supplier_id AND status != 'cancelled'");
                            $recent_expenses = fetchAll("SELECT e.expense_code, e.amount, e.expense_date, p.project_code 
                                                        FROM expenses e
                                                        JOIN projects p ON e.project_id = p.id
                                                        WHERE e.supplier_id = $supplier_id
                                                        ORDER BY e.expense_date DESC LIMIT 3");
                            ?>
                            <div class="mb-3">
                                <h6><?php echo $supplier ? htmlspecialchars($supplier['supplier_name']) : 'New Supplier'; ?></h6>
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
                            <p class="text-muted">Save the supplier to see statistics</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Suppliers -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Suppliers</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_suppliers = fetchAll("SELECT * FROM suppliers ORDER BY created_at DESC LIMIT 5");
                            foreach ($recent_suppliers as $recent): ?>
                            <a href="?action=edit&id=<?php echo $recent['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($recent['supplier_name']); ?></h6>
                                    <span class="badge bg-<?php echo $recent['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($recent['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($recent['contact_person'])): ?>
                                    <small class="text-muted">Contact: <?php echo htmlspecialchars($recent['contact_person']); ?></small>
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
    // Initialize DataTable for suppliers table
    if ($('#suppliersTable').length) {
        $('#suppliersTable').DataTable({
            "pageLength": 50,
            "order": [[0, 'asc']]
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>