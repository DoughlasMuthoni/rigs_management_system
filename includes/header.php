<?php
// includes/header.php
// require_once '../config.php';

// Security check
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// Get user info
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = $_SESSION['full_name'] ?? 'User';

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Dashboard pages that show month selector
$dashboard_pages = ['index.php', 'monthly_summary.php', 'rig_comparison.php'];


// Navigation structure - use relative paths
$nav_items = [
    'Dashboard' => [
        'icon' => 'bi-speedometer2',
        'url' => 'index.php',  // Relative path
        'pages' => ['index.php']
    ],
    'Add Project' => [
        'icon' => 'bi-plus-circle',
        'url' => 'modules/projects/add_project.php',  // Relative path
        'pages' => ['add_project.php']
    ],
    'View Projects' => [
        'icon' => 'bi-list-check',
        'url' => 'modules/projects/view_projects.php',  // Relative path
        'pages' => ['view_projects.php']
    ],
    'Rigs Management' => [
        'icon' => 'bi-truck',
        'url' => 'modules/rigs/view_rigs.php',  // Relative path
        'pages' => ['view_rigs.php']
    ],
    'Reports' => [
        'icon' => 'bi-file-earmark-text',
        'url' => 'reports/monthly_summary.php',  // Relative path
        'pages' => ['monthly_summary.php']
    ],

    'Rig Comparison' => [
    'icon' => 'bi-bar-chart-line',
    'url' => 'rig_comparison.php',
    'pages' => ['rig_comparison.php']
],

];

// Function to check if link is active
function isActive($pages, $current_page) {
    return in_array($current_page, $pages);
}


// includes/header.php

// Function to get correct URL - FIXED VERSION
// includes/header.php

// Function to get correct URL - FIXED VERSION with proper protocol
function getUrl($relative_path) {
    // Remove any leading slashes
    $relative_path = ltrim($relative_path, '/');
    
    // If it's already a full URL, return it
    if (filter_var($relative_path, FILTER_VALIDATE_URL)) {
        return $relative_path;
    }
    
    // Get current protocol (http or https)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    
    // Get the host
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the base directory from current request
    $base_dir = dirname($_SERVER['SCRIPT_NAME']);
    
    // For your specific project structure
    $project_folder = '/waterliftsolar_rig_tracker';
    
    // Build the base URL
    $base_url = rtrim($protocol . $host . $project_folder, '/');
    
    // Remove duplicate base folder if present in relative path
    if (strpos($relative_path, 'waterliftsolar_rig_tracker/') === 0) {
        $relative_path = substr($relative_path, strlen('waterliftsolar_rig_tracker/'));
    }
    
    // Return complete URL
    return $base_url . '/' . ltrim($relative_path, '/');
}

// Alternative: Even simpler version
function getAbsoluteUrl($path) {
    // Always use http://localhost for your XAMPP environment
    $base = 'http://localhost/waterliftsolar_rig_tracker';
    
    // Remove duplicate project folder if present
    if (strpos($path, '/waterliftsolar_rig_tracker/') === 0) {
        $path = substr($path, strlen('/waterliftsolar_rig_tracker'));
    }
    
    return $base . '/' . ltrim($path, '/');
}
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
            padding-top: var(--header-height); /* Added for sticky header */
        }
        
        /* Top Navigation Bar - FIXED AND STICKY */
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
        
        /* Main content area - FIXED FOR STICKY HEADER */
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
            
            .top-navbar .navbar-nav {
                padding: 10px 0;
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
            
            // Auto-close mobile sidebar when clicking outside
            document.addEventListener('click', function(event) {
                const sidebar = document.querySelector('.sidebar');
                const sidebarToggle = document.querySelector('.sidebar-toggle');
                
                if (window.innerWidth <= 992 && 
                    !sidebar.contains(event.target) && 
                    !sidebarToggle.contains(event.target) && 
                    sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
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
                <a class="navbar-brand" href="<?php echo getUrl('index.php'); ?>">
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
        <?php 
        // Check if variables are set, otherwise use current month/year
        if (isset($selected_year) && isset($selected_month)) {
            echo date('F Y', strtotime("$selected_year-$selected_month-01"));
        } else {
            echo date('F Y');
        }
        ?>
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
                            <li><a class="dropdown-item text-danger" href="<?php echo getUrl('logout.php'); ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
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
                    <a class="nav-link <?php echo isActive($item['pages'], $current_page) ? 'active' : ''; ?>" 
                       href="<?php echo getUrl($item['url']); ?>">
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
        <?php if (in_array($current_page, $dashboard_pages)): ?>
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
        <?php else: ?>
        <!-- <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><?php 
                        $page_title = basename($_SERVER['PHP_SELF'], '.php');
                        $page_title = str_replace('_', ' ', $page_title);
                        echo ucwords($page_title);
                    ?></h1>
                    <p><?php 
                        // $descriptions = [
                        //     'add_project' => 'Add new drilling project with complete financial details',
                        //     'view_projects' => 'Browse, search, and manage all drilling projects',
                        //     'view_rigs' => 'Manage drilling rigs and their configurations',
                        //     'monthly_summary' => 'Detailed financial reports and performance analysis'
                        // ];
                        // $page_key = basename($_SERVER['PHP_SELF'], '.php');
                        // echo $descriptions[$page_key] ?? 'WaterLiftSolar Rig Performance System';
                    ?></p>
                </div>
                <div>
                    <!-- <?php if ($current_page == 'view_projects.php'): ?>
                        <a href="<?php echo getUrl('modules/projects/add_project.php'); ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add New Project
                        </a>
                    <?php endif; ?> -->
                <!-- </div>
            </div>
        </div> -->
        <?php endif; ?>
        
        <!-- Content will be loaded here -->