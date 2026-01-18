<?php
require_once '../includes/header.php';

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
    $monthly_data = getRigMonthlyPerformance($rig_id, $month, $year);
    $projects = fetchAll("SELECT p.* FROM projects p 
                         WHERE p.rig_id = $rig_id 
                         AND YEAR(p.completion_date) = $year 
                         AND MONTH(p.completion_date) = $month
                         ORDER BY p.completion_date DESC");
} else {
    $monthly_data = [
        'revenue' => 0,
        'expenses' => 0,
        'profit' => 0,
        'project_count' => 0,
        'profit_margin' => 0
    ];
    $projects = fetchAll("SELECT p.*, r.rig_name FROM projects p 
                         LEFT JOIN rigs r ON p.rig_id = r.id
                         WHERE YEAR(p.completion_date) = $year 
                         AND MONTH(p.completion_date) = $month
                         ORDER BY p.completion_date DESC");
    
    // Calculate totals for all rigs
    foreach ($projects as $project) {
        $expenses = getProjectExpenses($project['id']);
        $monthly_data['revenue'] += $project['payment_received'];
        $monthly_data['expenses'] += $expenses['total'];
        $monthly_data['profit'] += ($project['payment_received'] - $expenses['total']);
    }
    $monthly_data['project_count'] = count($projects);
    $monthly_data['profit_margin'] = $monthly_data['revenue'] > 0 ? 
        ($monthly_data['profit'] / $monthly_data['revenue']) * 100 : 0;
}

// Get expense breakdown
$expense_breakdown = [
    'salaries' => 0,
    'fuel' => 0,
    'casings' => 0,
    'consumables' => 0,
    'miscellaneous' => 0
];

foreach ($projects as $project) {
    $expenses = getProjectExpenses($project['id']);
    $fixed_expenses = fetchOne("SELECT * FROM fixed_expenses WHERE project_id = {$project['id']}");
    
    if ($fixed_expenses) {
        $expense_breakdown['salaries'] += $fixed_expenses['salaries'];
        $expense_breakdown['fuel'] += $fixed_expenses['fuel_rig'] + 
                                     $fixed_expenses['fuel_truck'] + 
                                     $fixed_expenses['fuel_pump'] + 
                                     $fixed_expenses['fuel_hired'];
        $expense_breakdown['casings'] += $fixed_expenses['casing_surface'] + 
                                        $fixed_expenses['casing_screened'] + 
                                        $fixed_expenses['casing_plain'];
    }
    
    $expense_breakdown['consumables'] += $expenses['consumables'];
    $expense_breakdown['miscellaneous'] += $expenses['miscellaneous'];
}
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
                    <h4 class="card-title fw-bold"><?php echo formatCurrency($monthly_data['revenue']); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Total Expenses</h6>
                    <h4 class="card-title fw-bold"><?php echo formatCurrency($monthly_data['expenses']); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card <?php echo $monthly_data['profit'] >= 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Net Profit</h6>
                    <h4 class="card-title fw-bold <?php echo $monthly_data['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo formatCurrency($monthly_data['profit']); ?>
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card <?php echo $monthly_data['profit_margin'] >= 0 ? 'border-success' : 'border-danger'; ?>">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Profit Margin</h6>
                    <h4 class="card-title fw-bold <?php echo $monthly_data['profit_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 col-xl mb-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6 class="card-subtitle mb-2 text-muted">Projects Completed</h6>
                    <h4 class="card-title fw-bold"><?php echo $monthly_data['project_count']; ?></h4>
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
                    <div class="chart-container">
                        <canvas id="expenseChart" height="250"></canvas>
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
                        $expense_categories = [
                            'salaries' => ['Salaries', 'primary'],
                            'fuel' => ['Fuel', 'warning'],
                            'casings' => ['Casings', 'info'],
                            'consumables' => ['Consumables', 'success'],
                            'miscellaneous' => ['Miscellaneous', 'secondary']
                        ];
                        
                        foreach ($expense_categories as $key => $details):
                            $percentage = $monthly_data['expenses'] > 0 ? 
                                ($expense_breakdown[$key] / $monthly_data['expenses']) * 100 : 0;
                        ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-<?php echo $details[1]; ?> me-2"><?php echo $details[0]; ?></span>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo formatCurrency($expense_breakdown[$key]); ?></div>
                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                                    <tr>
                                        <th>Project Code</th>
                                        <th>Project Name</th>
                                        <?php if ($rig_id == 0): ?>
                                            <th>Rig</th>
                                        <?php endif; ?>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Expenses</th>
                                        <th class="text-end">Profit</th>
                                        <th class="text-end">Margin</th>
                                        <th>Completion Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): 
                                        $profit = calculateProjectProfit($project['id']);
                                        $expenses = getProjectExpenses($project['id']);
                                        $revenue = $project['payment_received'];
                                        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-secondary"><?php echo $project['project_code']; ?></span></td>
                                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                        <?php if ($rig_id == 0): ?>
                                            <td><?php echo $project['rig_name']; ?></td>
                                        <?php endif; ?>
                                        <td class="text-end"><?php echo formatCurrency($revenue); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($expenses['total']); ?></td>
                                        <td class="text-end <?php echo $profit >= 0 ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                                            <?php echo formatCurrency($profit); ?>
                                        </td>
                                        <td class="text-end <?php echo $margin >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($margin, 2); ?>%
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="<?php echo $rig_id == 0 ? 3 : 2; ?>" class="fw-bold">TOTAL</td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($monthly_data['revenue']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatCurrency($monthly_data['expenses']); ?></td>
                                        <td class="text-end fw-bold <?php echo $monthly_data['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo formatCurrency($monthly_data['profit']); ?>
                                        </td>
                                        <td class="text-end fw-bold <?php echo $monthly_data['profit_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
                                        </td>
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

<script>
// Expense Breakdown Chart
document.addEventListener('DOMContentLoaded', function() {
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: ['Salaries', 'Fuel', 'Casings', 'Consumables', 'Miscellaneous'],
            datasets: [{
                data: [
                    <?php echo $expense_breakdown['salaries']; ?>,
                    <?php echo $expense_breakdown['fuel']; ?>,
                    <?php echo $expense_breakdown['casings']; ?>,
                    <?php echo $expense_breakdown['consumables']; ?>,
                    <?php echo $expense_breakdown['miscellaneous']; ?>
                ],
                backgroundColor: [
                    '#0d6efd', // Primary
                    '#ffc107', // Warning
                    '#0dcaf0', // Info
                    '#198754', // Success
                    '#6c757d'  // Secondary
                ],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += 'Ksh ' + context.parsed.toLocaleString();
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