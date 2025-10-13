<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

header('Content-Type: application/json');

$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_components':
            $tableId = $_POST['table_id'] ?? 0;
            $components = getTableComponents($usingSupabase, $tableId);
            $response = [
                'status' => 'success',
                'components' => $components
            ];
            break;

        case 'get_component_details':
            $componentId = $_POST['component_id'] ?? 0;
            $details = getComponentDetails($usingSupabase, $componentId);
            $response = [
                'status' => 'success',
                'name' => $details['name'],
                'type' => $details['type'],
                'required' => $details['required'],
                'description' => $details['description']
            ];
            break;
    }
}

echo json_encode($response);

function getTableComponents($usingSupabase, $tableId) {
    $components = [];
    if ($usingSupabase) {
        $stmt = $supabase_pdo->prepare("
            SELECT 
                st.stid as id, 
                st.str as name, 
                CASE 
                    WHEN st.single = 0 THEN 'Single Selection'
                    WHEN st.single = 1 THEN 'Multiple Selection'
                    WHEN st.single = 2 THEN 'Text'
                    WHEN st.single = 3 THEN 'Date'
                    WHEN st.single = 4 THEN 'Numeric (0.1)'
                    WHEN st.single = 5 THEN 'Numeric (Integer)'
                    WHEN st.single = 6 THEN 'Time'
                END as type,
                st.musthave as required
            FROM tab_fields tf
            JOIN select_types st ON tf.stid = st.stid
            WHERE tf.tbid = ?
        ");
        $stmt->execute([$tableId]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return $components;
}

function getComponentDetails($usingSupabase, $componentId) {
    if ($usingSupabase) {
        $stmt = $supabase_pdo->prepare("
            SELECT 
                str as name, 
                single as type, 
                musthave as required, 
                '' as description 
            FROM select_types 
            WHERE stid = ?
        ");
        $stmt->execute([$componentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: ['name' => '', 'type' => 0, 'required' => 0, 'description' => ''];
    }
    
    return ['name' => '', 'type' => 0, 'required' => 0, 'description' => ''];
}