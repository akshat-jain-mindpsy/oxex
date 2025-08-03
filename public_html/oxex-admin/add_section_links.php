<?php
// File: public_html/oxex-admin/add_section_links.php

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Ensure proper access control
if(!(login_check($mysqli) == true && 
     ($admintype == 'AT' || $admintype == 'DV'))) {
    echo "Access denied.";
    exit();
}

echo "<h1>Populating section_table_link table</h1>";

// Start by checking if the field_sections table has table_ids column
$result = $mysqli->query("SHOW COLUMNS FROM field_sections LIKE 'table_ids'");
if ($result->num_rows == 0) {
    echo "<p>Error: table_ids column does not exist in field_sections table.</p>";
    exit();
}

// Get all sections
$sections_query = "SELECT section_id, table_ids FROM field_sections";
$sections_result = $mysqli->query($sections_query);

$count = 0;
$error_count = 0;

// Process each section
while ($section = $sections_result->fetch_assoc()) {
    $section_id = $section['section_id'];
    $table_ids = $section['table_ids'];
    
    if (empty($table_ids)) {
        echo "<p>Section ID $section_id has no table_ids.</p>";
        continue;
    }
    
    // Split the comma-separated list
    $tables = explode(',', $table_ids);
    $display_order = 1;
    
    foreach ($tables as $table_id) {
        $table_id = trim($table_id);
        if (!is_numeric($table_id) || $table_id <= 0) {
            echo "<p>Invalid table ID: $table_id for section $section_id.</p>";
            $error_count++;
            continue;
        }
        
        // Check if entry already exists
        $check_stmt = $mysqli->prepare("
            SELECT COUNT(*) FROM section_table_link 
            WHERE section_id = ? AND tbid = ?
        ");
        $check_stmt->bind_param("ii", $section_id, $table_id);
        $check_stmt->execute();
        $check_stmt->bind_result($exists);
        $check_stmt->fetch();
        $check_stmt->close();
        
        if ($exists) {
            echo "<p>Link already exists for section $section_id and table $table_id.</p>";
            continue;
        }
        
        // Insert the link
        $insert_stmt = $mysqli->prepare("
            INSERT INTO section_table_link (section_id, tbid, display_order) 
            VALUES (?, ?, ?)
        ");
        $insert_stmt->bind_param("iii", $section_id, $table_id, $display_order);
        
        if ($insert_stmt->execute()) {
            echo "<p>Created link for section $section_id and table $table_id.</p>";
            $count++;
        } else {
            echo "<p>Error creating link for section $section_id and table $table_id: " . $mysqli->error . "</p>";
            $error_count++;
        }
        
        $insert_stmt->close();
        $display_order++;
    }
}

echo "<h2>Summary</h2>";
echo "<p>Created $count section-table links.</p>";
echo "<p>Encountered $error_count errors.</p>";
echo "<p><a href='tabledetail.php?which=" . (isset($_GET['tbid']) ? $_GET['tbid'] : '') . "'>Return to Table Detail</a></p>";
?>