<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Debug logging
error_log("Move fields request received: " . json_encode($_POST));

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

// Get parameters
$field_ids = isset($_POST['field_ids']) ? $_POST['field_ids'] : [];
$section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;
$table_id = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;

error_log("Parameters - field_ids: " . json_encode($field_ids) . ", section_id: $section_id, table_id: $table_id");

if (empty($field_ids) || !$section_id || !$table_id) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

try {
    $supabase_pdo->beginTransaction();
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($field_ids as $field_id) {
        $field_id = (int)$field_id;
        
        error_log("Updating field $field_id to section $section_id");
        
        // Update the section_id for this field in select_types table
        $update_query = "UPDATE select_types SET section_id = ? WHERE stid = ?";
        $stmt = $supabase_pdo->prepare($update_query);
        
        if ($stmt->execute([$section_id, $field_id])) {
            $success_count++;
            error_log("Successfully updated field $field_id");
        } else {
            $error_count++;
            error_log("Failed to update field $field_id");
        }
    }
    
    if ($error_count == 0) {
        $supabase_pdo->commit();
        
        $message = $success_count == 1 ? 
            "1 field moved successfully" : 
            "$success_count fields moved successfully";
            
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'moved_count' => $success_count
        ]);
    } else {
        $supabase_pdo->rollBack();
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => "Error moving fields. $success_count moved, $error_count failed."
        ]);
    }
    
} catch (Exception $e) {
    $supabase_pdo->rollBack();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
