<?php
header('Content-Type: application/json');

include '../../../OXEXfolder/config.php';
include '../../../OXEXfolder/u_functions.php';
sec_session_start();

// Check permissions
if (!login_check($pdo) || !in_array($GLOBALS['admintype'], ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized Access'
    ]);
    exit;
}

// Validate input
$template_id = $_POST['template_id'] ?? 0;

if (empty($template_id) || !is_numeric($template_id)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid template ID'
    ]);
    exit;
}

try {
    // Begin transaction
    $supabase_pdo->beginTransaction();

    // Delete template columns
    $column_stmt = $supabase_pdo->prepare("DELETE FROM csv_template_columns WHERE template_id = ?");
    $column_stmt->execute([$template_id]);

    // Delete template
    $template_stmt = $supabase_pdo->prepare("DELETE FROM csv_templates WHERE id = ?");
    $template_stmt->execute([$template_id]);

    // Commit transaction
    $supabase_pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Template deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $supabase_pdo->rollBack();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error deleting template: ' . $e->getMessage()
    ]);
}
?> 