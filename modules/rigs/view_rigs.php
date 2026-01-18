<?php
// view_projects.php
require_once '../../config.php';
require_once ROOT_PATH . '/includes/init.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/header.php';


// Check user permissions
if (!hasRole('admin') && !hasRole('manager')) {
    header('Location: ../../index.php');
    exit();
}

$message = '';
$message_type = '';

// Handle rig status toggle
if (isset($_GET['toggle_status']) && isset($_GET['rig_id'])) {
    $rig_id = intval($_GET['rig_id']);
    
    // Get current status
    $rig = fetchOne("SELECT * FROM rigs WHERE id = $rig_id");
    if ($rig) {
        $new_status = $rig['status'] == 'active' ? 'inactive' : 'active';
        $sql = "UPDATE rigs SET status = '$new_status' WHERE id = $rig_id";
        
        if (query($sql)) {
            $message = "Rig status updated successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to update rig status!";
            $message_type = "error";
        }
    }
}

// Handle rig deletion
if (isset($_GET['delete']) && isset($_GET['rig_id'])) {
    $rig_id = intval($_GET['rig_id']);
    
    // Check if rig has projects
    $project_count = fetchOne("SELECT COUNT(*) as count FROM projects WHERE rig_id = $rig_id");
    
    if ($project_count['count'] > 0) {
        $message = "Cannot delete rig with assigned projects!";
        $message_type = "error";
    } else {
        $sql = "DELETE FROM rigs WHERE id = $rig_id";
        if (query($sql)) {
            $message = "Rig deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to delete rig!";
            $message_type = "error";
        }
    }
}

// Get all rigs with performance stats
$sql = "SELECT 
            r.*,
            COUNT(p.id) as total_projects,
            COALESCE(SUM(p.payment_received), 0) as total_revenue,
            COALESCE(
                (SELECT COUNT(*) 
                 FROM projects p2 
                 WHERE p2.rig_id = r.id 
                 AND YEAR(p2.completion_date) = YEAR(CURDATE())
                 AND MONTH(p2.completion_date) = MONTH(CURDATE())
                ), 0) as current_month_projects
        FROM rigs r
        LEFT JOIN projects p ON r.id = p.rig_id
        GROUP BY r.id
        ORDER BY r.status DESC, r.rig_name";

$rigs = fetchAll($sql);
?>

