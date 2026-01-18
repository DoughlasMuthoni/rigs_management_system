<?php
require_once '../../includes/header.php';

// Get filter parameters
$rig_id = isset($_GET['rig']) ? intval($_GET['rig']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : $selected_month;
$year = isset($_GET['year']) ? intval($_GET['year']) : $selected_year;
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
                           p.client_name LIKE '%$search%')";
}

if ($month > 0 && $year > 0) {
    $where_conditions[] = "YEAR(p.completion_date) = $year AND MONTH(p.completion_date) = $month";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get projects
$sql = "SELECT p.*, r.rig_name, r.rig_code 
        FROM projects p 
        LEFT JOIN rigs r ON p.rig_id = r.id 
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
                        
                        <!-- Month Filter -->
                        <div class="col-md-6 col-lg-2">
                            <label for="month_filter" class="form-label">Month</label>
                            <select id="month_filter" name="month" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <!-- Year Filter -->
                        <div class="col-md-6 col-lg-2">
                            <label for="year_filter" class="form-label">Year</label>
                            <select id="year_filter" name="year" class="form-select" onchange="this.form.submit()">
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
                    <span class="badge bg-primary"><?php echo count($projects); ?> Projects</span>
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
                                        $expenses = getProjectExpenses($project['id']);
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
                                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                                        <td>
                                            <span class="badge bg-info text-dark">
                                                <?php echo $project['rig_name']; ?>
                                            </span>
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
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_project.php?id=<?php echo $project['id']; ?>" 
                                                   class="btn btn-outline-secondary"
                                                   data-bs-toggle="tooltip" title="Edit Project">
                                                    <i class="fas fa-edit"></i>
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
});
</script>

<?php require_once '../../includes/footer.php'; ?>