<?php
// Disable error display to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

include __DIR__ . '/../../OXEXfolder/config.php';
include __DIR__ . '/../../OXEXfolder/u_functions.php';
sec_session_start();

// Enhanced logging function
function logSearchError($message, $query = null) {
    error_log("Global Search Error: " . $message . 
              ($query ? " | Query: " . $query : ""));
}

// Utility function to safely execute a query
function safeExecuteQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        logSearchError("Query execution failed: " . $e->getMessage());
        return false;
    }
}

// Search function for tables
function searchTables($pdo, $searchTerm) {
    $query = "
        SELECT 
            t.tbid AS id, 
            t.tab_name AS title,
            COALESCE(t.tab_notes, 'No description available') AS description,
            'Table' AS type,
            CONCAT('sheetdetail.php?which=', t.tbid) AS url,
            (
                CASE 
                    WHEN t.tab_name LIKE ? THEN 10 
                    WHEN t.tab_notes LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            tabs_tbl t
        WHERE 
            t.tab_name LIKE ? OR
            t.tab_notes LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [
        $searchTerm, $searchTerm, 
        $searchTerm, $searchTerm
    ];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $tableResults = [];
    if ($result) {
        foreach ($result as $row) {
            $tableResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => substr($row['description'], 0, 100) . (strlen($row['description']) > 100 ? '...' : ''),
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $tableResults;
}

// Search function for categories
function searchFields($pdo, $searchTerm) {
    $query = "
        SELECT 
            st.stid AS id,
            st.str AS title,
            t.tab_name AS description,
            'Category' AS type,
            CONCAT('listtypedetail.php?which=', st.stid) AS url,
            (
                CASE 
                    WHEN st.str LIKE ? THEN 10 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            select_types st
        JOIN 
            tab_fields tf ON st.stid = tf.stid
        JOIN 
            tabs_tbl t ON tf.tbid = t.tbid
        WHERE 
            st.str LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $fieldResults = [];
    if ($result) {
        foreach ($result as $row) {
            $fieldResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $fieldResults;
}

// Search function for category values
function searchFieldValues($pdo, $searchTerm) {
    $query = "
        SELECT 
            sg.pid AS id,
            sg.select_val AS title,
            st.str AS description,
            'Category Value' AS type,
            CONCAT('listdetail.php?which=', sg.pid) AS url,
            (
                CASE 
                    WHEN sg.select_val LIKE ? THEN 10 
                    WHEN st.str LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            select_gen sg
        JOIN 
            select_types st ON sg.stid = st.stid
        WHERE 
            sg.select_val LIKE ? OR
            st.str LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $fieldValueResults = [];
    if ($result) {
        foreach ($result as $row) {
            $fieldValueResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => 'Category: ' . $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $fieldValueResults;
}

// Search function for sections
function searchSections($pdo, $searchTerm) {
    $query = "
        SELECT 
            fs.section_id AS id,
            fs.section_name AS title,
            COALESCE(t.tab_name, 'Unassigned') AS description,
            'Section' AS type,
            'sections.php' AS url,
            (
                CASE 
                    WHEN fs.section_name LIKE ? THEN 10 
                    WHEN fs.section_description LIKE ? THEN 5
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            field_sections fs
        LEFT JOIN 
            section_table_link stl ON fs.section_id = stl.section_id
        LEFT JOIN 
            tabs_tbl t ON stl.tbid = t.tbid
        WHERE 
            fs.section_name LIKE ? OR
            fs.section_description LIKE ?
        GROUP BY
            fs.section_id, fs.section_name, t.tab_name, t.tbid
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $sectionResults = [];
    if ($result) {
        foreach ($result as $row) {
            $sectionResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => substr($row['description'], 0, 100) . (strlen($row['description']) > 100 ? '...' : ''),
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $sectionResults;
}

// Search function for documentation
function searchDocumentation($pdo, $searchTerm) {
    // Check if documentation table exists (PostgreSQL syntax)
    try {
        $checkTable = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'documentation')")->fetchColumn();
        
        if (!$checkTable) {
            return [];
        }
    } catch (Exception $e) {
        // If table doesn't exist, return empty results
        return [];
    }
    
    $query = "
        SELECT 
            d.doc_id AS id,
            d.doc_title AS title,
            SUBSTRING(d.doc_content, 1, 150) AS description,
            'Documentation' AS type,
            CONCAT('view_doc.php?id=', d.doc_id) AS url,
            (
                CASE 
                    WHEN d.doc_title LIKE ? THEN 10 
                    WHEN d.doc_content LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            documentation d
        WHERE 
            d.doc_title LIKE ? OR 
            d.doc_content LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $docResults = [];
    if ($result) {
        foreach ($result as $row) {
            $docResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => substr($row['description'], 0, 100) . (strlen($row['description']) > 100 ? '...' : ''),
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $docResults;
}

// Search function for trainees
function searchTrainees($pdo, $searchTerm) {
    // Check if trainee table exists (PostgreSQL syntax)
    try {
        $checkTable = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'trainee_tbl')")->fetchColumn();
        
        if (!$checkTable) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $query = "
        SELECT 
            t.tid AS id,
            t.name AS title,
            CONCAT(t.email, ' - ', IFNULL(u.university, 'No university')) AS description,
            'Trainee' AS type,
            CONCAT('trainee.php?id=', t.tid) AS url,
            (
                CASE 
                    WHEN t.name LIKE ? THEN 10 
                    WHEN t.email LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            trainee_tbl t
        LEFT JOIN 
            uni_tbl u ON t.uid = u.uid
        WHERE 
            t.name LIKE ? OR
            t.email LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $traineeResults = [];
    if ($result) {
        foreach ($result as $row) {
            $traineeResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $traineeResults;
}

// Search function for trainee groups
function searchTraineeGroups($pdo, $searchTerm) {
    // Check if trainee_group table exists (PostgreSQL syntax)
    try {
        $checkTable = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'trainee_group')")->fetchColumn();
        
        if (!$checkTable) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $query = "
        SELECT 
            tg.tgif AS id,
            tg.group_name AS title,
            CONCAT('Group Key: ', tg.groupkey) AS description,
            'Trainee Group' AS type,
            'trainee_groups.php' AS url,
            (
                CASE 
                    WHEN tg.group_name LIKE ? THEN 10 
                    WHEN tg.groupkey LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            trainee_group tg
        WHERE 
            tg.group_name LIKE ? OR
            tg.groupkey LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $groupResults = [];
    if ($result) {
        foreach ($result as $row) {
            $groupResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $groupResults;
}

// Search function for admin users
function searchAdminUsers($pdo, $searchTerm) {
    // Check if who_there table exists (PostgreSQL syntax)
    try {
        $checkTable = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'who_there')")->fetchColumn();
        
        if (!$checkTable) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $query = "
        SELECT 
            wt.whid AS id,
            wt.realname AS title,
            CONCAT(wt.email, ' - ', wt.admintype) AS description,
            'Admin User' AS type,
            CONCAT('adminusers.php?which=', wt.whid) AS url,
            (
                CASE 
                    WHEN wt.realname LIKE ? THEN 10 
                    WHEN wt.email LIKE ? THEN 5 
                    WHEN wt.admintype LIKE ? THEN 3 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            who_there wt
        WHERE 
            wt.realname LIKE ? OR
            wt.email LIKE ? OR
            wt.admintype LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $adminResults = [];
    if ($result) {
        foreach ($result as $row) {
            $adminResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $adminResults;
}

// Search function for blog posts
function searchBlogPosts($pdo, $searchTerm) {
    // Check if blog table exists (PostgreSQL syntax)
    try {
        $checkTable = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'semantic_blog')")->fetchColumn();
        
        if (!$checkTable) {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
    
    $query = "
        SELECT 
            sb.sbid AS id,
            sb.title,
            SUBSTRING(sb.blogbody, 1, 150) AS description,
            'Blog Post' AS type,
            CONCAT('blogdetail.php?id=', sb.sbid) AS url,
            (
                CASE 
                    WHEN sb.title LIKE ? THEN 10 
                    WHEN sb.blogbody LIKE ? THEN 5 
                    ELSE 1 
                END
            ) AS relevance
        FROM 
            semantic_blog sb
        WHERE 
            sb.title LIKE ? OR
            sb.blogbody LIKE ?
        ORDER BY 
            relevance DESC
        LIMIT 20
    ";
    
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    
    $result = safeExecuteQuery($pdo, $query, $params);
    
    $blogResults = [];
    if ($result) {
        foreach ($result as $row) {
            $blogResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => strip_tags($row['description']) . '...',
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $blogResults;
}

// Main search function
function globalSearch($pdo, $query) {
    // Check login and permissions
    $admintype = isset($_SESSION['admintype']) ? $_SESSION['admintype'] : '';
    if (!login_check($pdo) || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
        return [
            'status' => 'error', 
            'message' => 'Unauthorized access'
        ];
    }

    // Validate query
    if (empty($query) || strlen($query) < 2) {
        return [
            'status' => 'error',
            'message' => 'Search query must be at least 2 characters'
        ];
    }

    try {
        $searchTerm = '%' . $query . '%';
        
        // Perform searches across different tables
        $results = array_merge(
            searchTables($pdo, $searchTerm),
            searchFields($pdo, $searchTerm),
            searchFieldValues($pdo, $searchTerm),
            searchSections($pdo, $searchTerm),
            searchDocumentation($pdo, $searchTerm),
            searchTrainees($pdo, $searchTerm),
            searchTraineeGroups($pdo, $searchTerm),
            searchAdminUsers($pdo, $searchTerm),
            searchBlogPosts($pdo, $searchTerm)
        );

        // Sort results by relevance
        usort($results, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });

        // Limit to top 50 results
        $results = array_slice($results, 0, 50);

        return [
            'status' => 'success',
            'query' => $query,
            'results' => $results,
            'count' => count($results)
        ];

    } catch (Exception $e) {
        logSearchError($e->getMessage(), $query);
        return [
            'status' => 'error',
            'message' => 'An error occurred while searching: ' . $e->getMessage()
        ];
    }
}

// Handle the search request
try {
    $query = isset($_POST['query']) ? trim($_POST['query']) : '';
    $searchResults = globalSearch($pdo, $query);
    echo json_encode($searchResults);
} catch (Exception $e) {
    logSearchError($e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Unexpected error occurred'
    ]);
}

// PDO connection is automatically closed
?> 