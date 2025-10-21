<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Only allow authorized admins
if(!(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'DV'))) {
    echo "Not authorized.";
    exit();
}

echo "<h1>Create Test Pass Standard</h1>";

$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    echo "Database connection failed.";
    exit();
}

// Get available tables
$tables_query = "SELECT tbid, tab_name FROM tabs_tbl ORDER BY tab_name ASC";
$tables_stmt = $pdo->prepare($tables_query);
$tables_stmt->execute();
$tables = $tables_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available fields for the first table (Generic)
$fields_query = "SELECT stid, str FROM select_types ORDER BY str ASC";
$fields_stmt = $pdo->prepare($fields_query);
$fields_stmt->execute();
$fields = $fields_stmt->fetchAll(PDO::FETCH_ASSOC);

// Find the Generic table and Primary Presenting Clinical Issue field
$generic_table_id = null;
$primary_field_id = null;

foreach ($tables as $table) {
    if (strpos($table['tab_name'], 'Generic') !== false) {
        $generic_table_id = $table['tbid'];
        break;
    }
}

foreach ($fields as $field) {
    if (strpos($field['str'], 'Primary Presenting Clinical Issue') !== false) {
        $primary_field_id = $field['stid'];
        break;
    }
}

if (!$generic_table_id || !$primary_field_id) {
    echo "<p>Could not find Generic table or Primary Presenting Clinical Issue field.</p>";
    echo "<p>Available tables:</p><ul>";
    foreach ($tables as $table) {
        echo "<li>" . htmlspecialchars($table['tab_name']) . " (ID: " . $table['tbid'] . ")</li>";
    }
    echo "</ul>";
    
    echo "<p>Available fields:</p><ul>";
    foreach ($fields as $field) {
        echo "<li>" . htmlspecialchars($field['str']) . " (ID: " . $field['stid'] . ")</li>";
    }
    echo "</ul>";
    exit();
}

echo "<p>Found Generic table (ID: $generic_table_id) and Primary Presenting Clinical Issue field (ID: $primary_field_id)</p>";

// Get subfields for Primary Presenting Clinical Issue
$subfields_query = "SELECT pid, select_val FROM select_gen WHERE stid = ? ORDER BY select_val ASC";
$subfields_stmt = $pdo->prepare($subfields_query);
$subfields_stmt->execute([$primary_field_id]);
$subfields = $subfields_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Found " . count($subfields) . " subfields for Primary Presenting Clinical Issue:</p><ul>";
foreach ($subfields as $subfield) {
    echo "<li>" . htmlspecialchars($subfield['select_val']) . " (ID: " . $subfield['pid'] . ")</li>";
}
echo "</ul>";

// Find Anxiety and CPTSD/Complex Trauma subfields
$anxiety_id = null;
$cptsd_id = null;

foreach ($subfields as $subfield) {
    if (strpos($subfield['select_val'], 'Anxiety') !== false) {
        $anxiety_id = $subfield['pid'];
    }
    if (strpos($subfield['select_val'], 'CPTSD') !== false || strpos($subfield['select_val'], 'Complex Trauma') !== false) {
        $cptsd_id = $subfield['pid'];
    }
}

if (!$anxiety_id || !$cptsd_id) {
    echo "<p>Could not find Anxiety or CPTSD/Complex Trauma subfields.</p>";
    exit();
}

echo "<p>Found Anxiety (ID: $anxiety_id) and CPTSD/Complex Trauma (ID: $cptsd_id) subfields</p>";

// Create the test standard
$standard_name = "8 CBT Training Cases with Specific Presentations";
$requirement_type = "TOTAL_COUNT";
$required_value = 8;
$is_active = 1;

// Create subfield rules
$subfield_rules = [
    [
        'subfield_value' => $anxiety_id,
        'requirement_type' => 'TOTAL_COUNT',
        'specific_value' => '4'
    ],
    [
        'subfield_value' => $cptsd_id,
        'requirement_type' => 'TOTAL_COUNT',
        'specific_value' => '4'
    ]
];

$field_value = 'SUBFIELD_RULES:' . json_encode($subfield_rules);

$date_added = time();

$sql = "INSERT INTO pass_standards 
        (standard_name, tbid, stid, requirement_type, required_value, field_value, parent_standard_id, is_active, who_by, date_added) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING psid";

$stmt = $pdo->prepare($sql);
if ($stmt->execute([
    $standard_name,
    $generic_table_id,
    $primary_field_id,
    $requirement_type,
    $required_value,
    $field_value,
    null, // no parent
    $is_active,
    $usrkey,
    $date_added
])) {
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_id = $result['psid'];
    
    echo "<p><strong>✓ Successfully created test standard!</strong></p>";
    echo "<p>Standard ID: $new_id</p>";
    echo "<p>Name: $standard_name</p>";
    echo "<p>Table: Generic (ID: $generic_table_id)</p>";
    echo "<p>Field: Primary Presenting Clinical Issue (ID: $primary_field_id)</p>";
    echo "<p>Requirement: $required_value $requirement_type</p>";
    echo "<p>Subfield Rules: " . count($subfield_rules) . " rules created</p>";
    
    echo "<p><a href='view_pass_standard.php?which=$new_id'>View the new standard</a></p>";
    echo "<p><a href='pass_standards.php'>Back to Pass Standards List</a></p>";
} else {
    echo "<p><strong>✗ Failed to create test standard.</strong></p>";
    echo "<p>Error: " . $stmt->errorInfo()[2] . "</p>";
}

echo "<p><a href='pass_standards.php'>Back to Pass Standards</a></p>";
?>
