<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// Current timestamp
$current_time = time();

// SQL Query to insert the new page record
$sql = "INSERT INTO pages_tbl 
    (filename, page_name, googleTitle, googleDesc, googleKeywords, page_title, date_added, date_modified, google_priority) 
    VALUES 
    ('csv-template-download.php', 'CSV Template Download', 'OXEX CSV Template Download', 
    'Download your data in CSV format using customizable templates', 
    'OXEX, CSV, template, download, data export', 
    'CSV Template Download', $current_time, $current_time, 0.5)";

// Execute the query
$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if ($pdo) {
    try {
        $pdo->exec($sql);
        echo "Success: Record added for csv-template-download.php";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Error: No database connection available";
}
?> 