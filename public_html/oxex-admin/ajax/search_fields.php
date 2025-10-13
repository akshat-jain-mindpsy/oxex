<?php
include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_log("Search fields request received: " . print_r($_GET, true));

// Check authorization
if (login_check($pdo) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get parameters
$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// Validate input
if (empty($term) || strlen($term) < 2) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Search term must be at least 2 characters'
    ]);
    exit;
}

try {
    // Convert term to search pattern
    $search_pattern = "%$term%";
    
    // Find fields that match the search term and are not already in the section
    // Use a simpler query that's more likely to work in any MySQL setup
    $query = "
        SELECT stid, str, single 
        FROM select_types 
        WHERE str LIKE ? 
          AND (section_id IS NULL OR section_id != ? OR section_id = 0)
        ORDER BY str ASC
        LIMIT 20
    ";
    
    $stmt = $supabase_pdo->prepare($query);
    $stmt->execute([$search_pattern, $section_id]);
    
    $fields = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get field type text
        $type_text = '';
        switch ($row['single']) {
            case 0: $type_text = 'Single Select'; break;
            case 1: $type_text = 'Multi Select'; break;
            case 2: $type_text = 'Text'; break;
            case 3: $type_text = 'Date'; break;
            case 4: $type_text = 'Numeric (0.1)'; break;
            case 5: $type_text = 'Numeric (Integer)'; break;
            case 6: $type_text = 'Time'; break;
            default: $type_text = 'Unknown'; break;
        }
        
        $fields[] = [
            'stid' => $row['stid'],
            'str' => $row['str'],
            'single' => $row['single'],
            'type_text' => $type_text
        ];
    }
    
    error_log("Found " . count($fields) . " matching fields");
    
    echo json_encode([
        'status' => 'success',
        'fields' => $fields
    ]);
    
} catch (Exception $e) {
    error_log("Error in search_fields.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>