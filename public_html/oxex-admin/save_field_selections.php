<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    die(json_encode(['status' => 'error', 'message' => 'Not authorized']));
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['stid']) || !isset($input['selections'])) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid input']));
}

$stid = (int)$input['stid'];
$selections = $input['selections'];

// Validate selections array
if (!is_array($selections) || empty($selections)) {
    die(json_encode(['status' => 'error', 'message' => 'No selections provided']));
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, delete existing selections for this field
    $delete_stmt = $pdo->prepare("DELETE FROM select_gen WHERE stid = ?");
    $delete_stmt->execute([$stid]);
    $delete_stmt->closeCursor();

    // Get the field type (single value)
    $type_stmt = $pdo->prepare("SELECT single FROM select_types WHERE stid = ?");
    $type_stmt->execute([$stid]);
    $single = (int)$type_stmt->fetchColumn();
    $type_stmt->closeCursor();

    // Prepare insert statement - now including select_type
    $insert_stmt = $pdo->prepare("INSERT INTO select_gen (stid, single, select_val, select_type) VALUES (?, ?, ?, ?)");
    
    // Insert each selection
    foreach ($selections as $option) {
        $value = $option['value'];
        $select_type = $option['select_type'] ?? $single; // Use provided select_type or fall back to single value
        
        $insert_stmt->execute([$stid, $single, $value, $select_type]);
    }
    
    $insert_stmt->closeCursor();

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Options saved successfully',
        'count' => count($selections)
    ]);

} catch (Exception $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    
    error_log("Error saving field selections: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred while saving options: ' . $e->getMessage()
    ]);
}
?> 