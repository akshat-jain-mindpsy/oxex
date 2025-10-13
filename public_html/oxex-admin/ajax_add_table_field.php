<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();

// Enable comprehensive error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '../error_log');

// Log all incoming data
error_log("AJAX Add Field Request: " . print_r($_POST, true));

// Ensure proper access control
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
if(!(login_check($pdo) == true)) {
    error_log("Authentication failed in ajax_add_table_field.php");
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit();
}

try {
    // Validate inputs with extensive logging
    $tbid = isset($_POST['which']) ? (int)$_POST['which'] : 0;
    $stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
    $$value0 = 0; // Default value for sort_order
$subtitle = "_POST['sort_order'] : 1;

    error_log("Processing field add - TBID: $tbid, STID: $stid, Sort Order: $sort_order");

    // Validate required fields
    if ($tbid <= 0) {
        throw new Exception("Invalid table ID: $tbid");
    }

    if ($stid <= 0) {
        throw new Exception("Invalid field ID: $stid");
    }

    // Check if field already exists in this table
    if ($usingSupabase) {
        $check_stmt = $supabase_pdo->prepare("SELECT COUNT(*) FROM tab_fields WHERE tbid = ? AND stid = ?");
        $check_stmt->execute([$tbid, $stid]);
        $existing_count = (int)$check_stmt->fetchColumn();
    } else {
        $existing_count = 0;
    }

    if ($existing_count > 0) {
        throw new Exception("Field already exists in this table");
    }

    // Fetch field details
    if ($usingSupabase) {
        $field_stmt = $supabase_pdo->prepare("
            SELECT str, single 
            FROM select_types 
            WHERE stid = ?
        ");
        $field_stmt->execute([$stid]);
        $row = $field_stmt->fetch(PDO::FETCH_ASSOC);
        $str = $row ? $row['str'] : '';
        $single = $row ? (int)$row['single'] : 0;
    } else {
        $str = '';
        $single = 0;
    }

    if (empty($str)) {
        throw new Exception("Field name not found for STID: $stid");
    }

    // Field type mapping
    $fieldTypes = [
        0 => 'Single Selection',
        1 => 'Multiple Selection',
        2 => 'Text',
        3 => 'Date',
        4 => 'Numeric (step 0.1)',
        5 => 'Numeric (step integer)',
        6 => 'Time'
    ];
    $listtype = $fieldTypes[$single] ?? 'Unknown';

    // Insert new field 
    if ($usingSupabase) {
        $insert_stmt = $supabase_pdo->prepare("
            INSERT INTO tab_fields (tbid, stid, sort_order) 
            VALUES (?, ?, ?)
        ");
        if (!$insert_stmt->execute([$tbid, $stid, $sort_order])) {
            throw new Exception("Insert failed");
        }
    }

    // Success response
    error_log("Field added successfully - TBID: $tbid, STID: $stid, Field Name: $str");
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Field added successfully',
        'tbid' => $tbid,
        'stid' => $stid,
        'str' => $str,
        'single' => $single,
        'listtype' => $listtype,
        '$value0 = 0; // Default value for sort_order
$subtitle = "sort_order
    ]);

} catch (Exception $e) {
    error_log("Error in ajax_add_table_field.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// PDO connection is automatically closed