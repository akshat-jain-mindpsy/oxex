<?php
include 'OXEXfolder/config.php';

echo "<h2>Database Test</h2>";

// Test database connection
echo "<h3>Connection Test</h3>";
if ($mysqli->ping()) {
    echo "Database connection is working.<br>";
} else {
    echo "Database connection failed.<br>";
}

// Check if pages_tbl exists
echo "<h3>Table Check</h3>";
$result = $mysqli->query("SHOW TABLES LIKE 'pages_tbl'");
if ($result->num_rows > 0) {
    echo "pages_tbl exists.<br>";
    
    // Check table structure
    echo "<h3>Table Structure</h3>";
    $result = $mysqli->query("DESCRIBE pages_tbl");
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
    echo "</pre>";
    
    // Check table content using prepared statement
    echo "<h3>Table Content</h3>";
    
    // Prepare statement
    $stmt = $mysqli->prepare("SELECT * FROM pages_tbl WHERE 1 LIMIT ?");
    if (!$stmt) {
        echo "Prepare failed: " . htmlspecialchars($mysqli->error) . "<br>";
        die("Prepare failed");
    }

    // Bind parameter
    $limit = 1;
    $stmt->bind_param("i", $limit);

    // Execute the statement
    if ($stmt->execute()) {
        // Get the results
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "<pre>";
            while ($row = $result->fetch_assoc()) {
                print_r($row);
                echo "\n--- End of Record ---\n";
            }
            echo "</pre>";
        } else {
            echo "No records in pages_tbl<br>";
        }
    } else {
        echo "Execute failed: " . htmlspecialchars($stmt->error) . "<br>";
    }

    // Close the statement
    $stmt->close();

    // Additional test with specific filename
    echo "<h3>Test Specific Page Query</h3>";
    $stmt = $mysqli->prepare("SELECT pid, page_name, googleTitle, googleDesc, googleKeywords, bannerTitle, bannerTxt, page_title, page_txt1, page_txt2, page_txt3, page_txt4, page_txt5, page_txt6, page_txt7, page_txt8, page_txt9, page_txt10, page_txt11, page_txt12, image, webp, avif FROM pages_tbl WHERE filename = ?");
    if (!$stmt) {
        echo "Prepare failed: " . htmlspecialchars($mysqli->error) . "<br>";
    } else {
        // Test with 'index.php'
        $filename = 'index.php';
        $stmt->bind_param("s", $filename);
        
        if ($stmt->execute()) {
            $stmt->store_result();
            $stmt->bind_result(
                $page_id, $page_name, $googleTitle, $googleDesc, $googleKeywords, 
                $bannerTitle, $bannerTxt, $page_title, $page_txt1, $page_txt2, 
                $page_txt3, $page_txt4, $page_txt5, $page_txt6, $page_txt7, 
                $page_txt8, $page_txt9, $page_txt10, $page_txt11, $page_txt12, 
                $image, $webp, $avif
            );

            if ($stmt->fetch()) {
                echo "<h4>Specific Page Data</h4>";
                echo "<pre>";
                echo "Page ID: $page_id\n";
                echo "Page Name: $page_name\n";
                echo "Google Title: $googleTitle\n";
                echo "Google Description: $googleDesc\n";
                echo "Google Keywords: $googleKeywords\n";
                echo "Banner Title: $bannerTitle\n";
                echo "Banner Text: $bannerTxt\n";

                // Add more fields as needed...
                echo "Page text1: $page_txt3\n";

                echo "</pre>";
            } else {
                echo "No record found for filename = 'index.php'<br>";
            }
        } else {
            echo "Execute failed: " . htmlspecialchars($stmt->error) . "<br>";
        }
        
        $stmt->close();
    }

} else {
    echo "pages_tbl does not exist.<br>";
}

// Test footer_tbl query
echo "<h3>Footer Table Test</h3>";
$stmt = $mysqli->prepare("SELECT footerl, footerm, footerr FROM footer_tbl WHERE fid = ?");
if (!$stmt) {
    echo "Prepare failed (footer): " . htmlspecialchars($mysqli->error) . "<br>";
} else {
    $fid = 1;
    $stmt->bind_param("i", $fid);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            echo "Footer data found:<br>";
            echo "<pre>";
            print_r($result->fetch_assoc());
            echo "</pre>";
        } else {
            echo "No footer data found for fid = 1<br>";
        }
    } else {
        echo "Execute failed (footer): " . htmlspecialchars($stmt->error) . "<br>";
    }
    
    $stmt->close();
}

$mysqli->close();
?>
