<?php
require_once __DIR__ . '/../config/database.php';

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Function to escape data
function escape($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Function to execute query with error handling
function query($sql) {
    global $conn;
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        // Log error but don't die in production
        error_log("SQL Error: " . mysqli_error($conn) . " in query: " . $sql);
        
        // For development, show error
        if (defined('DEVELOPMENT') && DEVELOPMENT) {
            die("Query failed: " . mysqli_error($conn) . "<br>SQL: " . htmlspecialchars($sql));
        }
    }
    
    return $result;
}

// Function to fetch single row
function fetchOne($sql) {
    $result = query($sql);
    if (!$result) return false;
    return mysqli_fetch_assoc($result);
}

// Function to fetch all rows
function fetchAll($sql) {
    $result = query($sql);
    if (!$result) return [];
    
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

// Function to get last insert ID
function lastInsertId() {
    global $conn;
    return mysqli_insert_id($conn);
}

// Function to count rows
function countRows($sql) {
    $result = query($sql);
    if (!$result) return 0;
    return mysqli_num_rows($result);
}
?>