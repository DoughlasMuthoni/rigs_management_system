<?php
// Remove session_start() since it should be called in the main file
session_start(); 

// Check if functions.php exists and include it
$functions_path = dirname(__FILE__) . '/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    // Try alternative path
    $functions_path = __DIR__ . '/functions.php';
    if (file_exists($functions_path)) {
        require_once $functions_path;
    }
}

// Check if user is logged in, redirect if not
// Only run if function exists to avoid errors
if (function_exists('isLoggedIn')) {
    if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'login.php') {
        header('Location: login.php');
        exit();
    }
}

// Get current month and year for dashboard
$current_month = date('m');
$current_year = date('Y');

// Get selected month/year from GET or use current
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

// Get user role for conditional displays
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';

// Define site name if not already defined
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'WaterLiftSolar');
}

// Determine current page and active nav
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> | <?php 
        if ($current_page == 'index.php') {
            echo 'Dashboard';
        } else {
            $title = str_replace(['.php', '_'], ['', ' '], $current_page);
            echo ucwords($title);
        }
    ?></title>
    
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
        }
        
        /* Top Navigation Bar */
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
            z-index: 100;
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
            padding: 10px;
            min-height: calc(100vh - var(--header-height));
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Page header */
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .page-header h1 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #6c757d;
            margin-bottom: 0;
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
            }
            
            .top-navbar .navbar-nav {
                padding: 10px 0;
            }
            
            .month-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-form {
                flex-direction: column;
                width: 100%;
            }
            
            .date-form select {
                width: 100%;
            }
        }
        
        /* Custom scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Badge styles */
        .badge {
            font-weight: 500;
            padding: 4px 8px;
        }
        
        /* Alert styles */
        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        /* Print styles */
        @media print {
            .top-navbar,
            .sidebar,
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin: 0;
                padding: 0;
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
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
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
            
            // Bootstrap tooltips
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
                    
                    <!-- Notifications (optional) -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                0
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">No new notifications</h6></li>
                        </ul>
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
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" 
                       href="index.php">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'add_project.php' ? 'active' : ''; ?>" 
                       href="modules\projects\add_project.php">
                        <i class="bi bi-plus-circle"></i>
                        Add Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_projects.php' ? 'active' : ''; ?>" 
                       href="modules/projects/view_projects.php">
                        <i class="bi bi-list-check"></i>
                        View Projects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'view_rigs.php' ? 'active' : ''; ?>" 
                       href="modules/rigs/view_rigs.php">
                        <i class="bi bi-truck"></i>
                        Rigs Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'monthly_summary.php' ? 'active' : ''; ?>" 
                       href="reports/monthly_summary.php">
                        <i class="bi bi-file-earmark-text"></i>
                        Reports
                    </a>
                </li>
                
                <?php if ($user_role == 'admin'): ?>
                <li class="nav-item mt-3">
                    <div class="nav-link text-uppercase small text-muted mb-2">
                        <i class="bi bi-shield-lock"></i>
                        Administration
                    </div>
                    <ul class="nav flex-column ps-4">
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i>
                                User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-gear"></i>
                                System Settings
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                
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
        <!-- Month/Year selector (only on dashboard pages) -->
        <?php 
        $dashboard_pages = ['index.php', 'monthly_summary.php', 'rig_comparison.php'];
        if (in_array($current_page, $dashboard_pages)): 
        ?>
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
                <?php if (isset($_GET['rig'])): ?>
                    <input type="hidden" name="rig" value="<?php echo htmlspecialchars($_GET['rig']); ?>">
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Page Header (for non-dashboard pages) -->
        <?php if (!in_array($current_page, $dashboard_pages)): ?>
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><?php 
                        $page_title = basename($_SERVER['PHP_SELF'], '.php');
                        $page_title = str_replace('_', ' ', $page_title);
                        echo ucwords($page_title);
                    ?></h1>
                    <p><?php
                        // Custom descriptions for each page
                        $descriptions = [
                            'add_project' => 'Add new drilling project with complete financial details',
                            'view_projects' => 'Browse, search, and manage all drilling projects',
                            'view_rigs' => 'Manage drilling rigs and their configurations',
                            'monthly_summary' => 'Detailed financial reports and performance analysis'
                        ];
                        $page_key = basename($_SERVER['PHP_SELF'], '.php');
                        echo $descriptions[$page_key] ?? 'WaterLiftSolar Rig Performance System';
                    ?></p>
                </div>
                <div>
                    <?php if ($current_page == 'view_projects.php'): ?>
                        <a href="add_project.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add New Project
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Content will be inserted here -->