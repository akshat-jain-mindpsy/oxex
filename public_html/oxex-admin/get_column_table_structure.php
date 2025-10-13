<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// Get table existence first
$query = "SELECT table_name FROM information_schema.tables WHERE table_schema = current_database() AND table_name = 'csv_template_columns'";
$result = $supabase_pdo->query($query);

echo "<h2>CSV Template Columns Table Check</h2>";

if ($result && $result->rowCount() > 0) {
    echo "<p>The csv_template_columns table exists.</p>";
    
    // Now get the structure
    $query = "SELECT column_name, data_type, is_nullable, column_key, column_default, extra FROM information_schema.columns WHERE table_name = 'csv_template_columns' AND table_schema = current_database()";
    $result = $supabase_pdo->query($query);
    
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>{$row['column_name']}</td>";
            echo "<td>{$row['data_type']}</td>";
            echo "<td>{$row['is_nullable']}</td>";
            echo "<td>{$row['column_key']}</td>";
            echo "<td>{$row['column_default']}</td>";
            echo "<td>{$row['extra']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Error: " . $supabase_pdo->errorInfo()[2] . "</p>";
    }
} else {
    echo "<p>The csv_template_columns table does not exist.</p>";
    
    // Create the table if it doesn't exist
    echo "<h3>Creating csv_template_columns table</h3>";
    
    $create_table = "CREATE TABLE csv_template_columns (
        id SERIAL PRIMARY KEY,
        template_id INT NOT NULL,
        field_id INT NOT NULL,
        table_id INT NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        FOREIGN KEY (template_id) REFERENCES csv_templates(id) ON DELETE CASCADE
    )";
    
    try {
        $supabase_pdo->exec($create_table);
        echo "<p>Table created successfully!</p>";
    } catch (PDOException $e) {
        echo "<p>Error creating table: " . $e->getMessage() . "</p>";
    }
}

// No explicit close needed with PDO
?> 