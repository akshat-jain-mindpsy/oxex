<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_components':
            $tableId = $_POST['table_id'] ?? 0;
            $components = getTableComponents($mysqli, $tableId);
            $response = [
                'status' => 'success',
                'components' => $components
            ];
            break;

        case 'get_component_details':
            $componentId = $_POST['component_id'] ?? 0;
            $details = getComponentDetails($mysqli, $componentId);
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

function getTableComponents($mysqli, $tableId) {
    $components = [];
    $stmt = $mysqli->prepare("
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
    $stmt->bind_param("i", $tableId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $components[] = $row;
    }
    
    return $components;
}

function getComponentDetails($mysqli, $componentId) {
    $stmt = $mysqli->prepare("
        SELECT 
            str as name, 
            single as type, 
            musthave as required, 
            '' as description 
        FROM select_types 
        WHERE stid = ?
    ");
    $stmt->bind_param("i", $componentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}