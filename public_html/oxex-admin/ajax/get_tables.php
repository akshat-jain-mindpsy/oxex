<?php
header('Content-Type: application/json');

// Include necessary configuration and database connection
require_once('../config.php');

// Check user authentication and permissions
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Prepare and execute query to fetch tables
    $stmt = $pdo->prepare("
        SELECT tbid, name 
        FROM tab_notes 
        WHERE active = 1 
        ORDER BY name ASC
    ");
    $stmt->execute();
    
    // Fetch all tables
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode($tables);
} catch (PDOException $e) {
    // Log error and return error response
    error_log('Error fetching tables: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch tables']);
}
exit;
?> 