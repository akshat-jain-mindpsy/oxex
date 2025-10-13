<?php
// Disable error display to prevent HTML output before JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    include '../OXEXfolder/config.php';
    include '../OXEXfolder/p_functions.php';
    sec_session_start();
    
    // Detect DB backend
    $usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);

    // Get trainkey from session
    $trainkey = isset($_SESSION['trainkey']) ? $_SESSION['trainkey'] : null;
    
    // Fallback: if no session trainkey, try to get it from GET parameter and validate
    if (!$trainkey && isset($_GET['trainee_key'])) {
        $potential_trainkey = $_GET['trainee_key'];
        // Validate that this trainkey exists in the database
        if ($usingSupabase) {
            $stmt = $supabase_pdo->prepare('select trainkey from trainee_tbl where trainkey = ? limit 1');
            $stmt->execute([$potential_trainkey]);
            if ($stmt->fetch(PDO::FETCH_NUM)) {
                $trainkey = $potential_trainkey;
            }
        }
    }
    
    // Check user login - allow if we have a valid trainkey even if login_check fails
    if (!$trainkey || (!login_check($pdo) && !isset($_GET['trainee_key']))) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }

    // Get trainee information
    $name = '';
    if (!empty($trainkey)) {
        if ($usingSupabase) {
            $trainee_stmt = $supabase_pdo->prepare('select name from trainee_tbl where trainkey = ? limit 1');
            $trainee_stmt->execute([$trainkey]);
            $row = $trainee_stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) { $name = $row['name']; }
        }
    }

    // If name is still empty, set a default
    if (empty($name)) {
        $name = 'Unknown User';
    }

    // Get and validate request parameters
    if (!isset($_GET['table_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing table_id parameter']);
        exit;
    }

    $table_id = intval($_GET['table_id']);

    if ($table_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid table_id']);
        exit;
    }

    // Verify that the current user can access this table
    $has_access = false;
    if ($usingSupabase) {
        $table_permission_stmt = $supabase_pdo->prepare('select 1 from trainee_tab_link where trainkey = ? and tbid = ? limit 1');
        $table_permission_stmt->execute([$trainkey, $table_id]);
        $has_access = (bool)$table_permission_stmt->fetch(PDO::FETCH_NUM);
    }
    if (!$has_access) {
        echo json_encode(['status' => 'error', 'message' => 'You do not have access to this table']);
        exit;
    }

    // Get fields for the specified table
    $fields = [];
    
    // Try tab_fields + select_types join
    if ($usingSupabase) {
        $fields_stmt = $supabase_pdo->prepare('select st.stid, st.str from tab_fields tf join select_types st on tf.stid = st.stid where tf.tbid = ? order by tf.sort_order asc');
        $fields_stmt->execute([$table_id]);
        while ($field = $fields_stmt->fetch(PDO::FETCH_ASSOC)) {
            $fields[] = [ 'stid' => $field['stid'], 'str' => $field['str'] ];
        }
    }
    
    // If no fields found, try getting all select_types that might be related
    if (empty($fields)) {
        if ($usingSupabase) {
            $all_fields_stmt = $supabase_pdo->query("select stid, str from select_types where str is not null and str != '' order by str asc limit 20");
            while ($field = $all_fields_stmt->fetch(PDO::FETCH_ASSOC)) {
                $fields[] = [ 'stid' => $field['stid'], 'str' => $field['str'] ];
            }
        }
    }
    
    // If still no fields, try getting fields from trainee_log for this table
    if (empty($fields)) {
        if ($usingSupabase) {
            $log_fields_stmt = $supabase_pdo->prepare('select distinct tl.stid, st.str from trainee_log tl left join select_types st on tl.stid = st.stid where tl.tbid = ? and tl.trainkey = ? order by st.str asc');
            $log_fields_stmt->execute([$table_id, $trainkey]);
            while ($field = $log_fields_stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($field['str'])) { $fields[] = [ 'stid' => $field['stid'], 'str' => $field['str'] ]; }
            }
        }
    }
    
    // Return fields along with trainee info
    $response = [
        'status' => 'success',
        'fields' => $fields,
        'trainee_key' => $trainkey,
        'trainee_name' => $name,
        'table_id' => $table_id
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching fields: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

// No explicit close needed with PDO
?> 