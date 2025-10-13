<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check authorization
if (login_check($pdo) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
    $stmt = $supabase_pdo->prepare("SELECT str, single FROM select_types WHERE stid = ?");
    $stmt->execute([$stid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $fieldName = $row['str'];
        $fieldType = (int)$row['single'];
        
        // Now fetch the actual options from select_gen
        $options_stmt = $supabase_pdo->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val");
        $options_stmt->execute([$stid]);
        
        $selections = [];
        while ($option = $options_stmt->fetch(PDO::FETCH_ASSOC)) {
            $selections[] = [
                'id' => (int)$option['pid'],
                'value' => $option['select_val']
            ];
        }

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
    }
} catch (PDOException $e) {
    // Log the full error for server-side debugging
    error_log("Error fetching selections: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
}
?> 