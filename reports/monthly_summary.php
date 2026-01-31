<?php
// 1. First include config.php to define ROOT_PATH
require_once '../config.php';

// 2. Define month/year variables
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


// Get parameters
$rig_id = isset($_GET['rig']) ? intval($_GET['rig']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : $selected_month;
$year = isset($_GET['year']) ? intval($_GET['year']) : $selected_year;

// Get rig name if specific rig selected
$rig_name = 'All Rigs';
if ($rig_id > 0) {
    $rig = fetchOne("SELECT * FROM rigs WHERE id = $rig_id");
    $rig_name = $rig ? $rig['rig_name'] : 'Unknown Rig';
}

// Get monthly data
if ($rig_id > 0) {
    // Get projects for specific rig
    $projects = fetchAll("SELECT p.* FROM projects p 
                         WHERE p.rig_id = $rig_id 
                         AND YEAR(p.completion_date) = $year 
                         AND MONTH(p.completion_date) = $month
                         ORDER BY p.completion_date DESC");
} else {
    // Get all projects for the month
    $projects = fetchAll("SELECT p.*, r.rig_name 
                         FROM projects p 
                         LEFT JOIN rigs r ON p.rig_id = r.id
                         WHERE YEAR(p.completion_date) = $year 
                         AND MONTH(p.completion_date) = $month
                         ORDER BY p.completion_date DESC");
}

// Initialize monthly data
$monthly_data = [
    'revenue' => 0,
    'expenses' => 0,
    'profit' => 0,
    'project_count' => 0,
    'profit_margin' => 0
];

// Get expense breakdown from new expenses system
$expense_breakdown = [
    'Personnel' => 0,
    'Fuel' => 0,
    'Materials' => 0,
    'Consumables' => 0,
    'Other' => 0
];

// Process each project
foreach ($projects as &$project) {
    // Get complete expenses for this project
    $complete_expenses = getCompleteProjectExpenses($project['id']);
    $project_expenses = $complete_expenses['total'];
    
    // Calculate revenue and profit
    $revenue = $project['payment_received'];
    $profit = $revenue - $project_expenses;
    
    // Update monthly totals
    $monthly_data['revenue'] += $revenue;
    $monthly_data['expenses'] += $project_expenses;
    $monthly_data['profit'] += $profit;
    
    // Add to expense breakdown by category
    $project_breakdown = getExpenseBreakdownByCategory($project['id']);
    foreach ($project_breakdown as $category => $data) {
        $clean_category = trim($category);
        if (isset($expense_breakdown[$clean_category])) {
            $expense_breakdown[$clean_category] += $data['total'];
        } else {
            $expense_breakdown[$clean_category] = $data['total'];
        }
    }
    
    // Determine salary source for this project
    $salary_data = fetchOne("SELECT SUM(amount) as monthly_salary 
                            FROM expenses 
                            WHERE project_id = {$project['id']} 
                            AND ref_number = 'MONTHLY-SALARY'");
    
    $project_salary = fetchOne("SELECT SUM(amount) as project_salary 
                               FROM expenses 
                               WHERE project_id = {$project['id']} 
                               AND ref_number != 'MONTHLY-SALARY'
                               AND expense_type_id IN (SELECT id FROM expense_types WHERE category = 'Personnel')");
    
    if ($salary_data && $salary_data['monthly_salary'] > 0) {
        if ($project_salary && $project_salary['project_salary'] > 0) {
            $salary_source = 'mixed';
        } else {
            $salary_source = 'monthly';
        }
    } else {
        $salary_source = 'project';
    }
    
    // Add salary source to project array
    $project['salary_source'] = $salary_source;
    $project['expenses_total'] = $project_expenses;
    $project['profit'] = $profit;
    $project['margin'] = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
}

$monthly_data['project_count'] = count($projects);
$monthly_data['profit_margin'] = $monthly_data['revenue'] > 0 ? 
    ($monthly_data['profit'] / $monthly_data['revenue']) * 100 : 0;
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2">Monthly Performance Report</h1>
                    <p class="lead mb-0"><?php echo $rig_name; ?> - <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
                </div>
                <div class="btn-group">
                    <form method="GET" class="d-flex gap-2 me-3">
                        <select name="month" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php for ($y = 2023; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <select name="rig" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">All Rigs</option>
                            <?php 
                            $all_rigs = fetchAll("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name");
                            foreach ($all_rigs as $rig): ?>
                                <option value="<?php echo $rig['id']; ?>" <?php echo $rig_id == $rig['id'] ? 'selected' : ''; ?>>
                                    <?php echo $rig['rig_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="page" value="reports">
                    </form>
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <a href="export_excel.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&rig=<?php echo $rig_id; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                    <h6 class="card-title fw-bold"><?php echo formatCurrency($monthly_data['revenue']); ?></h6>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Expenses</h6>
                    <h6 class="card-title fw-bold"><?php echo formatCurrency($monthly_data['expenses']); ?></h6>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card <?php echo $monthly_data['profit'] >= 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Net Profit</h6>
                    <h6 class="card-title fw-bold <?php echo $monthly_data['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency($monthly_data['profit']); ?>
                    </h6>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card <?php echo $monthly_data['profit_margin'] >= 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Profit Margin</h6>
                    <h6 class="card-title fw-bold <?php echo $monthly_data['profit_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
                    </h6>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Projects Completed</h6>
                    <h6 class="card-title fw-bold"><?php echo $monthly_data['project_count']; ?></h6>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts and Data -->
    <div class="row">
        <!-- Expense Breakdown Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Expense Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="height: 300px;">
                        <canvas id="expenseChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Total Expenses: <?php echo formatCurrency($monthly_data['expenses']); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Expense Breakdown Details -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Expense Details</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php 
                        // Define colors for categories
                        $category_colors = [
                            'Personnel' => 'primary',
                            'Fuel' => 'warning',
                            'Materials' => 'info',
                            'Consumables' => 'success',
                            'Other' => 'secondary',
                            'Transportation' => 'dark',
                            'Equipment' => 'light'
                        ];
                        
                        $total_expenses = $monthly_data['expenses'];
                        foreach ($expense_breakdown as $category => $amount):
                            if ($amount > 0):
                                $percentage = $total_expenses > 0 ? ($amount / $total_expenses) * 100 : 0;
                                $color = isset($category_colors[$category]) ? $category_colors[$category] : 'secondary';
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-<?php echo $color; ?> me-2"><?php echo $category; ?></span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo formatCurrency($amount); ?></div>
                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                        </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Projects List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Project Details</h5>
                    <span class="badge bg-primary"><?php echo count($projects); ?> Projects</span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($projects) == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No projects found for this period</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr class="small">
                                        <th>Project Code</th>
                                        <th>Project Name</th>
                                        <?php if ($rig_id == 0): ?>
                                            <th>Rig</th>
                                        <?php endif; ?>
                                        <th class="text-center">Salary Type</th>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Expenses</th>
                                        <th class="text-end">Profit</th>
                                        <th class="text-end">Margin</th>
                                        <th>Completion Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></td>
                                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                        <?php if ($rig_id == 0): ?>
                                            <td><?php echo $project['rig_name'] ?? 'No Rig'; ?></td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <?php 
                                            $badge_color = '';
                                            $source_text = '';
                                            switch ($project['salary_source']) {
                                                case 'monthly':
                                                    $badge_color = 'success';
                                                    $source_text = 'Monthly';
                                                    break;
                                                case 'project':
                                                    $badge_color = 'info';
                                                    $source_text = 'Project';
                                                    break;
                                                case 'mixed':
                                                    $badge_color = 'warning';
                                                    $source_text = 'Mixed';
                                                    break;
                                                default:
                                                    $badge_color = 'secondary';
                                                    $source_text = 'Project';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $badge_color; ?>">
                                                <?php echo $source_text; ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?php echo formatCurrency($project['payment_received']); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($project['expenses_total']); ?></td>
                                        <td class="text-end <?php echo $project['profit'] >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                            <?php echo formatCurrency($project['profit']); ?>
                                        </td>
                                        <td class="text-end <?php echo $project['margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($project['margin'], 2); ?>%
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></td>
                                        <td class="text-center">
                                            <a href="../modules/projects/project_details.php?id=<?php echo $project['id']; ?>" 
                                               class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="<?php echo $rig_id == 0 ? 4 : 3; ?>" class="fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($monthly_data['revenue']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($monthly_data['expenses']); ?></td>
                                        <td class="text-end fw-bold <?php echo $monthly_data['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($monthly_data['profit']); ?>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $monthly_data['profit_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
                                        </td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Expense Breakdown Chart
document.addEventListener('DOMContentLoaded', function() {
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    
    // Prepare data for chart
    const categories = [];
    const amounts = [];
    const backgroundColors = [];
    
    // Define colors for categories
    const colorMap = {
        'Personnel': '#0d6efd',
        'Fuel': '#ffc107',
        'Materials': '#0dcaf0',
        'Consumables': '#198754',
        'Other': '#6c757d',
        'Transportation': '#212529',
        'Equipment': '#f8f9fa'
    };
    
    <?php foreach ($expense_breakdown as $category => $amount): ?>
        <?php if ($amount > 0): ?>
            categories.push('<?php echo addslashes($category); ?>');
            amounts.push(<?php echo $amount; ?>);
            backgroundColors.push('<?php echo isset($category_colors[$category]) ? 
                ($category_colors[$category] == 'primary' ? '#0d6efd' : 
                 ($category_colors[$category] == 'warning' ? '#ffc107' : 
                  ($category_colors[$category] == 'info' ? '#0dcaf0' : 
                   ($category_colors[$category] == 'success' ? '#198754' : 
                    ($category_colors[$category] == 'secondary' ? '#6c757d' : '#adb5bd'))))) : '#adb5bd'; ?>');
        <?php endif; ?>
    <?php endforeach; ?>
    
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: categories,
            datasets: [{
                data: amounts,
                backgroundColor: backgroundColors,
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            label += 'Ksh ' + value.toLocaleString() + ' (' + percentage + '%)';
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>