<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
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
    // Get fields that can be moved to this section (not already in this section)
    $query = "
        SELECT 
            st.stid,
            st.str AS field_name,
            st.single AS field_type,
            COALESCE(fs.section_name, 'Unsectioned') AS current_section
        FROM select_types st
        JOIN tab_fields tf ON st.stid = tf.stid
        LEFT JOIN field_sections fs ON st.section_id = fs.section_id
        WHERE tf.tbid = ? AND (st.section_id != ? OR st.section_id IS NULL)
        ORDER BY st.str ASC
    ";
    
    $stmt = $supabase_pdo->prepare($query);
    $stmt->execute([$table_id, $section_id]);
    
    $fields = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $fields[] = $row;
    }
    
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
