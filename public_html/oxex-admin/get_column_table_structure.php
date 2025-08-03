<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// Get table existence first
$query = "SHOW TABLES LIKE 'csv_template_columns'";
$result = $mysqli->query($query);

echo "<h2>CSV Template Columns Table Check</h2>";

if ($result && $result->num_rows > 0) {
    echo "<p>The csv_template_columns table exists.</p>";
    
    // Now get the structure
    $query = "SHOW COLUMNS FROM csv_template_columns";
    $result = $mysqli->query($query);
    
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Error: " . $mysqli->error . "</p>";
    }
} else {
    echo "<p>The csv_template_columns table does not exist.</p>";
    
    // Create the table if it doesn't exist
    echo "<h3>Creating csv_template_columns table</h3>";
    
    $create_table = "CREATE TABLE csv_template_columns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        field_id INT NOT NULL,
        table_id INT NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE
    )";
    
    if ($mysqli->query($create_table)) {
        echo "<p>Table created successfully!</p>";
    } else {
        echo "<p>Error creating table: " . $mysqli->error . "</p>";
    }
}

$mysqli->close();
?> 