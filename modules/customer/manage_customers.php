<?php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle POST requests FIRST (before any output)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($action == 'save') {
        try {
            $data = [
                'customer_code' => escape($_POST['customer_code']),
                'first_name' => escape($_POST['first_name']),
                'last_name' => escape($_POST['last_name']),
                'company_name' => escape($_POST['company_name']),
                'email' => escape($_POST['email']),
                'phone' => escape($_POST['phone']),
                'phone2' => escape($_POST['phone2']),
                'address' => escape($_POST['address']),
                'city' => escape($_POST['city']),
                'country' => escape($_POST['country']),
                'tax_id' => escape($_POST['tax_id']),
                'notes' => escape($_POST['notes']),
                'status' => escape($_POST['status'])
            ];
            
            if ($customer_id > 0) {
                // Update existing customer
                $update_fields = [];
                foreach ($data as $key => $value) {
                    $update_fields[] = "$key = '$value'";
                }
                $update_fields[] = "updated_at = NOW()";
                
                $sql = "UPDATE customers SET " . implode(', ', $update_fields) . " WHERE id = $customer_id";
                query($sql);
                $message = "Customer updated successfully!";
                
                // Redirect IMMEDIATELY after successful update
                header("Location: manage_customers.php?action=view&id=" . $customer_id . "&message=" . urlencode($message) . "&message_type=success");
                exit();
            } else {
                // Insert new customer
                $fields = implode(', ', array_keys($data));
                $values = "'" . implode("', '", array_values($data)) . "'";
                
                $sql = "INSERT INTO customers ($fields, created_at) VALUES ($values, NOW())";
                query($sql);
                $customer_id = lastInsertId();
                $message = "Customer created successfully!";
                
                // Redirect IMMEDIATELY after successful creation
                header("Location: manage_customers.php?message=" . urlencode($message) . "&message_type=success");
                exit();
            }
            
        } catch (Exception $e) {
            // Store error in session for display
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            
            // Redirect back to form with error
            if ($customer_id > 0) {
                header("Location: manage_customers.php?action=edit&id=" . $customer_id);
            } else {
                header("Location: manage_customers.php?action=add");
            }
            exit();
        }
    }
}

// Now include header.php AFTER potential redirects
require_once ROOT_PATH . '/includes/header.php';

