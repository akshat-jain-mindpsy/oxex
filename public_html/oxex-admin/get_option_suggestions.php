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
    $field_stmt = $pdo->prepare("SELECT str FROM select_types WHERE stid = ?");
    $field_stmt->execute([$stid]);
    $field_name = $field_stmt->fetchColumn();
    $field_stmt->closeCursor();
    
    // Prepare suggestions query
    $suggestions = [];
    
    if (!empty($term)) {
        // Search for existing options that match the term
        $search_term = "%" . $term . "%";
        
        // First check options specific to this field
        $options_stmt = $pdo->prepare("
            SELECT select_val, COUNT(*) as count 
            FROM select_gen 
            WHERE stid = ? AND select_val LIKE ? 
            GROUP BY select_val
            ORDER BY count DESC
            LIMIT 5
        ");
        $options_stmt->execute([$stid, $search_term]);
        
        while ($row = $options_stmt->fetch(PDO::FETCH_ASSOC)) {
            $suggestions[] = [
                'value' => $row['select_val'],
                'count' => $row['count']
            ];
        }
        $options_stmt->closeCursor();
        
        // If we don't have enough suggestions, search across all fields
        if (count($suggestions) < 5) {
            $global_stmt = $pdo->prepare("
                SELECT select_val, COUNT(*) as count 
                FROM select_gen 
                WHERE select_val LIKE ? AND stid != ?
                GROUP BY select_val
                ORDER BY count DESC
                LIMIT ?
            ");
            $limit = 5 - count($suggestions);
            $global_stmt->execute([$search_term, $stid, $limit]);
            
            while ($row = $global_stmt->fetch(PDO::FETCH_ASSOC)) {
                $suggestions[] = [
                    'value' => $row['select_val'],
                    'count' => $row['count']
                ];
            }
            $global_stmt->closeCursor();
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