<?php
// Set response header to JSON
header('Content-Type: application/json');

// Include config and functions - fix paths to use absolute paths
include_once __DIR__ . '/../../OXEXfolder/config.php';
include_once __DIR__ . '/../../OXEXfolder/u_functions.php';
sec_session_start();
include_once __DIR__ . '/../incl/sess.php';

// Debug log
error_log("get_field_options.php called");

// Check login and permissions
if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Default response
$response = [
    'status' => 'error',
    'message' => 'An unexpected error occurred'
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Check if field_id exists
if (!isset($_POST['field_id'])) {
    $response['message'] = 'Missing field ID parameter';
    echo json_encode($response);
    exit;
}

$field_id = intval($_POST['field_id']);
error_log("Field ID received: " . $field_id);

// Validate field ID
if ($field_id <= 0) {
    $response['message'] = 'Invalid field ID';
    echo json_encode($response);
    exit;
}

try {
    // First check if field_options column exists
    $check_column = $supabase_pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'select_types' AND column_name = 'field_options'");
    $column_exists = $check_column->rowCount() > 0;
    
    if (!$column_exists) {
        // Column doesn't exist yet, redirect to the database update page
        $response = [
            'status' => 'error',
            'message' => 'Database needs update. Please run the database update script first.',
            'redirect' => '../db_update.php?tbid=' . $field_id
        ];
        echo json_encode($response);
        exit;
    }
    
    // Get the field details and options
    $query = "SELECT stid, str, single, field_options FROM select_types WHERE stid = ? LIMIT 1";
    $stmt = $supabase_pdo->prepare($query);
    $stmt->execute([$field_id]);
    $field = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$field) {
        $response['message'] = 'Field not found';
        echo json_encode($response);
        exit;
    }
    
    // Check if field is a select type (0 = single, 1 = multi-select)
    if ($field['single'] != 0 && $field['single'] != 1) {
        $response['message'] = 'This field is not a selection type field';
        echo json_encode($response);
        exit;
    }
    
    // If field_options is empty, try to get options from select_gen table
    $options = $field['field_options'] ?? '';
    
    if (empty($options)) {
        // Get options from select_gen table (as in listtypedetail.php)
        $options_query = "SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val";
        $opt_stmt = $supabase_pdo->prepare($options_query);
        $opt_stmt->execute([$field_id]);
        $options_result = $opt_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($options_result) > 0) {
            $option_values = [];
            foreach ($options_result as $option) {
                $option_values[] = $option['select_val'];
            }
            $options = implode('|', $option_values);
            
            // Update the field_options column for future use
            $update = $supabase_pdo->prepare("UPDATE select_types SET field_options = ? WHERE stid = ?");
            $update->execute([$options, $field_id]);
        }
    }
    
    // Return field options
    $response = [
        'status' => 'success',
        'field_id' => $field_id,
        'field_name' => $field['str'],
        'field_type' => $field['single'],
        'options' => $options
    ];
    
    error_log("Options response: " . json_encode($response));
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Exception in get_field_options.php: " . $e->getMessage());
}

// Return response
echo json_encode($response);
exit;
?> 