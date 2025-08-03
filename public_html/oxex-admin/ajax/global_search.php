<?php
header('Content-Type: application/json');

include '../../OXEXfolder/config.php';
include '../../OXEXfolder/u_functions.php';
sec_session_start();
include '../incl/sess.php';

// Enhanced logging function
function logSearchError($message, $query = null) {
    error_log("Global Search Error: " . $message . 
              ($query ? " | Query: " . $query : ""));
}

// Utility function to safely execute a query
function safeExecuteQuery($mysqli, $query, $params = [], $paramTypes = '') {
    try {
        $stmt = $mysqli->prepare($query);
        
        if (!empty($params)) {
            $bindParams = array_merge([$paramTypes], $params);
            $bindParamRefs = [];
            foreach ($bindParams as $key => $value) {
                $bindParamRefs[$key] = &$bindParams[$key];
            }
            call_user_func_array([$stmt, 'bind_param'], $bindParamRefs);
        }
        
        $stmt->execute();
        return $stmt->get_result();
    } catch (Exception $e) {
        logSearchError("Query execution failed: " . $e->getMessage());
        return false;
    }
}

// Search function for tables
function searchTables($mysqli, $searchTerm) {
    $query = "
        SELECT 
            t.tbid AS id, 
            t.tab_name AS title,
            COALESCE(t.tab_notes, 'No description available') AS description,
            'Table' AS type,
            CONCAT('tabledetail.php?which=', t.tbid) AS url,
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $tableResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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

// Search function for fields
function searchFields($mysqli, $searchTerm) {
    $query = "
        SELECT 
            st.stid AS id,
            st.str AS title,
            t.tab_name AS description,
            'Field' AS type,
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ss');
    
    $fieldResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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

// Search function for field values
function searchFieldValues($mysqli, $searchTerm) {
    $query = "
        SELECT 
            sg.pid AS id,
            sg.select_val AS title,
            st.str AS description,
            'Field Value' AS type,
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $fieldValueResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $fieldValueResults[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'excerpt' => 'Field: ' . $row['description'],
                'url' => $row['url'],
                'type' => $row['type'],
                'relevance' => $row['relevance']
            ];
        }
    }
    
    return $fieldValueResults;
}

// Search function for sections
function searchSections($mysqli, $searchTerm) {
    $query = "
        SELECT 
            fs.section_id AS id,
            fs.section_name AS title,
            COALESCE(t.tab_name, 'Unassigned') AS description,
            'Section' AS type,
            'tabsections.php' AS url,
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $sectionResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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
function searchDocumentation($mysqli, $searchTerm) {
    // Check if documentation table exists
    $checkTable = $mysqli->query("SHOW TABLES LIKE 'documentation'")->num_rows;
    
    if ($checkTable == 0) {
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $docResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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
function searchTrainees($mysqli, $searchTerm) {
    // Check if trainee table exists
    $checkTable = $mysqli->query("SHOW TABLES LIKE 'trainee_tbl'")->num_rows;
    
    if ($checkTable == 0) {
        return [];
    }
    
    $query = "
        SELECT 
            t.tid AS id,
            t.name AS title,
            CONCAT(t.email, ' - ', IFNULL(u.uni_name, 'No university')) AS description,
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $traineeResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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

// Search function for blog posts
function searchBlogPosts($mysqli, $searchTerm) {
    // Check if blog table exists
    $checkTable = $mysqli->query("SHOW TABLES LIKE 'semantic_blog'")->num_rows;
    
    if ($checkTable == 0) {
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
    
    $result = safeExecuteQuery($mysqli, $query, $params, 'ssss');
    
    $blogResults = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
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
function globalSearch($mysqli, $query) {
    // Check login and permissions
    if (!login_check($mysqli) || !in_array($GLOBALS['admintype'], ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
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
        $searchTerm = '%' . $mysqli->real_escape_string($query) . '%';
        
        // Perform searches across different tables
        $results = array_merge(
            searchTables($mysqli, $searchTerm),
            searchFields($mysqli, $searchTerm),
            searchFieldValues($mysqli, $searchTerm),
            searchSections($mysqli, $searchTerm),
            searchDocumentation($mysqli, $searchTerm),
            searchTrainees($mysqli, $searchTerm),
            searchBlogPosts($mysqli, $searchTerm)
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
    $searchResults = globalSearch($mysqli, $query);
    echo json_encode($searchResults);
} catch (Exception $e) {
    logSearchError($e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Unexpected error occurred'
    ]);
}

$mysqli->close();
?> 