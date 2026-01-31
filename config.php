<?php
// config.php
// Define absolute paths
define('ROOT_PATH', dirname(__FILE__));
// Get the project folder name
$script_path = $_SERVER['PHP_SELF'];
$project_folder = dirname($script_path);

// Remove any subdirectories to get just the project root
$project_root = '/' . basename(dirname(__FILE__));

// Set BASE_URL to just the project root
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $project_root);

// Define directory constants
define('INCLUDES_DIR', ROOT_PATH . '/includes');
define('MODULES_DIR', ROOT_PATH . '/modules');
define('REPORTS_DIR', ROOT_PATH . '/reports');
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('EXPENSE_RECEIPTS_PATH', UPLOAD_PATH . 'expenses/');
// Function to include files safely
function includeSafe($file_path) {
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    
    // Try relative to root
    $root_path = dirname(__DIR__) . '/' . ltrim($file_path, '/');
    if (file_exists($root_path)) {
        require_once $root_path;
        return true;
    }
    
    // Try from current directory
    $current_path = __DIR__ . '/' . ltrim($file_path, '/');
    if (file_exists($current_path)) {
        require_once $current_path;
        return true;
    }
    
    error_log("Failed to include file: " . $file_path);
    return false;
}
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/// Auto-load functions
$functions_path = INCLUDES_DIR . '/functions.php';
if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    // Try alternative path
    $functions_path = ROOT_PATH . '/includes/functions.php';
    if (file_exists($functions_path)) {
        require_once $functions_path;
    } else {
        // Don't die here, just log error
        error_log("Functions.php not found at: " . $functions_path);
    }
}

?>