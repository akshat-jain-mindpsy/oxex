<?php
// File: public_html/oxex-admin/migrate_sections.php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';

// Check proper access control
if(!(login_check($pdo) == true && $admintype == 'AT')) {
    echo "You are not authorized to run this script.";
    exit();
}

// Initialize output
$output = '';

// Get all sections
$sections_result = $pdo->query("SELECT section_id, table_ids FROM field_sections");

if ($sections_result->rowCount() > 0) {
    while ($section = $sections_result->fetch(PDO::FETCH_ASSOC)) {
        $section_id = $section['section_id'];
        $table_ids_str = $section['table_ids'];
        
        // Skip if no table_ids defined
        if (empty($table_ids_str)) {
            $output .= "Section ID $section_id has no table_ids defined. Skipping.<br>";
            continue;
        }
        
        // Parse table IDs
        $table_ids = explode(',', $table_ids_str);
        
        $order = 0;
        foreach ($table_ids as $tbid) {
            $tbid = (int)$tbid;
            if ($tbid <= 0) continue;
            
            // Check if record already exists
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM section_table_link WHERE section_id = ? AND tbid = ?");
            $check_stmt->execute([$section_id, $tbid]);
            $count = $check_stmt->fetchColumn();
            $check_stmt->closeCursor();
            
            if ($count > 0) {
                $output .= "Link between section $section_id and table $tbid already exists. Skipping.<br>";
                continue;
            }
            
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO section_table_link (section_id, tbid, display_order) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$section_id, $tbid, $order])) {
                $output .= "Added section $section_id to table $tbid with order $order<br>";
            } else {
                $output .= "Error adding section $section_id to table $tbid: " . $stmt->errorInfo()[2] . "<br>";
            }
            
            $stmt->closeCursor();
            $order++;
        }
    }
    
    $output .= "<p><strong>Migration complete!</strong></p>";
} else {
    $output .= "No sections found to migrate.";
}

// Output HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrate Sections</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Section Table Migration</h1>
        <div class="card">
            <div class="card-header">Results</div>
            <div class="card-body">
                <?php echo $output; ?>
</div>
        
        <div class="mt-4">
            <a href="sections.php" class="btn btn-primary">Back to Sections</a>
</div>
</body>
</html>