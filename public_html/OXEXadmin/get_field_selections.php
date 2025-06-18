<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check authorization
if (login_check($mysqli) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get stid from POST
$stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;

// Validate input
if ($stid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid field ID'
    ]);
    exit;
}

// Fetch current selections with more detailed error handling
try {
    // First, verify the field exists and get its type
    $stmt = $mysqli->prepare("SELECT str, single FROM select_types WHERE stid = ?");
    $stmt->bind_param("i", $stid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fieldName = $row['str'];
        $fieldType = $row['single'];
        $stmt->close();
        
        // Now fetch the actual options from select_gen
        $options_stmt = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
        $options_stmt->bind_param("i", $stid);
        $options_stmt->execute();
        $options_result = $options_stmt->get_result();
        
        $selections = [];
        while ($option = $options_result->fetch_assoc()) {
            $selections[] = [
                'id' => $option['pid'],
                'value' => $option['select_val']
            ];
        }
        $options_stmt->close();

        echo json_encode([
            'status' => 'success',
            'fieldName' => $fieldName,
            'fieldType' => $fieldType,
            'selections' => $selections
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Field not found'
        ]);
        $stmt->close();
    }
} catch (Exception $e) {
    // Log the full error for server-side debugging
    error_log("Error fetching selections: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?> 