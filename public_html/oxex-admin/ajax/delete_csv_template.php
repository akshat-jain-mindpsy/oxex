<?php
header('Content-Type: application/json');

include '../../../OXEXfolder/config.php';
include '../../../OXEXfolder/u_functions.php';
sec_session_start();

// Check permissions
if (!login_check($mysqli) || !in_array($GLOBALS['admintype'], ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
    $mysqli->begin_transaction();

    // Delete template columns
    $column_stmt = $mysqli->prepare("DELETE FROM csv_template_columns WHERE template_id = ?");
    $column_stmt->bind_param("i", $template_id);
    $column_stmt->execute();

    // Delete template
    $template_stmt = $mysqli->prepare("DELETE FROM csv_templates WHERE id = ?");
    $template_stmt->bind_param("i", $template_id);
    $template_stmt->execute();

    // Commit transaction
    $mysqli->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Template deleted successfully'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();

    echo json_encode([
        'status' => 'error',
        'message' => 'Error deleting template: ' . $e->getMessage()
    ]);
}

$mysqli->close();
?> 