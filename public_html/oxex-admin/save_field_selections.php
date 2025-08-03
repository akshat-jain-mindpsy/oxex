<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
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
    $mysqli->begin_transaction();

    // First, delete existing selections for this field
    $delete_stmt = $mysqli->prepare("DELETE FROM select_gen WHERE stid = ?");
    $delete_stmt->bind_param("i", $stid);
    $delete_stmt->execute();
    $delete_stmt->close();

    // Get the field type (single value)
    $type_stmt = $mysqli->prepare("SELECT single FROM select_types WHERE stid = ?");
    $type_stmt->bind_param("i", $stid);
    $type_stmt->execute();
    $type_stmt->bind_result($single);
    $type_stmt->fetch();
    $type_stmt->close();

    // Prepare insert statement - now including select_type
    $insert_stmt = $mysqli->prepare("INSERT INTO select_gen (stid, single, select_val, select_type) VALUES (?, ?, ?, ?)");
    
    // Insert each selection
    foreach ($selections as $option) {
        $value = $option['value'];
        $select_type = $option['select_type'] ?? $single; // Use provided select_type or fall back to single value
        
        $insert_stmt->bind_param("iisi", $stid, $single, $value, $select_type);
        $insert_stmt->execute();
    }
    
    $insert_stmt->close();

    // Commit transaction
    $mysqli->commit();

    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Options saved successfully',
        'count' => count($selections)
    ]);

} catch (Exception $e) {
    // Roll back transaction on error
    $mysqli->rollback();
    
    error_log("Error saving field selections: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred while saving options: ' . $e->getMessage()
    ]);
}
?> 