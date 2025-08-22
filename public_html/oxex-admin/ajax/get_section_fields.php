<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
     ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || 
      $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV'))) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit();
}

// Get parameters
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$table_id = isset($_GET['table_id']) ? (int)$_GET['table_id'] : 0;

if (!$section_id || !$table_id) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit();
}

try {
    // Get fields currently in this section for this table
    $query = "
        SELECT 
            st.stid,
            st.str AS field_name,
            st.single AS field_type,
            CASE 
                WHEN st.single = 0 THEN 'Single Selection'
                WHEN st.single = 1 THEN 'Multiple Selection'
                WHEN st.single = 2 THEN 'Text'
                WHEN st.single = 3 THEN 'Date'
                WHEN st.single = 4 THEN 'Numeric (step 0.1)'
                WHEN st.single = 5 THEN 'Numeric (step integer)'
                WHEN st.single = 6 THEN 'Time'
                ELSE 'Unknown'
            END AS field_type_name
        FROM select_types st
        JOIN tab_fields tf ON st.stid = tf.stid
        WHERE st.section_id = ? AND tf.tbid = ?
        ORDER BY tf.sort_order ASC, st.str ASC
    ";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ii", $section_id, $table_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row;
    }
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'fields' => $fields
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
