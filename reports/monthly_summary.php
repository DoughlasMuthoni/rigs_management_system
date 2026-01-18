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

<div class="container">
    <div class="page-header">
        <h2>Monthly Performance Report</h2>
        <p><?php echo $rig_name; ?> - <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card">
            <h3>Total Revenue</h3>
            <div class="amount"><?php echo formatCurrency($monthly_data['revenue']); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Expenses</h3>
            <div class="amount"><?php echo formatCurrency($monthly_data['expenses']); ?></div>
        </div>
        <div class="summary-card">
            <h3>Net Profit</h3>
            <div class="amount <?php echo $monthly_data['profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                <?php echo formatCurrency($monthly_data['profit']); ?>
            </div>
        </div>
        <div class="summary-card">
            <h3>Profit Margin</h3>
            <div class="amount <?php echo $monthly_data['profit_margin'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
            </div>
        </div>
        <div class="summary-card">
            <h3>Projects Completed</h3>
            <div class="amount"><?php echo $monthly_data['project_count']; ?></div>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="chart-container">
        <h3>Expense Breakdown</h3>
        <canvas id="expenseChart" height="100"></canvas>
    </div>
    
    <!-- Projects List -->
    <div class="table-container">
        <h3>Projects Details (<?php echo count($projects); ?>)</h3>
        
        <?php if (count($projects) == 0): ?>
            <div class="no-data">
                <p>No projects found for this period.</p>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <?php if ($rig_id == 0): ?>
                            <th>Rig</th>
                        <?php endif; ?>
                        <th>Revenue</th>
                        <th>Expenses</th>
                        <th>Profit</th>
                        <th>Margin</th>
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
                        <td><?php echo $project['project_code']; ?></td>
                        <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                        <?php if ($rig_id == 0): ?>
                            <td><?php echo $project['rig_name']; ?></td>
                        <?php endif; ?>
                        <td><?php echo formatCurrency($revenue); ?></td>
                        <td><?php echo formatCurrency($expenses['total']); ?></td>
                        <td class="<?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo formatCurrency($profit); ?>
                        </td>
                        <td class="<?php echo $margin >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo number_format($margin, 2); ?>%
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($project['completion_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td colspan="<?php echo $rig_id == 0 ? 3 : 2; ?>">TOTAL</td>
                        <td><?php echo formatCurrency($monthly_data['revenue']); ?></td>
                        <td><?php echo formatCurrency($monthly_data['expenses']); ?></td>
                        <td class="<?php echo $monthly_data['profit'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo formatCurrency($monthly_data['profit']); ?>
                        </td>
                        <td class="<?php echo $monthly_data['profit_margin'] >= 0 ? 'profit-positive' : 'profit-negative'; ?>">
                            <?php echo number_format($monthly_data['profit_margin'], 2); ?>%
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Export Options -->
    <div class="export-options">
        <button onclick="window.print()" class="btn">Print Report</button>
        <a href="export_excel.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&rig=<?php echo $rig_id; ?>" 
           class="btn btn-secondary">Export to Excel</a>
    </div>
</div>

<script>
// Expense Breakdown Chart
const expenseCtx = document.getElementById('expenseChart').getContext('2d');
const expenseChart = new Chart(expenseCtx, {
    type: 'pie',
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
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
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
</script>

<?php require_once '../includes/footer.php'; ?>