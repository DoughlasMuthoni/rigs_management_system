<?php

// 1. Load config.php first
require_once '../../config.php';

// 2. Define month/year variables BEFORE including header.php
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


// Get filter parameters
$rig_id = isset($_GET['rig']) ? intval($_GET['rig']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : $selected_month;
$year = isset($_GET['year']) ? intval($_GET['year']) : $selected_year;
// Add this after line 44 (after $search variable)
$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // month, quarter, year
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 0;
$search = isset($_GET['search']) ? escape($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($rig_id > 0) {
    $where_conditions[] = "p.rig_id = $rig_id";
}

if (!empty($search)) {
    $where_conditions[] = "(p.project_code LIKE '%$search%' OR 
                           p.project_name LIKE '%$search%' OR 
                           CONCAT(c.first_name, ' ', c.last_name) LIKE '%$search%' OR
                           c.company_name LIKE '%$search%')";
}

// Handle period filters
if ($year > 0) {
    switch ($period) {
        case 'month':
            if ($month > 0) {
                $where_conditions[] = "YEAR(p.completion_date) = $year AND MONTH(p.completion_date) = $month";
            } else {
                $where_conditions[] = "YEAR(p.completion_date) = $year";
            }
            break;
            
        case 'quarter':
            if ($quarter > 0) {
                $start_month = (($quarter - 1) * 3) + 1;
                $end_month = $start_month + 2;
                $where_conditions[] = "YEAR(p.completion_date) = $year 
                                      AND MONTH(p.completion_date) BETWEEN $start_month AND $end_month";
            } else {
                $where_conditions[] = "YEAR(p.completion_date) = $year";
            }
            break;
            
        case 'year':
            $where_conditions[] = "YEAR(p.completion_date) = $year";
            break;
            
        case 'all':
            // No year filter for "all time"
            break;
            
        default:
            // Monthly default
            if ($month > 0 && $year > 0) {
                $where_conditions[] = "YEAR(p.completion_date) = $year AND MONTH(p.completion_date) = $month";
            } elseif ($year > 0) {
                $where_conditions[] = "YEAR(p.completion_date) = $year";
            }
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get projects - UPDATED QUERY WITH CUSTOMER JOIN
$sql = "SELECT p.*, r.rig_name, r.rig_code,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               c.company_name,
               c.first_name,
               c.last_name
        FROM projects p 
        LEFT JOIN rigs r ON p.rig_id = r.id 
        LEFT JOIN customers c ON p.customer_id = c.id
        $where_clause 
        ORDER BY p.completion_date DESC, p.id DESC";

$projects = fetchAll($sql);

// Get rigs for filter
$rigs = fetchAll("SELECT * FROM rigs ORDER BY rig_name");
?>

<div class="container-fluid mt-4">
 
    <!-- Filters Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <!-- Rig Filter -->
                        <div class="col-md-6 col-lg-3">
                            <label for="rig_filter" class="form-label">Rig</label>
                            <select id="rig_filter" name="rig" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Rigs</option>
                                <?php foreach ($rigs as $rig): ?>
                                    <option value="<?php echo $rig['id']; ?>" 
                                            <?php echo $rig['id'] == $rig_id ? 'selected' : ''; ?>>
                                        <?php echo $rig['rig_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                       <!-- Period Type Filter -->
<div class="col-md-6 col-lg-2">
    <label for="period_filter" class="form-label">Period Type</label>
    <select id="period_filter" name="period" class="form-select" onchange="updatePeriodFilters()">
        <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Monthly</option>
        <option value="quarter" <?php echo $period == 'quarter' ? 'selected' : ''; ?>>Quarterly</option>
        <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Yearly</option>
        <option value="all" <?php echo $period == 'all' ? 'selected' : ''; ?>>All Time</option>
    </select>
</div>

<!-- Month Filter (shown for monthly) -->
<div class="col-md-6 col-lg-2" id="month_filter_container" style="<?php echo $period != 'month' ? 'display:none;' : ''; ?>">
    <label for="month_filter" class="form-label">Month</label>
    <select id="month_filter" name="month" class="form-select">
        <option value="0">All Months</option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
            </option>
        <?php endfor; ?>
    </select>
</div>

<!-- Quarter Filter (shown for quarterly) -->
<div class="col-md-6 col-lg-2" id="quarter_filter_container" style="<?php echo $period != 'quarter' ? 'display:none;' : ''; ?>">
    <label for="quarter_filter" class="form-label">Quarter</label>
    <select id="quarter_filter" name="quarter" class="form-select">
        <option value="0">All Quarters</option>
        <option value="1" <?php echo $quarter == 1 ? 'selected' : ''; ?>>Q1 (Jan-Mar)</option>
        <option value="2" <?php echo $quarter == 2 ? 'selected' : ''; ?>>Q2 (Apr-Jun)</option>
        <option value="3" <?php echo $quarter == 3 ? 'selected' : ''; ?>>Q3 (Jul-Sep)</option>
        <option value="4" <?php echo $quarter == 4 ? 'selected' : ''; ?>>Q4 (Oct-Dec)</option>
    </select>
</div>

<!-- Year Filter -->
<div class="col-md-6 col-lg-2">
    <label for="year_filter" class="form-label">Year</label>
    <select id="year_filter" name="year" class="form-select">
        <option value="0">All Years</option>
        <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                <?php echo $y; ?>
            </option>
        <?php endfor; ?>
    </select>
</div>
                        
                        <!-- Search -->
                        <div class="col-md-6 col-lg-3">
                            <label for="search" class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" id="search" name="search" class="form-control" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Project code, name, or client">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="col-md-12 col-lg-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <a href="view_projects.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Projects Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Projects List</h5>
                    <span class="badge bg-primary">
                        <?php echo count($projects); ?> Projects 
                        <?php 
                        if ($period == 'month' && $month > 0 && $year > 0) {
                            echo " - " . date('F Y', strtotime("$year-$month-01"));
                        } elseif ($period == 'quarter' && $quarter > 0 && $year > 0) {
                            echo " - Q$quarter $year";
                        } elseif ($period == 'year' && $year > 0) {
                            echo " - Year $year";
                        }
                        ?>
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($projects) == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No projects found</h5>
                            <p class="text-muted">Try adjusting your filters or</p>
                            <a href="add_project.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Your First Project
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Project Code</th>
                                        <th>Project Name</th>
                                        <th>Client</th>
                                        <th>Rig</th>
                                        <th class="text-end">Contract Amount</th>
                                        <th class="text-end">Payment</th>
                                        <th>Completion Date</th>
                                        <th class="text-end">Profit</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): 
                                        $profit = calculateProjectProfit($project['id']);
                                        // $expenses = getProjectExpenses($project['id']);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $project['project_code']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($project['project_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if (!empty($project['first_name']) || !empty($project['last_name'])) {
                                                $client_display = htmlspecialchars(trim($project['first_name'] . ' ' . $project['last_name']));
                                                if (!empty($project['company_name'])) {
                                                    $client_display .= ' (' . htmlspecialchars($project['company_name']) . ')';
                                                }
                                                echo $client_display;
                                            } else {
                                                echo '<span class="text-muted">No client assigned</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($project['rig_name'])): ?>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo $project['rig_name']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">No Rig</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo formatCurrency($project['contract_amount']); ?></td>
                                        <td class="text-end">
                                            <span class="badge <?php echo $project['payment_received'] == $project['contract_amount'] ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo formatCurrency($project['payment_received']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo date('d/m/Y', strtotime($project['completion_date'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge <?php echo $profit >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo formatCurrency($profit); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_project.php?id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-outline-secondary"
                                                   data-bs-toggle="tooltip" title="Edit Project">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php if (count($projects) > 0): ?>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="4" class="fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold">
                                            <?php 
                                            $total_contract = array_sum(array_column($projects, 'contract_amount'));
                                            echo formatCurrency($total_contract); 
                                            ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?php 
                                            $total_payment = array_sum(array_column($projects, 'payment_received'));
                                            echo formatCurrency($total_payment); 
                                            ?>
                                        </td>
                                        <td></td>
                                        <td class="text-end fw-bold">
                                            <?php 
                                            $total_profit = 0;
                                            foreach ($projects as $project) {
                                                $total_profit += calculateProjectProfit($project['id']);
                                            }
                                            ?>
                                            <span class="<?php echo $total_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatCurrency($total_profit); ?>
                                            </span>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                                <?php endif; ?>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($projects) > 10): ?>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                Showing <?php echo min(10, count($projects)); ?> of <?php echo count($projects); ?> projects
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Hover over actions for tooltips
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
     // Set initial period filter state
    updatePeriodFilters();
});
// Update period filter visibility
function updatePeriodFilters() {
    const periodType = document.getElementById('period_filter').value;
    
    // Hide all filter containers first
    document.getElementById('month_filter_container').style.display = 'none';
    document.getElementById('quarter_filter_container').style.display = 'none';
    
    // Show relevant filter
    if (periodType === 'month') {
        document.getElementById('month_filter_container').style.display = 'block';
    } else if (periodType === 'quarter') {
        document.getElementById('quarter_filter_container').style.display = 'block';
    }
    
    // Update labels based on period
    const yearLabel = document.querySelector('label[for="year_filter"]');
    if (periodType === 'all') {
        yearLabel.textContent = 'Year (Optional)';
    } else {
        yearLabel.textContent = 'Year';
    }
}

</script>

<?php require_once '../../includes/footer.php'; ?>