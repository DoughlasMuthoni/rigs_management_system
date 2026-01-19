<?php
// 1. Load config.php
require_once '../../config.php';

// 2. Load init.php
require_once ROOT_PATH . '/includes/init.php';

// 3. Load functions.php
require_once ROOT_PATH . '/includes/functions.php';

// 4. Load header.php
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

<main class="container-fluid mt-0">
    <!-- Page Header -->
    <div class="card shadow mb-4">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">Monthly Performance Report</h4>
                    <p class="text-muted mb-0">
                        <i class="bi bi-calendar3 me-1"></i><?php echo date('F Y', strtotime("$year-$month-01")); ?>
                        <?php if ($rig_id > 0): ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-truck me-1"></i><?php echo $rig_name; ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="#" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-printer"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Compact Summary Cards -->
    <div class="row mb-3">
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-primary border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-cash-coin text-primary fs-6"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Revenue</div>
                            <div class="fw-bold"><?php echo formatCurrency($monthly_data['revenue']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-warning border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-currency-dollar text-warning fs-8"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Expenses</div>
                            <div class="fw-bold"><?php echo formatCurrency($monthly_data['expenses']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-<?php echo $monthly_data['profit'] >= 0 ? 'success' : 'danger'; ?> border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-graph-up-arrow text-<?php echo $monthly_data['profit'] >= 0 ? 'success' : 'danger'; ?> fs-8"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Net Profit</div>
                            <div class="fw-bold text-<?php echo $monthly_data['profit'] >= 0 ? 'success' : 'danger'; ?>">
                                <?php echo formatCurrency($monthly_data['profit']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-info border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-percent text-info fs-8"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Margin</div>
                            <div class="fw-bold"><?php echo number_format($monthly_data['profit_margin'], 1); ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-secondary border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-clipboard-check text-secondary fs-8"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Projects</div>
                            <div class="fw-bold"><?php echo $monthly_data['project_count']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card border-start-dark border-top-0 border-end-0 border-bottom-0">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-calendar3 text-dark fs-8"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Avg/Project</div>
                            <div class="fw-bold">
                                <?php echo $monthly_data['project_count'] > 0 ? 
                                    formatCurrency($monthly_data['profit'] / $monthly_data['project_count']) : 
                                    formatCurrency(0); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Section - More Compact -->
    <div class="row mb-4">
        <div class="col-lg-7 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-pie-chart me-2"></i>Expense Breakdown
                        <small class="text-muted float-end">Total: <?php echo formatCurrency($monthly_data['expenses']); ?></small>
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div style="height: 250px;">
                        <canvas id="expenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-5 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header py-2">
                    <h6 class="m-0 fw-bold">
                        <i class="bi bi-bar-chart me-2"></i>Expense Distribution
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Salaries</span>
                            <span class="fw-bold"><?php echo formatCurrency($expense_breakdown['salaries']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-danger" 
                                 style="width: <?php echo $monthly_data['expenses'] > 0 ? ($expense_breakdown['salaries'] / $monthly_data['expenses'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Fuel</span>
                            <span class="fw-bold"><?php echo formatCurrency($expense_breakdown['fuel']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" 
                                 style="width: <?php echo $monthly_data['expenses'] > 0 ? ($expense_breakdown['fuel'] / $monthly_data['expenses'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Casings</span>
                            <span class="fw-bold"><?php echo formatCurrency($expense_breakdown['casings']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" 
                                 style="width: <?php echo $monthly_data['expenses'] > 0 ? ($expense_breakdown['casings'] / $monthly_data['expenses'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Consumables</span>
                            <span class="fw-bold"><?php echo formatCurrency($expense_breakdown['consumables']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" 
                                 style="width: <?php echo $monthly_data['expenses'] > 0 ? ($expense_breakdown['consumables'] / $monthly_data['expenses'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Miscellaneous</span>
                            <span class="fw-bold"><?php echo formatCurrency($expense_breakdown['miscellaneous']); ?></span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-secondary" 
                                 style="width: <?php echo $monthly_data['expenses'] > 0 ? ($expense_breakdown['miscellaneous'] / $monthly_data['expenses'] * 100) : 0; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="border-top pt-2">
                        <div class="row">
                            <div class="col-6 text-center">
                                <div class="small text-muted">Revenue</div>
                                <div class="h5 fw-bold text-success"><?php echo formatCurrency($monthly_data['revenue']); ?></div>
                            </div>
                            <div class="col-6 text-center">
                                <div class="small text-muted">Profit Margin</div>
                                <div class="h5 fw-bold text-<?php echo $monthly_data['profit_margin'] >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($monthly_data['profit_margin'], 1); ?>%
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Projects Table - More Compact -->
    <div class="card shadow-sm mb-4">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold">
                <i class="bi bi-list-check me-2"></i>Project Details
                <span class="badge bg-primary ms-2"><?php echo count($projects); ?> projects</span>
            </h6>
            <div>
                <small class="text-muted me-2"><?php echo $rig_name; ?></small>
                <span class="badge bg-info"><?php echo date('M Y', strtotime("$year-$month-01")); ?></span>
            </div>
        </div>
        <div class="card-body p-3">
            <?php if (count($projects) == 0): ?>
                <div class="text-center py-4">
                    <i class="bi bi-clipboard-x text-muted fs-1 mb-3"></i>
                    <h6 class="text-muted">No Projects Found</h6>
                    <p class="text-muted small mb-0">No projects found for <?php echo date('F Y', strtotime("$year-$month-01")); ?></p>
                    <?php if ($rig_id > 0): ?>
                        <a href="../modules/projects/add_project.php?rig_id=<?php echo $rig_id; ?>" class="btn btn-sm btn-primary mt-2">
                            <i class="bi bi-plus-circle me-1"></i>Add Project
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr class="small">
                                <th>Project</th>
                                <?php if ($rig_id == 0): ?>
                                    <th>Rig</th>
                                <?php endif; ?>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Expenses</th>
                                <th class="text-end">Profit</th>
                                <th class="text-end">Margin</th>
                                <th>Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): 
                                $profit = calculateProjectProfit($project['id']);
                                $expenses = getProjectExpenses($project['id']);
                                $revenue = $project['payment_received'];
                                $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;
                            ?>
                            <tr class="small">
                                <td>
                                    <div class="fw-bold"><?php echo $project['project_code']; ?></div>
                                    <div class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($project['project_name']); ?>">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </div>
                                </td>
                                <?php if ($rig_id == 0): ?>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?php echo $project['rig_name']; ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td class="text-end">
                                    <span class="text-success"><?php echo formatCurrency($revenue); ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="text-warning"><?php echo formatCurrency($expenses['total']); ?></span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-<?php echo $profit >= 0 ? 'success' : 'danger'; ?>">
                                        <?php echo formatCurrency($profit); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo $margin >= 0 ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($margin, 1); ?>%
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <?php echo date('d/m', strtotime($project['completion_date'])); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="#" 
                                           class="btn btn-outline-info btn-sm" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="#" 
                                           class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="small fw-bold bg-light">
                            <tr>
                                <td colspan="<?php echo $rig_id == 0 ? 2 : 1; ?>">TOTAL</td>
                                <td class="text-end text-success"><?php echo formatCurrency($monthly_data['revenue']); ?></td>
                                <td class="text-end text-warning"><?php echo formatCurrency($monthly_data['expenses']); ?></td>
                                <td class="text-end text-<?php echo $monthly_data['profit'] >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo formatCurrency($monthly_data['profit']); ?>
                                </td>
                                <td class="text-end text-<?php echo $monthly_data['profit_margin'] >= 0 ? 'success' : 'danger'; ?>">
                                    <?php echo number_format($monthly_data['profit_margin'], 1); ?>%
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Compact Export Options -->
    <div class="card shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Report generated on <?php echo date('d/m/Y H:i'); ?>
                </div>
                <div class="btn-group btn-group-sm">
                    <button onclick="window.print()" class="btn btn-outline-primary">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                    <a href="export_excel.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>&rig=<?php echo $rig_id; ?>" 
                       class="btn btn-outline-success">
                        <i class="bi bi-file-earmark-excel me-1"></i>Excel
                    </a>
                    <a href="#" class="btn btn-outline-secondary" onclick="downloadPDF()">
                        <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Expense Breakdown Chart - Smaller size
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut', // Changed to doughnut for smaller appearance
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
                ],
                borderWidth: 1,
                borderColor: '#fff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            cutout: '60%', // Makes doughnut chart thinner
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 10,
                        font: {
                            size: 11
                        }
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
    
    // PDF download function
    window.downloadPDF = function() {
        alert('PDF export feature will be implemented soon!');
    }
});
</script>

<style>
    /* Compact card styles */
    .card {
        border-radius: 8px;
    }
    
    .card-header {
        padding: 0.5rem 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    /* Smaller table font */
    .table-sm {
        font-size: 0.85rem;
    }
    
    .table-sm th {
        font-size: 0.8rem;
        padding: 0.5rem;
    }
    
    .table-sm td {
        padding: 0.5rem;
        vertical-align: middle;
    }
    
    /* Smaller badges */
    .badge {
        font-size: 0.75em;
        padding: 0.25em 0.5em;
    }
    
    /* Progress bars */
    .progress {
        border-radius: 4px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .col-6 {
            margin-bottom: 0.5rem;
        }
        
        .card-body {
            padding: 0.75rem;
        }
        
        .table-responsive {
            font-size: 0.8rem;
        }
    }
    
    /* Print styles */
    @media print {
        .btn, .actions-column, .no-print {
            display: none !important;
        }
        
        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
        
        .table {
            font-size: 10px !important;
        }
    }
</style>

<?php require_once '../../includes/footer.php'; ?>