<main class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Rigs Management</h1>
                <p>Manage drilling rigs, view performance metrics, and update configurations</p>
            </div>
            <div>
                <a href="add_rig.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add New Rig
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#helpModal">
                    <i class="bi bi-question-circle me-2"></i>Help
                </button>
            </div>
        </div>
    </div>

    <!-- Message Alert -->
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Rigs Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                Total Rigs</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php echo count($rigs); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                Active Rigs</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php 
                                    $active_count = array_filter($rigs, function($rig) {
                                        return $rig['status'] == 'active';
                                    });
                                    echo count($active_count);
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                Inactive Rigs</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php 
                                    $inactive_count = array_filter($rigs, function($rig) {
                                        return $rig['status'] == 'inactive';
                                    });
                                    echo count($inactive_count);
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-pause-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                This Month's Projects</div>
                            <div class="h5 mb-0 fw-bold text-gray-800">
                                <?php 
                                    $monthly_projects = array_sum(array_column($rigs, 'current_month_projects'));
                                    echo $monthly_projects;
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rigs Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">All Rigs</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="rigsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Rig Code</th>
                            <th>Rig Name</th>
                            <th>Status</th>
                            <th>Total Projects</th>
                            <th>Total Revenue</th>
                            <th>Current Month</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($rigs) == 0): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-truck display-6 mb-3"></i>
                                        <h5>No rigs found</h5>
                                        <p>Add your first rig to start tracking performance</p>
                                        <a href="add_rig.php" class="btn btn-primary">
                                            <i class="bi bi-plus-circle me-2"></i>Add First Rig
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rigs as $rig): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $rig['rig_code']; ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                                                 style="width: 40px; height: 40px;">
                                                <i class="bi bi-truck"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo $rig['rig_name']; ?></h6>
                                            <small class="text-muted">ID: <?php echo $rig['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $rig['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($rig['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-clipboard-check text-primary me-2"></i>
                                        <span><?php echo $rig['total_projects']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-currency-exchange text-success me-2"></i>
                                        <span class="fw-bold"><?php echo formatCurrency($rig['total_revenue']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-month text-info me-2"></i>
                                        <span><?php echo $rig['current_month_projects']; ?> projects</span>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($rig['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="edit_rig.php?id=<?php echo $rig['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           data-bs-toggle="tooltip" 
                                           title="Edit Rig">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        
                                        <a href="?toggle_status=1&rig_id=<?php echo $rig['id']; ?>" 
                                           class="btn btn-sm btn-outline-<?php echo $rig['status'] == 'active' ? 'warning' : 'success'; ?>" 
                                           data-bs-toggle="tooltip" 
                                           title="<?php echo $rig['status'] == 'active' ? 'Deactivate' : 'Activate'; ?> Rig"
                                           onclick="return confirm('Are you sure you want to <?php echo $rig['status'] == 'active' ? 'deactivate' : 'activate'; ?> this rig?')">
                                            <i class="bi bi-power"></i>
                                        </a>
                                        
                                        <a href="?delete=1&rig_id=<?php echo $rig['id']; ?>" 
                                           class="btn btn-sm btn-outline-danger" 
                                           data-bs-toggle="tooltip" 
                                           title="Delete Rig"
                                           onclick="return confirm('Are you sure you want to delete this rig? This action cannot be undone.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        
                                        <a href="rig_details.php?id=<?php echo $rig['id']; ?>" 
                                           class="btn btn-sm btn-outline-info" 
                                           data-bs-toggle="tooltip" 
                                           title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Export Options -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted">
                    Showing <?php echo count($rigs); ?> rig<?php echo count($rigs) != 1 ? 's' : ''; ?>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Rig Performance Overview</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="rigPerformanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 fw-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="add_rig.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-plus-circle text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">Add New Rig</h6>
                                <small class="text-muted">Register a new drilling rig</small>
                            </div>
                        </a>
                        <a href="../projects/view_projects.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-clipboard-check text-success me-3"></i>
                            <div>
                                <h6 class="mb-0">View Projects</h6>
                                <small class="text-muted">Check all drilling projects</small>
                            </div>
                        </a>
                        <a href="../reports/monthly_summary.php" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-graph-up text-info me-3"></i>
                            <div>
                                <h6 class="mb-0">Performance Reports</h6>
                                <small class="text-muted">Generate detailed reports</small>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="bi bi-question-circle text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0">Help & Support</h6>
                                <small class="text-muted">Get assistance</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="bi bi-question-circle me-2"></i>Rigs Management Help
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-info-circle text-primary me-2"></i>Managing Rigs</h6>
                        <p class="small">Rigs are your drilling equipment units. Each rig can be tracked independently for performance analysis.</p>
                        
                        <h6><i class="bi bi-check-circle text-success me-2"></i>Active Status</h6>
                        <p class="small">Active rigs are available for new projects. Inactive rigs are under maintenance or out of service.</p>
                        
                        <h6><i class="bi bi-graph-up text-info me-2"></i>Performance Metrics</h6>
                        <ul class="small">
                            <li><strong>Total Projects:</strong> Number of projects completed by the rig</li>
                            <li><strong>Total Revenue:</strong> Total income generated by the rig</li>
                            <li><strong>Current Month:</strong> Projects completed this month</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-tools text-warning me-2"></i>Available Actions</h6>
                        <div class="d-flex flex-column gap-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-primary me-2">Edit</span>
                                <span class="small">Update rig details</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-warning me-2">Activate/Deactivate</span>
                                <span class="small">Change rig status</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-danger me-2">Delete</span>
                                <span class="small">Remove rig (no projects assigned)</span>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-info me-2">View Details</span>
                                <span class="small">See rig performance details</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="add_rig.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add New Rig
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Prepare chart data
    const rigNames = <?php echo json_encode(array_column($rigs, 'rig_name')); ?>;
    const rigRevenues = <?php echo json_encode(array_column($rigs, 'total_revenue')); ?>;
    const rigProjects = <?php echo json_encode(array_column($rigs, 'total_projects')); ?>;
    
    // Create performance chart
    const ctx = document.getElementById('rigPerformanceChart').getContext('2d');
    const rigPerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: rigNames,
            datasets: [
                {
                    label: 'Total Revenue',
                    data: rigRevenues,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Total Projects',
                    data: rigProjects,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1,
                    yAxisID: 'y1',
                    type: 'line'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (Ksh)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Ksh ' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Number of Projects'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += 'Ksh ' + context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.y + ' projects';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>