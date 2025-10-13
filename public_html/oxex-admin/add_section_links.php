<?php
// File: public_html/oxex-admin/add_section_links.php

include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Ensure proper access control
if(!(login_check($pdo) == true && 
     ($admintype == 'AT' || $admintype == 'DV'))) {
    echo "Access denied.";
    exit();
}

echo "<h1>Populating section_table_link table</h1>";

// Start by checking if the field_sections table has table_ids column
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    echo "<p>Error: No database connection available.</p>";
    exit();
}

try {
    $result = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'field_sections' AND column_name = 'table_ids'");
    if ($result->rowCount() == 0) {
        echo "<p>Error: table_ids column does not exist in field_sections table.</p>";
        exit();
    }
} catch (Throwable $e) {
    echo "<p>Error checking table structure: " . $e->getMessage() . "</p>";
    exit();
}

// Get all sections
$sections_query = "SELECT section_id, table_ids FROM field_sections";
$sections_result = $pdo->query($sections_query);

$count = 0;
$error_count = 0;

// Process each section
while ($section = $sections_result->fetch(PDO::FETCH_ASSOC)) {
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
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM section_table_link 
            WHERE section_id = ? AND tbid = ?
        ");
        $check_stmt->execute([$section_id, $table_id]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists) {
            echo "<p>Link already exists for section $section_id and table $table_id.</p>";
            continue;
        }
        
        // Insert the link
        $insert_stmt = $pdo->prepare("
            INSERT INTO section_table_link (section_id, tbid, display_order) 
            VALUES (?, ?, ?)
        ");
        
        if ($insert_stmt->execute([$section_id, $table_id, $display_order])) {
            echo "<p>Created link for section $section_id and table $table_id.</p>";
            $count++;
        } else {
            echo "<p>Error creating link for section $section_id and table $table_id: " . implode(', ', $insert_stmt->errorInfo()) . "</p>";
            $error_count++;
        }
        $display_order++;
    }
}

echo "<h2>Summary</h2>";
echo "<p>Created $count section-table links.</p>";
echo "<p>Encountered $error_count errors.</p>";
echo "<p><a href='sheetdetail.php?which=" . (isset($_GET['tbid']) ? $_GET['tbid'] : '') . "'>Return to Sheet Detail</a></p>";
?>