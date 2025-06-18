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
if ($mysqli->query($sql)) {
    echo "Success: Record added for csv-template-download.php";
} else {
    echo "Error: " . $mysqli->error;
}

// Close connection
$mysqli->close();
?> 