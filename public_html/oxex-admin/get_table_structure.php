<?php
// Database connection
include 'public_html/OXEXfolder/config.php';

// Get table structure
$query = "SHOW COLUMNS FROM csv_templates";
$result = $pdo->query($query);

echo "<h2>CSV Templates Table Structure</h2>";

if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
    echo "<p>Error: " . $pdo->errorInfo()[2] . "</p>";
}

$pdo = null;
?> 