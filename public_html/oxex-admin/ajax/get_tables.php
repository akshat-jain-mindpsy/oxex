<?php
header('Content-Type: application/json');

// Load Postgres/Supabase config and shared session/auth bootstrap
include_once __DIR__ . '/../../OXEXfolder/config.php';
include_once __DIR__ . '/../../OXEXfolder/u_functions.php';
sec_session_start();
include_once __DIR__ . '/../incl/sess.php';

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