// Check for session messages from redirects
if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['error_message']);
    unset($_SESSION['message_type']);
}
?>
<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Customers</h1>
                <p class="text-muted mb-0">Manage customer information and view their projects</p>
            </div>
            <div>
                <a href="?action=add" class="btn btn-primary">
                    <i class="bi bi-person-plus me-2"></i>Add New Customer
                </a>
            </div>
        </div>
    </div>
    
        <?php 
    // Check for redirect messages from URL parameters
    if (isset($_GET['message'])): 
        $message = urldecode($_GET['message']);
        $message_type = isset($_GET['message_type']) ? $_GET['message_type'] : 'success';
    ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php 
    // Also check for session messages (for backward compatibility - displays form errors)
    elseif (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($action == 'list'): ?>
        <!-- Customer List with Search -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Search & Filter</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by name, company, phone, or email"
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo isset($_GET['status']) && $_GET['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo isset($_GET['status']) && $_GET['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="name_asc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="recent" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                            <option value="projects_desc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'projects_desc' ? 'selected' : ''; ?>>Most Projects</option>
                            <option value="projects_asc" <?php echo isset($_GET['sort']) && $_GET['sort'] == 'projects_asc' ? 'selected' : ''; ?>>Fewest Projects</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-1"></i>Apply
                        </button>
                    </div>
                </form>
                
                <?php if (isset($_GET['search']) || isset($_GET['status'])): ?>
                <div class="mt-3">
                    <a href="manage_customers.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear Filters
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Statistics -->
        <?php
        // Get filtered customers
        $search_where = "WHERE 1=1";
        
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search_term = escape($_GET['search']);
            $search_where .= " AND (
                first_name LIKE '%$search_term%' OR 
                last_name LIKE '%$search_term%' OR 
                company_name LIKE '%$search_term%' OR 
                email LIKE '%$search_term%' OR 
                phone LIKE '%$search_term%' OR 
                customer_code LIKE '%$search_term%'
            )";
        }
        
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $status = escape($_GET['status']);
            $search_where .= " AND status = '$status'";
        }
        
        // Determine sort order
        $order_by = "ORDER BY first_name, last_name";
        if (isset($_GET['sort'])) {
            switch ($_GET['sort']) {
                case 'name_desc':
                    $order_by = "ORDER BY first_name DESC, last_name DESC";
                    break;
                case 'recent':
                    $order_by = "ORDER BY created_at DESC";
                    break;
                case 'projects_desc':
                    $order_by = "ORDER BY project_count DESC";
                    break;
                case 'projects_asc':
                    $order_by = "ORDER BY project_count ASC";
                    break;
            }
        }
        
        // Get customers with project counts
        $sql = "SELECT c.*, 
                (SELECT COUNT(*) FROM projects p WHERE p.customer_id = c.id) as project_count
                FROM customers c 
                $search_where 
                $order_by";
        
        $customers = fetchAll($sql);
        $total_customers = count($customers);
        $active_customers = 0;
        $total_projects = 0;
        
        foreach ($customers as $customer) {
            if ($customer['status'] == 'active') $active_customers++;
            $total_projects += $customer['project_count'];
        }
        ?>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Customers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_customers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Active Customers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_customers; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-person-check fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Total Projects</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_projects; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-folder fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Avg Projects/Customer</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $total_customers > 0 ? number_format($total_projects / $total_customers, 1) : 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customers Table -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Customers List 
                    <?php if (isset($_GET['search'])): ?>
                        <small class="text-muted">(<?php echo $total_customers; ?> results)</small>
                    <?php endif; ?>
                </h5>
                <div class="btn-group">
                    <?php if ($total_customers > 0): ?>
                    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="export_customers.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-file-excel me-1"></i>Export
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if ($total_customers > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="customersTable">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Projects</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo $customer['customer_code']; ?></span></td>
                                    <td>
                                        <a href="?action=view&id=<?php echo $customer['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                                    <td>
                                        <a href="tel:<?php echo $customer['phone']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($customer['phone']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($customer['email']): ?>
                                        <a href="mailto:<?php echo $customer['email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </a>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $customer['project_count'] > 0 ? 'info' : 'secondary'; ?>">
                                            <?php echo $customer['project_count']; ?> projects
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $customer['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="?action=view&id=<?php echo $customer['id']; ?>" class="btn btn-outline-primary" 
                                               data-bs-toggle="tooltip" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="?action=edit&id=<?php echo $customer['id']; ?>" class="btn btn-outline-warning"
                                               data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?action=projects&id=<?php echo $customer['id']; ?>" class="btn btn-outline-info"
                                               data-bs-toggle="tooltip" title="Projects">
                                                <i class="bi bi-folder"></i>
                                            </a>
                                            <?php if ($customer['email']): ?>
                                            <a href="mailto:<?php echo $customer['email']; ?>" class="btn btn-outline-success"
                                               data-bs-toggle="tooltip" title="Email">
                                                <i class="bi bi-envelope"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h3 class="text-muted mt-3">No Customers Found</h3>
                        <?php if (isset($_GET['search'])): ?>
                            <p class="text-muted">No customers match your search criteria.</p>
                            <a href="manage_customers.php" class="btn btn-primary mt-3">
                                <i class="bi bi-arrow-left me-2"></i>Clear Search
                            </a>
                        <?php else: ?>
                            <p class="text-muted">You haven't added any customers yet.</p>
                            <a href="?action=add" class="btn btn-primary btn-lg mt-3">
                                <i class="bi bi-person-plus me-2"></i>Add Your First Customer
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($action == 'add' || $action == 'edit'): ?>
        <!-- Add/Edit Customer Form -->
        <?php 
        $customer = $customer_id > 0 ? getCustomerById($customer_id) : null;
        $title = $action == 'add' ? 'Add New Customer' : 'Edit Customer';
        ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo $title; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="?action=save&id=<?php echo $customer_id; ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Customer Code *</label>
                                    <input type="text" name="customer_code" class="form-control" 
                                           value="<?php echo $customer ? $customer['customer_code'] : 'CUST-' . date('Ymd-His'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active" <?php echo ($customer && $customer['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo ($customer && $customer['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?php echo $customer ? $customer['first_name'] : ''; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?php echo $customer ? $customer['last_name'] : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control" 
                                           value="<?php echo $customer ? $customer['company_name'] : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tax ID</label>
                                    <input type="text" name="tax_id" class="form-control" 
                                           value="<?php echo $customer ? $customer['tax_id'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo $customer ? $customer['email'] : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone *</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?php echo $customer ? $customer['phone'] : ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Alternate Phone</label>
                                    <input type="text" name="phone2" class="form-control" 
                                           value="<?php echo $customer ? $customer['phone2'] : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" 
                                           value="<?php echo $customer ? $customer['city'] : ''; ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" class="form-control" 
                                           value="<?php echo $customer ? $customer['country'] : 'Kenya'; ?>">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?php echo $customer ? $customer['address'] : ''; ?></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"><?php echo $customer ? $customer['notes'] : ''; ?></textarea>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="col-12 mt-3">
                                    <div class="card bg-light">
                                        <div class="card-body py-2">
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?php echo $customer ? $customer['customer_code'] : ''; ?>')">
                                                    <i class="bi bi-clipboard me-1"></i>Copy Code
                                                </button>
                                                <?php if ($customer_id > 0): ?>
                                                <a href="../projects/add_project.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-plus-circle me-1"></i>Add Project
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="manage_customers.php" class="btn btn-secondary">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Save Customer</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <?php if ($customer): ?>
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Customer Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php 
                        $lifetime_value = calculateCustomerLifetimeValue($customer_id);
                        $projects = getCustomerProjects($customer_id);
                        ?>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Projects:</span>
                                <span class="fw-bold"><?php echo $lifetime_value['project_count']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Contracts:</span>
                                <span class="fw-bold"><?php echo formatCurrency($lifetime_value['total_contracts']); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Payments:</span>
                                <span class="fw-bold"><?php echo formatCurrency($lifetime_value['total_payments']); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>First Project:</span>
                                <span><?php echo $lifetime_value['first_project'] ? date('d/m/Y', strtotime($lifetime_value['first_project'])) : 'N/A'; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Last Project:</span>
                                <span><?php echo $lifetime_value['last_project'] ? date('d/m/Y', strtotime($lifetime_value['last_project'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                        
                        <?php if ($projects): ?>
                        <div class="mt-3">
                            <h6>Recent Projects</h6>
                            <div class="list-group">
                                <?php foreach (array_slice($projects, 0, 3) as $project): ?>
                                <a href="../projects/view_projects.php?id=<?php echo $project['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $project['project_name']; ?></h6>
                                        <small><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo $project['project_code']; ?></p>
                                    <small class="text-muted"><?php echo formatCurrency($project['payment_received']); ?></small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($action == 'view' && $customer_id > 0): ?>
        <!-- View Customer Details -->
        <?php 
        $customer = getCustomerById($customer_id);
        $projects = getCustomerProjects($customer_id);
        $lifetime_value = calculateCustomerLifetimeValue($customer_id);
        ?>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Customer Details</h5>
                        <div class="btn-group">
                            <a href="?action=edit&id=<?php echo $customer_id; ?>" class="btn btn-warning btn-sm">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </a>
                            <a href="?action=projects&id=<?php echo $customer_id; ?>" class="btn btn-info btn-sm">
                                <i class="bi bi-folder me-1"></i>View Projects
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Personal Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Customer Code:</th>
                                        <td><span class="badge bg-secondary"><?php echo $customer['customer_code']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th>Name:</th>
                                        <td><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Company:</th>
                                        <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="badge bg-<?php echo $customer['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Contact Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Email:</th>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Alt Phone:</th>
                                        <td><?php echo htmlspecialchars($customer['phone2']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tax ID:</th>
                                        <td><?php echo htmlspecialchars($customer['tax_id']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-12">
                                <h6>Address</h6>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($customer['address'])); ?><br>
                                        <?php echo htmlspecialchars($customer['city']); ?>, <?php echo htmlspecialchars($customer['country']); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($customer['notes']): ?>
                            <div class="col-12 mt-3">
                                <h6>Notes</h6>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($customer['notes'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Projects Section -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Customer Projects</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($projects): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Project Code</th>
                                            <th>Project Name</th>
                                            <th>Rig</th>
                                            <th>Type</th>
                                            <th>Depth</th>
                                            <th>Contract Amount</th>
                                            <th>Paid Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></td>
                                            <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                            <td><?php echo $project['rig_name']; ?></td>
                                            <td><span class="badge bg-info"><?php echo $project['project_type']; ?></span></td>
                                            <td><?php echo $project['depth']; ?>m</td>
                                            <td><?php echo formatCurrency($project['contract_amount']); ?></td>
                                            <td><?php echo formatCurrency($project['payment_received']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $project['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($project['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../projects/view_projects.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-folder-x display-4 text-muted"></i>
                                <h5 class="text-muted mt-3">No projects found for this customer</h5>
                                <a href="../projects/add_project.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-circle me-1"></i>Create First Project
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Customer Statistics -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Customer Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Projects:</span>
                                <span class="fw-bold"><?php echo $lifetime_value['project_count']; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Contract Value:</span>
                                <span class="fw-bold"><?php echo formatCurrency($lifetime_value['total_contracts']); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Total Payments Received:</span>
                                <span class="fw-bold"><?php echo formatCurrency($lifetime_value['total_payments']); ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Average Project Value:</span>
                                <span class="fw-bold">
                                    <?php echo $lifetime_value['project_count'] > 0 ? 
                                           formatCurrency($lifetime_value['total_contracts'] / $lifetime_value['project_count']) : 
                                           formatCurrency(0); ?>
                                </span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>First Project:</span>
                                <span><?php echo $lifetime_value['first_project'] ? date('d/m/Y', strtotime($lifetime_value['first_project'])) : 'N/A'; ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between">
                                <span>Last Project:</span>
                                <span><?php echo $lifetime_value['last_project'] ? date('d/m/Y', strtotime($lifetime_value['last_project'])) : 'N/A'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card shadow">
                    <div class="card-header">
                        <h6 class="card-title mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../projects/add_project.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add New Project
                            </a>
                            <a href="?action=edit&id=<?php echo $customer_id; ?>" class="btn btn-warning">
                                <i class="bi bi-pencil me-2"></i>Edit Customer
                            </a>
                            <a href="manage_customers.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($action == 'projects' && $customer_id > 0): ?>
        <!-- Customer Projects Page -->
        <?php 
        $customer = getCustomerById($customer_id);
        $projects = getCustomerProjects($customer_id);
        ?>
        
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0">Projects for <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h5>
                    <p class="text-muted mb-0">Customer Code: <?php echo $customer['customer_code']; ?></p>
                </div>
                <div>
                    <a href="../projects/add_project.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Project
                    </a>
                    <a href="?action=view&id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Customer
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($projects): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project Code</th>
                                    <th>Project Name</th>
                                    <th>Rig</th>
                                    <th>Type</th>
                                    <th>Depth (m)</th>
                                    <th>Start Date</th>
                                    <th>Completion Date</th>
                                    <th>Contract Amount</th>
                                    <th>Paid Amount</th>
                                    <th>Profit</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): 
                                    $profit = calculateProjectProfit($project['id']);
                                ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></td>
                                    <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                    <td><?php echo $project['rig_name']; ?></td>
                                    <td><span class="badge bg-info"><?php echo $project['project_type']; ?></span></td>
                                    <td><?php echo $project['depth']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($project['start_date'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></td>
                                    <td><?php echo formatCurrency($project['contract_amount']); ?></td>
                                    <td><?php echo formatCurrency($project['payment_received']); ?></td>
                                    <td class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold">
                                        <?php echo formatCurrency($profit); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $project['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../projects/view_projects.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="../projects/add_expense.php?project_id=<?php echo $project['id']; ?>" class="btn btn-outline-success">
                                                <i class="bi bi-cash"></i>
                                            </a>
                                            <a href="../reports/project_report.php?id=<?php echo $project['id']; ?>" class="btn btn-outline-info">
                                                <i class="bi bi-graph-up"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Project Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Projects</h6>
                                    <h4 class="card-title fw-bold"><?php echo count($projects); ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                                    <h4 class="card-title fw-bold">
                                        <?php echo formatCurrency(array_sum(array_column($projects, 'payment_received'))); ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h6 class="card-subtitle mb-2 text-muted">Total Profit</h6>
                                    <h4 class="card-title fw-bold">
                                        <?php 
                                        $total_profit = 0;
                                        foreach ($projects as $project) {
                                            $total_profit += calculateProjectProfit($project['id']);
                                        }
                                        echo formatCurrency($total_profit);
                                        ?>
                                    </h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php 
                            $completed = array_filter($projects, function($p) {
                                return $p['status'] == 'completed';
                            });
                            ?>
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <h6 class="card-subtitle mb-2 text-muted">Completed</h6>
                                    <h4 class="card-title fw-bold"><?php echo count($completed); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder-x display-1 text-muted"></i>
                        <h3 class="text-muted mt-3">No Projects Found</h3>
                        <p class="text-muted">This customer doesn't have any projects yet.</p>
                        <a href="../projects/add_project.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary btn-lg mt-3">
                            <i class="bi bi-plus-circle me-2"></i>Create First Project
                        </a>
                    </div>
                <?php endif; ?>
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
    background-color: #f8f9fc;
}

.table th {
    font-weight: 600;
    color: #5a5c69;
    border-bottom: 2px solid #e3e6f0;
}

.badge {
    font-weight: 500;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.cursor-pointer {
    cursor: pointer;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    transition: all 0.2s;
}

.input-group-text {
    background-color: #f8f9fa;
}
</style>

<script>
$(document).ready(function() {
    // Initialize DataTable for customers table
    if ($('#customersTable').length) {
        $('#customersTable').DataTable({
            "pageLength": 25,
            "order": [[1, 'asc']],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>tip',
            "language": {
                "search": "Search customers:",
                "lengthMenu": "Show _MENU_ customers",
                "info": "Showing _START_ to _END_ of _TOTAL_ customers",
                "infoEmpty": "No customers found",
                "infoFiltered": "(filtered from _MAX_ total customers)"
            }
        });
    }
    
    // Auto-generate customer code
    $('input[name="customer_code"]').on('focus', function() {
        if (!$(this).val()) {
            var code = 'CUST-' + new Date().toISOString().slice(0,10).replace(/-/g,'') + 
                       '-' + Math.floor(Math.random() * 1000);
            $(this).val(code);
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Copy to clipboard function
    window.copyToClipboard = function(text) {
        if (!text) {
            alert('No text to copy');
            return;
        }
        
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            var toastEl = document.createElement('div');
            toastEl.className = 'position-fixed bottom-0 end-0 p-3';
            toastEl.style.zIndex = '11';
            toastEl.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <strong class="me-auto">Success</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Copied to clipboard: ${text}
                    </div>
                </div>
            `;
            document.body.appendChild(toastEl);
            
            // Remove toast after 3 seconds
            setTimeout(function() {
                toastEl.remove();
            }, 3000);
        }).catch(function(err) {
            alert('Failed to copy: ' + err);
        });
    };
    
    // Quick search for phone number formatting
    $('input[name="phone"], input[name="phone2"]').on('blur', function() {
        var phone = $(this).val().replace(/\D/g, '');
        if (phone.length === 9) {
            phone = '0' + phone;
        }
        if (phone.length === 10) {
            phone = phone.replace(/(\d{4})(\d{3})(\d{3})/, '$1 $2 $3');
        }
        $(this).val(phone);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>