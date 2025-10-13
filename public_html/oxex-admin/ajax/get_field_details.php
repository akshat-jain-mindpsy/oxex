<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();

// Check login and permissions
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Validate input
$field_id = isset($_GET['field_id']) ? (int)$_GET['field_id'] : 0;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if ($field_id <= 0 || $table_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input parameters'
    ]);
    exit;
}

try {
    // Get the field details
    $query = "
        SELECT 
            st.stid,
            st.str AS field_name,
            st.single AS field_type
        FROM 
            select_types st
        JOIN 
            tab_fields tf ON st.stid = tf.stid
        WHERE 
            st.stid = ? AND tf.tbid = ?
        LIMIT 1
    ";
    
    $stmt = $supabase_pdo->prepare($query);
    $stmt->execute([$field_id, $table_id]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$field) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Field not found'
        ]);
        exit;
    }
    
    // Get field options if it's a select field (type 0 or 1)
    $options = [];
    if ($field['field_type'] == 0 || $field['field_type'] == 1) {
        $options_query = "
            SELECT 
                select_val as option_text 
            FROM 
                select_gen 
            WHERE 
                stid = ? 
            ORDER BY 
                select_val ASC
        ";
        
        $options_stmt = $supabase_pdo->prepare($options_query);
        $options_stmt->execute([$field_id]);
        
        while ($option = $options_stmt->fetch(PDO::FETCH_ASSOC)) {
            $options[] = $option['option_text'];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'field_id' => $field_id,
        'field_name' => $field['field_name'],
        'field_type' => $field['field_type'],
        'options' => $options
    ]);
} catch (Exception $e) {
    error_log("Error getting field details: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to get field details: ' . $e->getMessage()
    ]);
}

// PDO connection is managed by config.php
?> 