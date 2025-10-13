<?php
require_once '../../OXEXfolder/config.php';
require_once '../../OXEXfolder/u_functions.php';
sec_session_start();

// Basic security check
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    // Check for other admin privileges as fallback
    if (isset($_SESSION['admintype']) && in_array($_SESSION['admintype'], ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        // Allow access if admin type is valid
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized access. Please login as admin.'
        ]);
        exit();
    }
}

// Check required parameters
if (!isset($_POST['sections']) || !is_array($_POST['sections'])) {
    error_log("Missing sections parameter. POST data: " . json_encode($_POST));
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameters: sections array'
    ]);
    exit();
}

$sections = $_POST['sections'];
error_log("Received sections to update: " . json_encode($sections));

// Start transaction
$pdo->beginTransaction();

try {
    // Update section order for each section
    foreach ($sections as $order => $section_id) {
        $section_id = (int)$section_id;
        $order = (int)$order + 1; // Start at 1, not 0
        
        $update_stmt = $pdo->prepare("UPDATE field_sections SET section_order = ? WHERE section_id = ?");
        $update_stmt->execute([$order, $section_id]);
        
        if ($update_stmt->rowCount() < 0) {
            throw new Exception("Failed to update section order for section ID: $section_id");
        }
        $update_stmt->closeCursor();
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Return success
    error_log("Section order updated successfully for sections: " . json_encode($sections));
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Section order updated successfully'
    ]);
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction
    $pdo->rollBack();
    
    // Log the error for debugging
    error_log("Error in update_section_order.php: " . $e->getMessage());
    
    // Return error
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit();
}
?>
