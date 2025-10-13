<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check if user is logged in and has appropriate permissions
if (login_check($pdo) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate input
if (!isset($data['tbid']) || !isset($data['changes']) || !is_array($data['changes'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid input'
    ]);
    exit;
}

$tbid = (int)$data['tbid'];
$changes = $data['changes'];
$optionsChanges = isset($data['optionsChanges']) ? $data['optionsChanges'] : [];

// Start transaction
// Use PDO transaction and statements
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No database connection available'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE select_types SET str = COALESCE(?, str), single = COALESCE(?, single) WHERE stid = ?");

    foreach ($changes as $stid => $change) {
        $name = isset($change['name']) ? $change['name'] : null;
        $type = isset($change['type']) ? (int)$change['type'] : null;

        if ($type !== null) {
            $type_check_stmt = $pdo->prepare("SELECT single FROM select_types WHERE stid = ?");
            $type_check_stmt->execute([(int)$stid]);
            $row = $type_check_stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $current_type = (int)$row['single'];
                if (($type === 0 || $type === 1) && !($current_type === 0 || $current_type === 1)) {
                    $clear_stmt = $pdo->prepare("DELETE FROM select_gen WHERE stid = ?");
                    $clear_stmt->execute([(int)$stid]);
                }
                if (!($type === 0 || $type === 1) && ($current_type === 0 || $current_type === 1)) {
                    $clear_stmt = $pdo->prepare("DELETE FROM select_gen WHERE stid = ?");
                    $clear_stmt->execute([(int)$stid]);
                }
            }
        }

        $stmt->execute([$name, $type, (int)$stid]);
    }

    if (!empty($optionsChanges)) {
        foreach ($optionsChanges as $stid => $options) {
            $type_stmt = $pdo->prepare("SELECT single FROM select_types WHERE stid = ?");
            $type_stmt->execute([(int)$stid]);
            $row = $type_stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $single = (int)$row['single'];
            } else {
                throw new Exception("Field not found: $stid");
            }

            $existing_stmt = $pdo->prepare("SELECT pid FROM select_gen WHERE stid = ?");
            $existing_stmt->execute([(int)$stid]);
            $existing_ids = [];
            while ($r = $existing_stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing_ids[] = (int)$r['pid'];
            }

            $new_ids = [];
            foreach ($options as $option) {
                if (isset($option['id']) && $option['id'] > 0) {
                    $new_ids[] = (int)$option['id'];
                }
            }

            $to_delete = array_diff($existing_ids, $new_ids);
            if (!empty($to_delete)) {
                $placeholders = implode(',', array_fill(0, count($to_delete), '?'));
                $delete_sql = "DELETE FROM select_gen WHERE pid IN ($placeholders)";
                $delete_stmt = $pdo->prepare($delete_sql);
                $delete_stmt->execute(array_values($to_delete));
            }

            $update_stmt = $pdo->prepare("UPDATE select_gen SET select_val = ? WHERE pid = ?");
            $insert_stmt = $pdo->prepare("INSERT INTO select_gen (stid, single, select_val) VALUES (?, ?, ?)");

            foreach ($options as $option) {
                $value = isset($option['value']) ? trim($option['value']) : '';
                if ($value === '') continue;
                if (isset($option['id']) && $option['id'] > 0) {
                    $update_stmt->execute([$value, (int)$option['id']]);
                } else {
                    $insert_stmt->execute([(int)$stid, $single, $value]);
                }
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => count($changes) . ' field(s) updated successfully'
    ]);
} catch (Exception $e) {
    if (isset($pdo)) { $pdo->rollBack(); }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?> 