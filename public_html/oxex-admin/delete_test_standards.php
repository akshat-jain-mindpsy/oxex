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

echo "<h1>Delete Test Pass Standards</h1>";

$pdo = (isset($supabase_pdo) && $supabase_pdo instanceof PDO) ? $supabase_pdo : null;
if (!$pdo) {
    echo "Database connection failed.";
    exit();
}

// Look for standards with names containing "8 CBT Training Cases with Specific Presentations"
$query = "SELECT psid, standard_name, requirement_type, required_value, is_active, date_added FROM pass_standards WHERE standard_name LIKE ? ORDER BY date_added DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['%8 CBT Training Cases with Specific Presentations%']);

$standards = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($standards)) {
    echo "<p>No standards found with that name pattern.</p>";
    echo "<p><a href='pass_standards.php'>Back to Pass Standards</a></p>";
    exit();
}

echo "<h2>Found " . count($standards) . " matching standards:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Value</th><th>Active</th><th>Date Added</th><th>Action</th></tr>";

foreach ($standards as $standard) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($standard['psid']) . "</td>";
    echo "<td>" . htmlspecialchars($standard['standard_name']) . "</td>";
    echo "<td>" . htmlspecialchars($standard['requirement_type']) . "</td>";
    echo "<td>" . htmlspecialchars($standard['required_value']) . "</td>";
    echo "<td>" . ($standard['is_active'] ? 'Yes' : 'No') . "</td>";
    echo "<td>" . ($standard['date_added'] ? date('Y-m-d H:i:s', $standard['date_added']) : 'N/A') . "</td>";
    echo "<td><a href='pass_standards.php?del=del&which=" . $standard['psid'] . "' onclick='return confirm(\"Are you sure you want to delete this standard?\");'>Delete</a></td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Or delete all matching standards at once:</h2>";
echo "<form method='post' onsubmit='return confirm(\"Are you sure you want to delete ALL matching standards? This cannot be undone!\");'>";
echo "<input type='hidden' name='action' value='delete_all'>";
echo "<button type='submit' style='background: red; color: white; padding: 10px; border: none; cursor: pointer;'>Delete All Matching Standards</button>";
echo "</form>";

// Handle bulk delete
if (isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    echo "<h2>Deleting all matching standards...</h2>";
    
    $deleted_count = 0;
    foreach ($standards as $standard) {
        $delete_stmt = $pdo->prepare("DELETE FROM pass_standards WHERE psid = ?");
        if ($delete_stmt->execute([$standard['psid']])) {
            $deleted_count++;
            echo "<p>✓ Deleted standard ID " . $standard['psid'] . ": " . htmlspecialchars($standard['standard_name']) . "</p>";
        } else {
            echo "<p>✗ Failed to delete standard ID " . $standard['psid'] . "</p>";
        }
    }
    
    echo "<p><strong>Deleted " . $deleted_count . " out of " . count($standards) . " standards.</strong></p>";
    echo "<p><a href='pass_standards.php'>Back to Pass Standards</a></p>";
} else {
    echo "<p><a href='pass_standards.php'>Back to Pass Standards</a></p>";
}
?>
