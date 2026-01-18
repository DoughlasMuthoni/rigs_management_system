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

<div class="container">
    <div class="page-header">
        <h2>Projects</h2>
        <p>View and manage all drilling projects</p>
    </div>
    
    <!-- Filters -->
    <div class="filters-container">
        <form method="GET" class="filter-form">
            <div class="filter-row">
                <div class="form-group">
                    <label for="rig_filter">Filter by Rig:</label>
                    <select id="rig_filter" name="rig" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Rigs</option>
                        <?php foreach ($rigs as $rig): ?>
                            <option value="<?php echo $rig['id']; ?>" 
                                    <?php echo $rig['id'] == $rig_id ? 'selected' : ''; ?>>
                                <?php echo $rig['rig_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="month_filter">Month:</label>
                    <select id="month_filter" name="month" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Months</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="year_filter">Year:</label>
                    <select id="year_filter" name="year" class="form-control" onchange="this.form.submit()">
                        <option value="0">All Years</option>
                        <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Project code, name, or client">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="view_projects.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Projects Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>Projects (<?php echo count($projects); ?>)</h3>
            <a href="add_project.php" class="btn">+ Add New Project</a>
        </div>
        
        <?php if (count($projects) == 0): ?>
            <div class="no-data">
                <p>No projects found. <a href="add_project.php">Add your first project</a></p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Client</th>
                        <th>Rig</th>
                        <th>Contract Amount</th>
                        <th>Payment</th>
                        <th>Completion Date</th>
                        <th>Profit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): 
                        $profit = calculateProjectProfit($project['id']);
                        $expenses = getProjectExpenses($project['id']);
                    ?>
                    <tr>
                        <td><?php echo $project['project_code']; ?></td>
                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                        <td><?php echo htmlspecialchars($project['client_name']); ?></td>
                        <td><?php echo $project['rig_name']; ?></td>
                        <td><?php echo formatCurrency($project['contract_amount']); ?></td>
                        <td><?php echo formatCurrency($project['payment_received']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></td>
                        <td class="<?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo formatCurrency($profit); ?>
                        </td>
                        <td class="table-actions">
                            <a href="project_details.php?id=<?php echo $project['id']; ?>" 
                               class="btn btn-sm">View</a>
                            <a href="edit_project.php?id=<?php echo $project['id']; ?>" 
                               class="btn btn-sm btn-secondary">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>