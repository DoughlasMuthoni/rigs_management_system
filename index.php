<?php
// Include config.php which already includes functions.php and starts session
require_once 'config.php';

// Check if user is logged in
if (function_exists('isLoggedIn')) {
    if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php') {
        header('Location: login.php');
        exit();
    }
}

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get selected month/year from GET
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : $current_month;
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// Validate month
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = $current_month;
}

// Validate year
if ($selected_year < 2020 || $selected_year > 2100) {
    $selected_year = $current_year;
}

// Get user info
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';

// Current page
$current_page = basename($_SERVER['PHP_SELF']);

// Function to get correct relative path
function getRelativePath($target_file) {
    // Remove any leading slashes
    $target_file = ltrim($target_file, '/\\');
    
    // If target is in modules or reports folder, return as is
    if (strpos($target_file, 'modules/') === 0 || strpos($target_file, 'reports/') === 0) {
        return $target_file;
    }
    
    // For files in root directory
    return $target_file;
}

// Navigation items
$nav_items = [
    'Dashboard' => [
        'icon' => 'bi-speedometer2',
        'url' => 'index.php',
        'active' => ['index.php']
    ],
    'Add Project' => [
        'icon' => 'bi-plus-circle',
        'url' => 'modules/projects/add_project.php',
        'active' => ['add_project.php']
    ],
    'View Projects' => [
        'icon' => 'bi-list-check',
        'url' => 'modules/projects/view_projects.php',
        'active' => ['view_projects.php']
    ],
    'Rigs Management' => [
        'icon' => 'bi-truck',
        'url' => 'modules/rigs/view_rigs.php',
        'active' => ['view_rigs.php']
    ],
    'Reports' => [
        'icon' => 'bi-file-earmark-text',
        'url' => 'reports/monthly_summary.php',
        'active' => ['monthly_summary.php', 'rig_comparison.php']
    ]
];

// Check if page is active
function isNavActive($item_pages, $current_page) {
    return in_array($current_page, $item_pages);
}

// Get monthly performance for all rigs
if (function_exists('getAllRigsMonthlySummary')) {
    $monthly_summary = getAllRigsMonthlySummary($selected_month, $selected_year);
} else {
    // Temporary fallback data
    $monthly_summary = [
        [
            'rig_id' => 1,
            'rig_name' => 'Rig Alpha',
            'rig_code' => 'RA-001',
            'revenue' => 2500000,
            'expenses' => 1500000,
            'profit' => 1000000,
            'profit_margin' => 40.00,
            'project_count' => 5
        ],
        [
            'rig_id' => 2,
            'rig_name' => 'Rig Beta',
            'rig_code' => 'RB-002',
            'revenue' => 1800000,
            'expenses' => 1200000,
            'profit' => 600000,
            'profit_margin' => 33.33,
            'project_count' => 3
        ]
    ];
}

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
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> | Dashboard</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #1e3c72;
            --secondary-color: #2a5298;
            --accent-color: #4e73df;
            --success-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --sidebar-width: 250px;
            --header-height: 70px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-color);
            color: #333;
            overflow-x: hidden;
            padding-top: var(--header-height);
        }
        
        /* Top Navigation Bar - STICKY */
        .top-navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            z-index: 1030;
            padding: 0 20px;
        }
        
        .top-navbar .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-navbar .navbar-brand i {
            font-size: 1.6rem;
        }
        
        .top-navbar .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .top-navbar .nav-link:hover,
        .top-navbar .nav-link.active {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .top-navbar .nav-link i {
            margin-right: 5px;
        }
        
        /* User dropdown */
        .user-dropdown .dropdown-toggle {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50px;
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .user-dropdown .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: var(--header-height);
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: white;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
        }
        
        .sidebar-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .sidebar-header p {
            margin: 5px 0 0;
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu .nav-item {
            margin-bottom: 5px;
        }
        
        .sidebar-menu .nav-link {
            color: #5a5c69;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            color: var(--primary-color);
            background-color: #f8f9fc;
            border-left: 4px solid var(--primary-color);
        }
        
        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 10px;
            font-size: 1.1rem;
        }
        
        /* Main content area */
        .main-content {
            margin-top: var(--header-height);
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: calc(100vh - var(--header-height));
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Month selector */
        .month-selector {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .month-selector h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .date-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-form select {
            padding: 8px 15px;
            border: 1px solid #e3e6f0;
            border-radius: 6px;
            background: white;
            color: #5a5c69;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .date-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 60, 114, 0.1);
        }
        
        /* Toggle sidebar button */
        .sidebar-toggle {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Mobile responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .month-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-form {
                flex-direction: column;
                width: 100%;
            }
        }
    </style>
    
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
        
        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav class="top-navbar navbar navbar-expand-lg">
        <div class="container-fluid">
            <!-- Brand with toggle button -->
            <div class="d-flex align-items-center">
                <button class="sidebar-toggle me-3 d-lg-none" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand" href="index.php">
                    <i class="bi bi-speedometer2"></i>
                    <?php echo SITE_NAME; ?>
                </a>
            </div>
            
            <!-- Mobile toggle button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <i class="bi bi-list text-white"></i>
            </button>
            
            <!-- Right side content -->
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <!-- Current month/year indicator -->
                    <li class="nav-item me-3 d-none d-lg-block">
                        <span class="nav-link text-white">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?php echo date('F Y', strtotime("$selected_year-$selected_month-01")); ?>
                        </span>
                    </li>
                    
                    <!-- User dropdown -->
                    <li class="nav-item dropdown user-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                            <span><?php echo $user_name; ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <h6 class="dropdown-header">
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2">
                                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div><?php echo $user_name; ?></div>
                                            <small class="text-muted"><?php echo ucfirst($user_role); ?></small>
                                        </div>
                                    </div>
                                </h6>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h5>WaterLiftSolar</h5>
            <p>Rig Performance System</p>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <?php foreach ($nav_items as $label => $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isNavActive($item['active'], $current_page) ? 'active' : ''; ?>" 
                       href="<?php echo getRelativePath($item['url']); ?>">
                        <i class="bi <?php echo $item['icon']; ?>"></i>
                        <?php echo $label; ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <li class="nav-item mt-auto">
                    <hr>
                    <div class="px-3 py-2">
                        <div class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Version 1.0.0
                        </div>
                    </div>
                </li>
            </ul>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Month selector -->
        <div class="month-selector">
            <div>
                <h3>Monthly Performance: <?php echo date('F Y', strtotime("$selected_year-$selected_month-01")); ?></h3>
                <p class="text-muted mb-0">View and analyze rig performance for the selected period</p>
            </div>
            <form method="GET" class="date-form">
                <select name="month" class="form-select" onchange="this.form.submit()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <select name="year" class="form-select" onchange="this.form.submit()">
                    <?php for ($y = 2023; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </form>
        </div>

<!-- REMOVE THE DUPLICATE BOOTSTRAP CSS LINK BELOW - Keep this one -->
<!-- Bootstrap CSS CDN (add to header.php) -->
<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"> -->

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

    </main>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>