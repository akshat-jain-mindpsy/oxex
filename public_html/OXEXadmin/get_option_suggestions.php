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

// Get stid and search term from POST
$stid = isset($_POST['stid']) ? (int)$_POST['stid'] : 0;
$term = isset($_POST['term']) ? trim($_POST['term']) : '';

// Validate input
if ($stid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid field ID'
    ]);
    exit;
}

try {
    // Get field name from stid
    $field_stmt = $mysqli->prepare("SELECT str FROM select_types WHERE stid = ?");
    $field_stmt->bind_param("i", $stid);
    $field_stmt->execute();
    $field_stmt->bind_result($field_name);
    $field_stmt->fetch();
    $field_stmt->close();
    
    // Prepare suggestions query
    $suggestions = [];
    
    if (!empty($term)) {
        // Search for existing options that match the term
        $search_term = "%" . $term . "%";
        
        // First check options specific to this field
        $options_stmt = $mysqli->prepare("
            SELECT select_val, COUNT(*) as count 
            FROM select_gen 
            WHERE stid = ? AND select_val LIKE ? 
            GROUP BY select_val
            ORDER BY count DESC
            LIMIT 5
        ");
        $options_stmt->bind_param("is", $stid, $search_term);
        $options_stmt->execute();
        $result = $options_stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = [
                'value' => $row['select_val'],
                'count' => $row['count']
            ];
        }
        $options_stmt->close();
        
        // If we don't have enough suggestions, search across all fields
        if (count($suggestions) < 5) {
            $global_stmt = $mysqli->prepare("
                SELECT select_val, COUNT(*) as count 
                FROM select_gen 
                WHERE select_val LIKE ? AND stid != ?
                GROUP BY select_val
                ORDER BY count DESC
                LIMIT ?
            ");
            $limit = 5 - count($suggestions);
            $global_stmt->bind_param("sii", $search_term, $stid, $limit);
            $global_stmt->execute();
            $global_result = $global_stmt->get_result();
            
            while ($row = $global_result->fetch_assoc()) {
                $suggestions[] = [
                    'value' => $row['select_val'],
                    'count' => $row['count']
                ];
            }
            $global_stmt->close();
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'suggestions' => $suggestions
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching suggestions: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while fetching suggestions'
    ]);
}
?> 