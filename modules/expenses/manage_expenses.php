<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$expense_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'save') {
        try {
            // Determine asset type and IDs
            $asset_type = !empty($_POST['asset_type']) ? escape($_POST['asset_type']) : NULL;
            $vehicle_id = NULL;
            $rig_id = NULL;
            
            if ($asset_type == 'vehicle') {
                $vehicle_id = !empty($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : NULL;
            } elseif ($asset_type == 'rig') {
                $rig_id = !empty($_POST['rig_id']) ? intval($_POST['rig_id']) : NULL;
            }
            
            // Save main expense - SIMPLER FIX
            $expense_data = [
                'expense_code' => escape($_POST['expense_code']),
                'project_id' => intval($_POST['project_id']),
                'supplier_id' => !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 'NULL',
                'expense_type_id' => intval($_POST['expense_type_id']),
                'ref_number' => escape($_POST['ref_number']),
                'expense_date' => escape($_POST['expense_date']),
                'vehicle_id' => $vehicle_id === NULL ? 'NULL' : $vehicle_id,
                'rig_id' => $rig_id === NULL ? 'NULL' : $rig_id,
                'asset_type' => $asset_type === NULL ? 'NULL' : $asset_type, // CHANGED: Don't add quotes here
                'amount' => floatval($_POST['amount']),
                'quantity' => floatval($_POST['quantity']),
                'unit_price' => floatval($_POST['unit_price']),
                'notes' => escape($_POST['notes']),
                'status' => escape($_POST['status']),
                'created_by' => $_SESSION['user_id']
            ];
            
            if ($expense_id > 0) {
                // Update existing expense
                $update_fields = [];
                foreach ($expense_data as $key => $value) {
                    if ($value === 'NULL') {
                        $update_fields[] = "$key = NULL";
                    } else {
                        // Add quotes for string values, not for numbers
                        if ($key === 'project_id' || $key === 'supplier_id' || $key === 'expense_type_id' || 
                            $key === 'vehicle_id' || $key === 'rig_id' || $key === 'amount' || 
                            $key === 'quantity' || $key === 'unit_price' || $key === 'created_by') {
                            $update_fields[] = "$key = $value";
                        } else {
                            $update_fields[] = "$key = '$value'";
                        }
                    }
                }
                $update_fields[] = "updated_at = NOW()";
                
                $sql = "UPDATE expenses SET " . implode(', ', $update_fields) . " WHERE id = $expense_id";
                query($sql);
                $message = "Expense updated successfully!";
            } else {
                // Insert new expense
                $fields = [];
                $values = [];
                
                foreach ($expense_data as $key => $value) {
                    $fields[] = $key;
                    if ($value === 'NULL') {
                        $values[] = 'NULL';
                    } else {
                        // Add quotes for string values, not for numbers
                        if ($key === 'project_id' || $key === 'supplier_id' || $key === 'expense_type_id' || 
                            $key === 'vehicle_id' || $key === 'rig_id' || $key === 'amount' || 
                            $key === 'quantity' || $key === 'unit_price' || $key === 'created_by') {
                            $values[] = $value;
                        } else {
                            $values[] = "'$value'";
                        }
                    }
                }
                
                $fields_str = implode(', ', $fields);
                $values_str = implode(', ', $values);
                
                $sql = "INSERT INTO expenses ($fields_str, created_at) VALUES ($values_str, NOW())";
                query($sql);
                $expense_id = lastInsertId();
                $message = "Expense created successfully!";
            }
            
            // Handle receipt upload
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
                saveExpenseReceipt($expense_id, $_FILES['receipt']);
            }
            
            // Handle extra expenses
            if (isset($_POST['extra_description']) && is_array($_POST['extra_description'])) {
                // Delete existing extra expenses
                query("DELETE FROM extra_expenses WHERE expense_id = $expense_id");
                
                for ($i = 0; $i < count($_POST['extra_description']); $i++) {
                    $description = escape($_POST['extra_description'][$i]);
                    $amount = floatval($_POST['extra_amount'][$i]);
                    
                    if (!empty($description) && $amount > 0) {
                        $extra_sql = "INSERT INTO extra_expenses (expense_id, description, amount) 
                                     VALUES ($expense_id, '$description', $amount)";
                        query($extra_sql);
                    }
                }
            }
            
            $message_type = 'success';
            
            // Redirect if from project
            if ($project_id > 0) {
                header("Location: ../projects/view_projects.php?id=$project_id&tab=expenses");
                exit();
            }
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get expense details if editing - Join with vehicle and rig data
$expense = null;
if ($expense_id > 0) {
    $sql = "SELECT e.*, 
                   v.vehicle_no, v.vehicle_type, v.make, v.model, v.year,
                   r.rig_name, r.rig_code
            FROM expenses e
            LEFT JOIN vehicles v ON e.vehicle_id = v.id
            LEFT JOIN rigs r ON e.rig_id = r.id
            WHERE e.id = $expense_id";
    $expense = fetchOne($sql);
}
$extra_expenses = $expense_id > 0 ? fetchAll("SELECT * FROM extra_expenses WHERE expense_id = $expense_id") : [];
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo $action == 'add' ? 'Add New Expense' : ($action == 'edit' ? 'Edit Expense' : 'Expenses'); ?></h1>
                <p class="text-muted mb-0">Manage project expenses and track costs</p>
            </div>
            <div>
                <?php if ($action == 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add Expense
                    </a>
                <?php else: ?>
                    <a href="manage_expenses.php" class="btn btn-secondary">
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
        <!-- Expense List with Filters -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter Expenses</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Project Rig</label>  <!-- Changed label -->
                        <select name="project_rig_id" class="form-select">  <!-- Changed name -->
                            <option value="">All Project Rigs</option>
                            <?php 
                            $rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
                            foreach ($rigs as $rig): ?>
                            <option value="<?php echo $rig['id']; ?>"
                                <?php echo isset($_GET['project_rig_id']) && $_GET['project_rig_id'] == $rig['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($rig['rig_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                    <label class="form-label">Expense Rig</label>  <!-- New filter -->
                    <select name="expense_rig_id" class="form-select">
                        <option value="">All Expense Rigs</option>
                        <?php 
                        $rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
                        foreach ($rigs as $rig): ?>
                        <option value="<?php echo $rig['id']; ?>"
                            <?php echo isset($_GET['expense_rig_id']) && $_GET['expense_rig_id'] == $rig['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($rig['rig_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Asset Type</label>
                        <select name="asset_type" class="form-select">
                            <option value="">All Types</option>
                            <option value="vehicle" <?php echo isset($_GET['asset_type']) && $_GET['asset_type'] == 'vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                            <option value="rig" <?php echo isset($_GET['asset_type']) && $_GET['asset_type'] == 'rig' ? 'selected' : ''; ?>>Rig</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Expense Type</label>
                        <select name="expense_type_id" class="form-select">
                            <option value="">All Types</option>
                            <?php 
                            $expense_types = getAllExpenseTypes();
                            foreach ($expense_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>"
                                <?php echo isset($_GET['expense_type_id']) && $_GET['expense_type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['category'] . ' - ' . $type['expense_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select select2">
                            <option value="">All Suppliers</option>
                            <?php 
                            $suppliers = getAllSuppliers();
                            foreach ($suppliers as $supp): ?>
                            <option value="<?php echo $supp['id']; ?>"
                                <?php echo isset($_GET['supplier_id']) && $_GET['supplier_id'] == $supp['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($supp['supplier_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo isset($_GET['status']) && $_GET['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="paid" <?php echo isset($_GET['status']) && $_GET['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Expenses Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Expenses List</h5>
                <div class="btn-group">
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="export_expenses.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-excel me-1"></i>Export
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php 
                // Clean up empty filters
                $filters = array_filter($_GET, function($value) {
                    return $value !== '' && $value !== null;
                });

                // Ensure we have safe defaults
                if (!isset($filters['status'])) {
                    $filters['status'] = ''; // Show all statuses by default
                }

                $expenses = generateExpenseReport($filters);
                $total_amount = array_sum(array_column($expenses, 'amount'));
                ?>
                
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Showing <?php echo count($expenses); ?> expenses totaling <?php echo formatCurrency($total_amount); ?>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="expensesTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Supplier</th>
                                <th>Asset</th>
                                <th>Ref #</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Receipt</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($expenses as $exp): 
        // Safely get asset_type with null coalescing
        $asset_type = $exp['asset_type'] ?? null;
        $vehicle_id = $exp['vehicle_id'] ?? null;
        $rig_id = $exp['rig_id'] ?? null;
        $vehicle_no = $exp['vehicle_no'] ?? null;
        $rig_name = $exp['rig_name'] ?? null;
    ?>
    <tr>
        <td><span class="badge bg-secondary"><?php echo $exp['expense_code']; ?></span></td>
        <td><?php echo date('d/m/Y', strtotime($exp['expense_date'])); ?></td>
        <td>
            <a href="../projects/project_details.php?id=<?php echo $exp['project_id']; ?>" class="text-decoration-none">
                <?php echo htmlspecialchars($exp['project_code'] . ' - ' . $exp['project_name']); ?>
            </a>
        </td>
        <td>
            <?php 
            $customer_display = 'N/A';
            if (!empty($exp['first_name']) || !empty($exp['last_name'])) {
                $customer_display = trim(htmlspecialchars($exp['first_name'] . ' ' . $exp['last_name']));
                if (!empty($exp['company_name'])) {
                    $customer_display .= ' (' . htmlspecialchars($exp['company_name']) . ')';
                }
            } elseif (!empty($exp['company_name'])) {
                $customer_display = htmlspecialchars($exp['company_name']);
            }
            echo $customer_display;
            ?>
        </td>
        <td>
            <span class="badge bg-info" title="<?php echo htmlspecialchars($exp['category']); ?>">
                <?php echo htmlspecialchars($exp['expense_name']); ?>
            </span>
        </td>
        <td><?php echo htmlspecialchars($exp['supplier_name']); ?></td>
        <td>
            <?php if ($asset_type == 'vehicle' && !empty($vehicle_id)): ?>
                <span class="badge bg-info">
                    <i class="bi bi-truck"></i> 
                    <?php echo htmlspecialchars($vehicle_no ?? 'Vehicle'); ?>
                </span>
            <?php elseif ($asset_type == 'rig' && !empty($rig_id)): ?>
                <span class="badge bg-warning">
                    <i class="bi bi-gear"></i> 
                    <?php echo htmlspecialchars($rig_name ?? 'Rig'); ?>
                </span>
            <?php else: ?>
                <span class="text-muted small">None</span>
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($exp['ref_number']); ?></td>
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
            <?php if ($exp['receipt_path']): ?>
                <a href="<?php echo ROOT_PATH . $exp['receipt_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-receipt"></i>
                </a>
            <?php else: ?>
                <span class="text-muted">None</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="btn-group btn-group-sm">
                <a href="?action=edit&id=<?php echo $exp['id']; ?>" class="btn btn-outline-warning">
                    <i class="bi bi-pencil"></i>
                </a>
                <a href="view_expense.php?id=<?php echo $exp['id']; ?>" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i>
                </a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="8" class="fw-bold">TOTAL</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($total_amount); ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        
    <?php elseif ($action == 'add' || $action == 'edit'): ?>
        <!-- Add/Edit Expense Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo $action == 'add' ? 'Add New Expense' : 'Edit Expense'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save&id=<?php echo $expense_id; ?>" enctype="multipart/form-data">
                            <div class="row g-3">
                                <!-- Expense Basic Info -->
                                <div class="col-md-6">
                                    <label class="form-label">Expense Code *</label>
                                    <input type="text" name="expense_code" class="form-control" 
                                           value="<?php echo $expense ? $expense['expense_code'] : 'EXP-' . date('Ymd-His'); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Status *</label>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?php echo ($expense && $expense['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo ($expense && $expense['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                        <option value="paid" <?php echo ($expense && $expense['status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Project *</label>
                                    <select name="project_id" class="form-select select2" required>
                                        <option value="">Select Project</option>
                                        <?php 
                                        $projects = fetchAll("SELECT p.*, 
                                                COALESCE(c.first_name, '') as first_name, 
                                                COALESCE(c.last_name, '') as last_name, 
                                                COALESCE(c.company_name, '') as company_name 
                                                FROM projects p 
                                                LEFT JOIN customers c ON p.customer_id = c.id
                                                ORDER BY p.project_name");
                                        foreach ($projects as $proj): 
                                            $display = $proj['project_code'] . ' - ' . $proj['project_name'];
                                            
                                            if (!empty($proj['first_name']) || !empty($proj['last_name'])) {
                                                $customer_name = trim($proj['first_name'] . ' ' . $proj['last_name']);
                                                $display .= ' (' . $customer_name . ')';
                                            } elseif (!empty($proj['company_name'])) {
                                                $display .= ' (' . $proj['company_name'] . ')';
                                            }
                                        ?>
                                        <option value="<?php echo $proj['id']; ?>" 
                                            <?php echo (($expense && $expense['project_id'] == $proj['id']) || $project_id == $proj['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($display); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Expense Date *</label>
                                    <input type="date" name="expense_date" class="form-control" 
                                           value="<?php echo $expense ? $expense['expense_date'] : date('Y-m-d'); ?>" required>
                                </div>
                                
                                <!-- Supplier and Type -->
                                <div class="col-md-6">
                                    <label class="form-label">Supplier</label>
                                    <select name="supplier_id" class="form-select select2">
                                        <option value="">Select Supplier</option>
                                        <?php 
                                        $suppliers = getAllSuppliers();
                                        foreach ($suppliers as $supp): ?>
                                        <option value="<?php echo $supp['id']; ?>"
                                            <?php echo ($expense && $expense['supplier_id'] == $supp['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($supp['supplier_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <a href="manage_suppliers.php?action=add" target="_blank" class="small">
                                            <i class="bi bi-plus-circle"></i> Add new supplier
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Expense Type *</label>
                                    <select name="expense_type_id" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <?php 
                                        $expense_types = getAllExpenseTypes();
                                        $grouped_types = [];
                                        foreach ($expense_types as $type) {
                                            $grouped_types[$type['category']][] = $type;
                                        }
                                        
                                        foreach ($grouped_types as $category => $types): ?>
                                        <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                            <?php foreach ($types as $type): ?>
                                            <option value="<?php echo $type['id']; ?>"
                                                <?php echo ($expense && $expense['expense_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['expense_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Vehicle/Rig Selection -->
                                <div class="col-md-6">
                                    <label class="form-label">Vehicle/Rig</label>
                                    <div class="row g-2">
                                        <div class="col-4">
                                            <select name="asset_type" class="form-select" id="assetType">
                                                <option value="">Select Type</option>
                                                <option value="vehicle" <?php echo ($expense && $expense['asset_type'] == 'vehicle') ? 'selected' : ''; ?>>Vehicle</option>
                                                <option value="rig" <?php echo ($expense && $expense['asset_type'] == 'rig') ? 'selected' : ''; ?>>Rig</option>
                                            </select>
                                        </div>
                                        <div class="col-8">
                                            <!-- Vehicle Select -->
                                            <select name="vehicle_id" class="form-select select2" id="vehicleSelect" 
                                                    data-placeholder="Select vehicle" 
                                                    <?php echo (!$expense || $expense['asset_type'] != 'vehicle') ? 'disabled' : ''; ?>>
                                                <option value="">-- Select Vehicle --</option>
                                                <?php 
                                                $vehicles = fetchAll("SELECT * FROM vehicles WHERE status = 'active' ORDER BY vehicle_type, vehicle_no");
                                                foreach ($vehicles as $vehicle): 
                                                    $vehicle_display = $vehicle['vehicle_no'] . ' - ' . $vehicle['vehicle_type'];
                                                    if (!empty($vehicle['make'])) $vehicle_display .= ' (' . $vehicle['make'];
                                                    if (!empty($vehicle['model'])) $vehicle_display .= ' ' . $vehicle['model'];
                                                    if (!empty($vehicle['year'])) $vehicle_display .= ' ' . $vehicle['year'];
                                                    if (!empty($vehicle['make'])) $vehicle_display .= ')';
                                                ?>
                                                <option value="<?php echo $vehicle['id']; ?>"
                                                    <?php echo ($expense && $expense['asset_type'] == 'vehicle' && $expense['vehicle_id'] == $vehicle['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($vehicle_display); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <!-- Rig Select -->
                                            <select name="rig_id" class="form-select select2" id="rigSelect" 
                                                    data-placeholder="Select rig" 
                                                    <?php echo (!$expense || $expense['asset_type'] != 'rig') ? 'disabled' : ''; ?>>
                                                <option value="">-- Select Rig --</option>
                                                <?php 
                                                $rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
                                                foreach ($rigs as $rig): ?>
                                                <option value="<?php echo $rig['id']; ?>"
                                                    <?php echo ($expense && $expense['asset_type'] == 'rig' && $expense['rig_id'] == $rig['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($rig['rig_name'] . ' (' . $rig['rig_code'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        <a href="manage_vehicles.php?action=add" target="_blank" class="small me-2">
                                            <i class="bi bi-plus-circle"></i> Add vehicle
                                        </a>
                                        <a href="../rigs/add_rig.php?action=add" target="_blank" class="small">
                                            <i class="bi bi-plus-circle"></i> Add rig
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" name="ref_number" class="form-control" 
                                           value="<?php echo $expense ? $expense['ref_number'] : ''; ?>" 
                                           placeholder="Invoice/Receipt number">
                                </div>
                                
                                <!-- Amount Details -->
                                <div class="col-md-4">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="quantity" class="form-control" 
                                           value="<?php echo $expense ? $expense['quantity'] : 1; ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Unit Price (Ksh)</label>
                                    <input type="number" name="unit_price" class="form-control" 
                                           value="<?php echo $expense ? $expense['unit_price'] : ''; ?>" 
                                           step="0.01" min="0">
                                    <div class="form-text small">Leave blank to calculate from total</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Total Amount (Ksh) *</label>
                                    <input type="number" name="amount" class="form-control" 
                                           value="<?php echo $expense ? $expense['amount'] : ''; ?>" 
                                           step="0.01" min="0" required>
                                </div>
                                
                                <!-- Extra Expenses -->
                                <div class="col-12">
                                    <h6 class="mt-4 mb-3">Extra Expenses (Hired Vehicle etc.)</h6>
                                    <div id="extra-expenses-container">
                                        <?php if (!empty($extra_expenses)): ?>
                                            <?php foreach ($extra_expenses as $index => $extra): ?>
                                            <div class="row g-3 mb-3 align-items-end extra-expense-item">
                                                <div class="col-md-7">
                                                    <label class="form-label">Description</label>
                                                    <input type="text" name="extra_description[]" class="form-control" 
                                                           value="<?php echo htmlspecialchars($extra['description']); ?>" 
                                                           placeholder="e.g., Hired truck repair, Additional labor">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Amount (Ksh)</label>
                                                    <input type="number" name="extra_amount[]" class="form-control" 
                                                           value="<?php echo $extra['amount']; ?>" step="0.01" min="0">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeExtraExpense(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="row g-3 mb-3 align-items-end extra-expense-item">
                                                <div class="col-md-7">
                                                    <label class="form-label">Description</label>
                                                    <input type="text" name="extra_description[]" class="form-control" 
                                                           placeholder="e.g., Hired truck repair, Additional labor">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Amount (Ksh)</label>
                                                    <input type="number" name="extra_amount[]" class="form-control" 
                                                           step="0.01" min="0">
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeExtraExpense(this)" disabled>
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addExtraExpense()">
                                        <i class="bi bi-plus-circle me-1"></i>Add Extra Expense
                                    </button>
                                </div>
                                
                                <!-- Receipt Upload -->
                                <div class="col-md-6">
                                    <label class="form-label">Receipt/Invoice</label>
                                    <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                    <?php if ($expense && $expense['receipt_path']): ?>
                                        <div class="form-text">
                                            <a href="<?php echo ROOT_PATH . $expense['receipt_path']; ?>" target="_blank" class="small">
                                                <i class="bi bi-receipt"></i> View current receipt
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"><?php echo $expense ? $expense['notes'] : ''; ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="manage_expenses.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save Expense</button>
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
                        <h6 class="card-title mb-0">Project Expense Summary</h6>
                    </div>
                    <div class="card-body">
                        <div id="project-stats">
                            <p class="text-muted">Select a project to see statistics</p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Expenses -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Recent Expenses</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_expenses = fetchAll("SELECT e.*, p.project_code, et.expense_name 
                                                        FROM expenses e
                                                        JOIN projects p ON e.project_id = p.id
                                                        JOIN expense_types et ON e.expense_type_id = et.id
                                                        ORDER BY e.created_at DESC LIMIT 5");
                            foreach ($recent_expenses as $recent): ?>
                            <a href="?action=edit&id=<?php echo $recent['id']; ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($recent['expense_code']); ?></h6>
                                    <small><?php echo date('d/m', strtotime($recent['expense_date'])); ?></small>
                                </div>
                                <p class="mb-1 small"><?php echo htmlspecialchars($recent['project_code']); ?> - <?php echo htmlspecialchars($recent['expense_name']); ?></p>
                                <small class="text-muted"><?php echo formatCurrency($recent['amount']); ?></small>
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

/* Hide the inactive select by default */
#rigSelect.select2-container,
#vehicleSelect.select2-container {
    display: none;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: "Select option",
        allowClear: true,
        width: '100%'
    });
    
    // Initialize DataTable for expenses table
    if ($('#expensesTable').length) {
        $('#expensesTable').DataTable({
            "pageLength": 50,
            "order": [[1, 'desc']]
        });
    }
    
    // Asset type selection handler
    $('#assetType').on('change', function() {
        var assetType = $(this).val();
        
        if (assetType === 'vehicle') {
            $('#vehicleSelect').prop('disabled', false).select2({
                placeholder: "Select vehicle",
                allowClear: true,
                width: '100%'
            });
            $('#rigSelect').prop('disabled', true).val('').trigger('change');
            
            // Show vehicle select, hide rig select
            $('#vehicleSelect').next('.select2-container').show();
            $('#rigSelect').next('.select2-container').hide();
        } else if (assetType === 'rig') {
            $('#rigSelect').prop('disabled', false).select2({
                placeholder: "Select rig",
                allowClear: true,
                width: '100%'
            });
            $('#vehicleSelect').prop('disabled', true).val('').trigger('change');
            
            // Show rig select, hide vehicle select
            $('#rigSelect').next('.select2-container').show();
            $('#vehicleSelect').next('.select2-container').hide();
        } else {
            $('#vehicleSelect').prop('disabled', true).val('').trigger('change');
            $('#rigSelect').prop('disabled', true).val('').trigger('change');
            
            // Hide both selects
            $('#vehicleSelect').next('.select2-container').hide();
            $('#rigSelect').next('.select2-container').hide();
        }
    });
    
    // Initialize asset type based on existing data
    $(window).on('load', function() {
        var assetType = $('#assetType').val();
        if (assetType === 'vehicle') {
            $('#vehicleSelect').next('.select2-container').show();
            $('#rigSelect').next('.select2-container').hide();
        } else if (assetType === 'rig') {
            $('#rigSelect').next('.select2-container').show();
            $('#vehicleSelect').next('.select2-container').hide();
        } else {
            $('#vehicleSelect').next('.select2-container').hide();
            $('#rigSelect').next('.select2-container').hide();
        }
    });
    
    // Auto-calculate unit price or total
    $('input[name="quantity"], input[name="unit_price"], input[name="amount"]').on('input', function() {
        var quantity = parseFloat($('input[name="quantity"]').val()) || 1;
        var unitPrice = parseFloat($('input[name="unit_price"]').val()) || 0;
        var total = parseFloat($('input[name="amount"]').val()) || 0;
        
        if (this.name === 'quantity' || this.name === 'unit_price') {
            var calculatedTotal = quantity * unitPrice;
            if (calculatedTotal > 0) {
                $('input[name="amount"]').val(calculatedTotal.toFixed(2));
            }
        } else if (this.name === 'amount' && quantity > 0 && total > 0) {
            var calculatedUnitPrice = total / quantity;
            $('input[name="unit_price"]').val(calculatedUnitPrice.toFixed(2));
        }
    });
    
    // Load project stats when project is selected
    $('select[name="project_id"]').on('change', function() {
        var projectId = $(this).val();
        if (projectId) {
            $.ajax({
                url: 'ajax_get_project_stats.php',
                method: 'POST',
                data: { project_id: projectId },
                success: function(response) {
                    $('#project-stats').html(response);
                }
            });
        } else {
            $('#project-stats').html('<p class="text-muted">Select a project to see statistics</p>');
        }
    });
    
    // Trigger change if project is already selected
    if ($('select[name="project_id"]').val()) {
        $('select[name="project_id"]').trigger('change');
    }
});

// Extra expenses management
let extraExpenseCount = <?php echo count($extra_expenses); ?>;
function addExtraExpense() {
    const container = $('#extra-expenses-container');
    const newItem = $(`
        <div class="row g-3 mb-3 align-items-end extra-expense-item">
            <div class="col-md-7">
                <label class="form-label">Description</label>
                <input type="text" name="extra_description[]" class="form-control" 
                       placeholder="e.g., Hired truck repair, Additional labor">
            </div>
            <div class="col-md-4">
                <label class="form-label">Amount (Ksh)</label>
                <input type="number" name="extra_amount[]" class="form-control" 
                       step="0.01" min="0">
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeExtraExpense(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `);
    container.append(newItem);
    extraExpenseCount++;
    
    // Enable delete button on first item
    if (container.children().length > 1) {
        container.find('.extra-expense-item:first .btn-danger').prop('disabled', false);
    }
}

function removeExtraExpense(button) {
    const row = $(button).closest('.extra-expense-item');
    const container = $('#extra-expenses-container');
    
    if (container.children().length > 1) {
        row.remove();
        extraExpenseCount--;
        
        if (container.children().length === 1) {
            container.find('.btn-danger').prop('disabled', true);
        }
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>