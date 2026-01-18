<?php
// includes/init.php

// Set selected month/year for all pages
if (!isset($selected_month)) {
    $selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
}
if (!isset($selected_year)) {
    $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
}

// Validate month
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
}

// Validate year
if ($selected_year < 2020 || $selected_year > 2100) {
    $selected_year = date('Y');
}

// Set user info if session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$current_page = basename($_SERVER['PHP_SELF']);