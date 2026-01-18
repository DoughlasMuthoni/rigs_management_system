<?php
require_once 'includes/header.php';

// Get monthly performance for all rigs
$monthly_summary = getAllRigsMonthlySummary($selected_month, $selected_year);

// Calculate totals
$total_revenue = 0;
$total_expenses = 0;
$total_profit = 0;
$total_projects = 0;

foreach ($monthly_summary as $rig) {
    $total_revenue += $rig['revenue'];
    $total_expenses += $rig['expenses'];
    $total_profit += $rig['profit'];
    $total_projects += $rig['project_count'];
}

// Get project data for chart
$chart_labels = [];
$chart_revenue = [];
$chart_expenses = [];
$chart_profit = [];

foreach ($monthly_summary as $rig) {
    $chart_labels[] = $rig['rig_name'];
    $chart_revenue[] = $rig['revenue'];
    $chart_expenses[] = $rig['expenses'];
    $chart_profit[] = $rig['profit'];
}
?>

<!-- Bootstrap CSS CDN (add to header.php) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">

<div class="container-fluid mt-4">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_revenue); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-exchange fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_expenses); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cash-stack fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?php echo $total_profit >= 0 ? 'success' : 'danger'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $total_profit >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Total Profit</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrency($total_profit); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-graph-up-arrow fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Projects Completed</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_projects; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-clipboard-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rig Performance Cards -->
    <div class="row mb-4">
        <?php foreach ($monthly_summary as $rig): ?>
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $rig['rig_name']; ?> (<?php echo $rig['rig_code']; ?>)
                    </h6>
                    <span class="badge bg-<?php echo $rig['profit'] >= 0 ? 'success' : 'danger'; ?>">
                        <?php echo $rig['profit'] >= 0 ? 'Profitable' : 'Loss'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row no-gutters">
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Revenue
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['revenue']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Expenses
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['expenses']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-<?php echo $rig['profit'] >= 0 ? 'success' : 'danger'; ?> text-uppercase mb-1">
                                Profit
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo formatCurrency($rig['profit']); ?>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Margin
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo number_format($rig['profit_margin'], 2); ?>%
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Projects
                            </div>
                            <div class="h6 font-weight-bold text-gray-800 mb-0">
                                <?php echo $rig['project_count']; ?> Completed
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between">
                        <a href="modules/projects/view_projects.php?rig=<?php echo $rig['rig_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View Projects
                        </a>
                        <a href="reports/monthly_summary.php?rig=<?php echo $rig['rig_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                           class="btn btn-sm btn-outline-info">
                            <i class="bi bi-graph-up me-1"></i>Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Chart Section -->
    <div class="row mb-4">
        <div class="col-xl-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Performance Comparison</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="performanceChart" height="70"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<script>
const ctx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [
            {
                label: 'Revenue',
                data: <?php echo json_encode($chart_revenue); ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.7)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 1
            },
            {
                label: 'Expenses',
                data: <?php echo json_encode($chart_expenses); ?>,
                backgroundColor: 'rgba(246, 194, 62, 0.7)',
                borderColor: 'rgba(246, 194, 62, 1)',
                borderWidth: 1
            },
            {
                label: 'Profit',
                data: <?php echo json_encode($chart_profit); ?>,
                backgroundColor: 'rgba(28, 200, 138, 0.7)',
                borderColor: 'rgba(28, 200, 138, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Ksh ' + value.toLocaleString();
                    }
                },
                grid: {
                    drawBorder: false,
                    color: 'rgba(0, 0, 0, 0.1)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.7)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: 'rgba(255, 255, 255, 0.1)',
                borderWidth: 1,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += 'Ksh ' + context.parsed.y.toLocaleString();
                        return label;
                    }
                }
            }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>