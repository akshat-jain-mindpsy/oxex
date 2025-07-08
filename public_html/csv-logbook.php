<?php
// Prevent any output before headers
ob_start();

include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
/* CSV logbook */
$value0 = 0;
$value1 = 1;
$value2 = 2;
$value3 = 3;
$valueblank = '';
$today = date("Ymd");
$todaydisp = strtotime($today);
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;
$filename = "OXEX_elog_$today.csv";

$which = isset($_GET['which']) ? $_GET['which'] : '';
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Check if the template table exists and if the requested template exists
$template_exists = false;
if ($template_id > 0) {
    $check_table = $mysqli->query("SHOW TABLES LIKE 'csv_templates'");
    if ($check_table && $check_table->num_rows > 0) {
        // Table exists, now check if the template exists
        $template_check = $mysqli->prepare("SELECT id FROM csv_templates WHERE id = ?");
        $template_check->bind_param("i", $template_id);
        $template_check->execute();
        $template_check->store_result();
        
        if ($template_check->num_rows > 0) {
            $template_exists = true;
        }
        $template_check->close();
    }
}

// Use template if it exists, otherwise use default CSV
if ($template_exists) {
    generateTemplateCSV($mysqli, $which, $template_id, $start_date, $end_date);
} else {
    generateDefaultCSV($mysqli, $which);
}
exit(); // Add exit to prevent any additional output

/**
 * Generate CSV using a selected template
 */
function generateTemplateCSV($mysqli, $trainee_key, $template_id, $start_date, $end_date) {
    // Define date variables
    $today = date("Ymd");
    $todaydisp = strtotime($today);
    
    // Validate trainee key
    if (empty($trainee_key)) {
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    // Get trainee information
    $stmt = $mysqli->prepare("SELECT name FROM trainee_tbl WHERE trainkey = ?");
    if (!$stmt) {
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $stmt->bind_param("s", $trainee_key);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        $stmt->close();
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $stmt->bind_result($name);
    $stmt->fetch();
    $stmt->close();
    
    // Get template information
    $template_query = $mysqli->prepare("SELECT id, template_name, description FROM csv_templates WHERE id = ?");
    if (!$template_query) {
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $template_query->bind_param("i", $template_id);
    $template_query->execute();
    $template_result = $template_query->get_result();
    
    if ($template_result->num_rows == 0) {
        $template_query->close();
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $template = $template_result->fetch_assoc();
    $template_query->close();
    
    if (!$template) {
        // If template not found, fall back to default
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    // Set CSV parameters with default values (since they don't exist in the table)
    $delimiter = ',';
    $enclosure = '"';
    $include_header = true; 
    $max_rows = 1000;
    
    // Get template columns
    $columns_query = $mysqli->prepare("
        SELECT c.id, c.field_id, c.table_id, c.display_order, 
               t.tab_name AS table_name, 
               s.str AS field_name
        FROM csv_template_columns c
        LEFT JOIN tabs_tbl t ON c.table_id = t.tbid
        LEFT JOIN select_types s ON c.field_id = s.stid
        WHERE c.template_id = ?
        ORDER BY c.display_order
    ");
    
    if (!$columns_query) {
        // If query fails, fall back to default CSV
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $columns_query->bind_param("i", $template_id);
    $columns_query->execute();
    $columns_result = $columns_query->get_result();
    
    $columns = [];
    while ($column = $columns_result->fetch_assoc()) {
        $columns[] = $column;
    }
    
    // Check if we have any columns defined
    if (empty($columns)) {
        // No columns defined for this template, fall back to default
        $columns_query->close();
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    // Get data for each column
    $data_rows = [];
    
    // Base query to get logkeys
    $query = "
        SELECT logkey
        FROM trainee_log 
        WHERE trainkey = ?
    ";
    
    $params = [$trainee_key];
    $types = "s";
    
    // Add date filtering
    if ($start_date) {
        $start_date_int = (int) str_replace('-', '', $start_date);
        $query .= " AND date_added >= ?";
        $params[] = $start_date_int;
        $types .= "i";
    }
    
    if ($end_date) {
        $end_date_int = (int) str_replace('-', '', $end_date);
        $query .= " AND date_added <= ?";
        $params[] = $end_date_int;
        $types .= "i";
    }
    
    $query .= " GROUP BY logkey ORDER BY MAX(date_added) DESC LIMIT ?";
    $params[] = $max_rows;
    $types .= "i";
    
    // Get logkeys with date filtering
    $data_query = $mysqli->prepare($query);
    if (!$data_query) {
        // Fallback or error handling
        generateDefaultCSV($mysqli, $trainee_key);
        return;
    }
    
    $data_query->bind_param($types, ...$params);
    $data_query->execute();
    $data_result = $data_query->get_result();
    
    // Group columns by table
    $tables = [];
    foreach ($columns as $column) {
        $table_id = $column['table_id'];
        $table_name = $column['table_name'];
        
        if (!isset($tables[$table_id])) {
            $tables[$table_id] = [
                'name' => $table_name,
                'columns' => []
            ];
        }
        
        $tables[$table_id]['columns'][] = $column;
    }
    
    // Begin CSV output with general information
    $csv_output = "";
    $today = date("Ymd");
    $todaydisp = strtotime($today);
    $sanitized_template_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $template['template_name']);
    $filename = "OXEX_{$sanitized_template_name}_{$today}.csv";
    
    // Add trainee name and date to the first row
    $csv_output .= "$name," . date("D jS M Y", $todaydisp) . "\n";
    $csv_output .= "Template: {$template['template_name']}\n";
    if (!empty($template['description'])) {
        $csv_output .= "Description: {$template['description']}\n";
    }
    $csv_output .= "\n"; // Add an empty line to separate metadata from data
    
    // Process all rows to collect data
    $all_row_data = [];
    while ($row = $data_result->fetch_assoc()) {
        $logkey = $row['logkey'];
        $entry_data = ['logkey' => $logkey];
        
        // Get data for each column in this row
        foreach ($columns as $column) {
            $field_id = $column['field_id'];
            $table_id = $column['table_id'];
            
            $value_query = $mysqli->prepare("
                SELECT tl.select_val, tl.pid, st.single
                FROM trainee_log tl
                JOIN select_types st ON tl.stid = st.stid
                WHERE tl.trainkey = ? AND tl.logkey = ? AND tl.stid = ?
                LIMIT 1
            ");
            $value_query->bind_param("ssi", $trainee_key, $logkey, $field_id);
            $value_query->execute();
            $value_result = $value_query->get_result();
            $field_data = $value_result->fetch_assoc();
            
            $field_value = '';
            
            if ($field_data) {
                switch ($field_data['single']) {
                    case 0: // Single select
                        if (!empty($field_data['pid'])) {
                            $label_query = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                            $label_query->bind_param("i", $field_data['pid']);
                            $label_query->execute();
                            $label_result = $label_query->get_result();
                            $label_data = $label_result->fetch_assoc();
                            $field_value = $label_data ? $label_data['select_val'] : '';
                            $label_query->close();
                        }
                        break;
                    
                    case 1: // Multiple select
                        $multi_query = $mysqli->prepare("
                            SELECT sg.select_val
                            FROM select_gen sg
                            JOIN trainee_log tl ON sg.pid = tl.pid
                            WHERE tl.trainkey = ? AND tl.logkey = ? AND tl.stid = ?
                        ");
                        $multi_query->bind_param("ssi", $trainee_key, $logkey, $field_id);
                        $multi_query->execute();
                        $multi_result = $multi_query->get_result();
                        
                        $multi_values = [];
                        while ($multi_data = $multi_result->fetch_assoc()) {
                            $multi_values[] = $multi_data['select_val'];
                        }
                        
                        $field_value = implode(', ', $multi_values);
                        $multi_query->close();
                        break;
                    
                    case 3: // Date
                        if (!empty($field_data['select_val'])) {
                            $date = strtotime($field_data['select_val']);
                            if ($date) {
                                $field_value = date('d-m-Y', $date);
                            }
                        }
                        break;
                    
                    default: // Text, number, etc.
                        $field_value = $field_data['select_val'] ?? '';
                }
            }
            
            // Handle missing values with empty string instead of potential null
            if (empty($field_value) && $field_value !== '0') {
                $field_value = ''; 
            }
            
            // Store field value in entry data
            $key = "{$table_id}_{$field_id}";
            $entry_data[$key] = $field_value;
        }
        
        $all_row_data[] = $entry_data;
    }
    
    // Format output by table
    foreach ($tables as $table_id => $table) {
        // Add table name as section header
        $csv_output .= "{$table['name']}\n";
        
        // Add header row for this table's fields
        if ($include_header) {
            foreach ($table['columns'] as $column) {
                $csv_output .= $enclosure . str_replace($enclosure, $enclosure.$enclosure, $column['field_name']) . $enclosure . $delimiter;
            }
            $csv_output = rtrim($csv_output, $delimiter) . "\n";
        }
        
        // Add data rows for this table
        foreach ($all_row_data as $row_data) {
            $has_data = false;
            
            foreach ($table['columns'] as $column) {
                $key = "{$table_id}_{$column['field_id']}";
                $value = isset($row_data[$key]) ? $row_data[$key] : '';
                
                // Check if this row has any data for this table
                if (!empty($value)) {
                    $has_data = true;
                }
                
                $csv_output .= $enclosure . str_replace($enclosure, $enclosure.$enclosure, $value) . $enclosure . $delimiter;
            }
            
            if ($has_data) {
                $csv_output = rtrim($csv_output, $delimiter) . "\n";
            }
        }
        
        // Add spacing between tables
        $csv_output .= "\n\n";
    }
    
    // Output the CSV
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Length: " . strlen($csv_output));
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=$filename");
    echo $csv_output;
    exit();
}

/**
 * Generate CSV using the default format (original logic)
 */
function generateDefaultCSV($mysqli, $which) {
    // Existing CSV generation code starts here
    $value0 = 0;
    $value1 = 1;
    $today = date("Ymd");
    $todaydisp = strtotime($today);
    $filename = "OXEX_elog_$today.csv";
    
    // find the required trainee
    $stmt = $mysqli->prepare("SELECT name, who_by, date_added, date_modified, last_used FROM trainee_tbl WHERE trainkey = ?");
    $stmt->bind_param("s", $which);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($name, $who_by, $date_added, $date_modified, $last_used);
    $stmt->fetch();
    $stmt->close();
    $date_added = strtotime($date_added);
    
    $firstline = "$name,".date("D jS M Y", $todaydisp)."\n";
    $nextline = "";
    $tabctr = 0;
    
    // Define the specific tab IDs we want
    $generic_tbid = 1;  // Generic tab ID
    $CSarr = array(11, 14, 15, 16);  // Special case tab IDs
    // 11 = Children and Young People (CYP)
    // 14 = Working Age Adults (WAA)
    // 15 = Older People (OA)
    // 16 = Learning Disability (LD)
    
    // Modify the query to only get the Generic tab
    $tableset = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = ?");
    $tableset->bind_param("i", $generic_tbid);
    $tableset->execute();
    $tableset->store_result();
    $tableset->bind_result($tbid, $tab_name);
    
    while ($tableset->fetch()){
        if ($tabctr > 0) {
            $nextline = $nextline."\n\n";
        }
        // Add Table Title with extra spacing
        $nextline = $nextline."$tab_name\n\n"; // Added extra newline for spacing after title
    
        // Check for pass status
        $numpass = 0; # reset
        $stmt = $mysqli->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
        $stmt->bind_param("is", $tbid, $which);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($who_notes, $date_signed);
        $numpass = $stmt->num_rows;
        $stmt->fetch();
        $stmt->close();
    
        if ($date_signed !== null) {
            $date_signed = strtotime($date_signed);
        } else {
            $date_signed = '';
        }
    
        if ($numpass > 0) {
            // who signed it off?
            $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
            $stmt->bind_param("s", $who_notes);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($supername);
            $stmt->fetch();
            $stmt->close();
            $nextline = $nextline."Signed off: , ".$supername.",".date("D jS M Y", $date_signed)."\n\n"; // Added extra newline
        }
    
        // Get fields for Generic tab
        $tabset = $mysqli->prepare("SELECT tab_fields.stid, select_types.str 
            FROM tab_fields, select_types 
            WHERE tab_fields.tbid = ? 
            AND tab_fields.sort_order != ? 
            AND tab_fields.stid = select_types.stid 
            ORDER BY tab_fields.sort_order ASC");
        $tabset->bind_param("ii", $generic_tbid, $value0);
        $tabset->execute();
        $tabset->store_result();
        $tabset->bind_result($stid, $str);
        
        while ($tabset->fetch()){
            // output the field names
            $nextline = $nextline."$str,";
        }
        $tabset->close();
        $nextline = $nextline."\n"; # end of line
    
        // Get data for Generic tab entries
        $dataset = $mysqli->prepare("SELECT MAX(date_added) as date_added, logkey 
            FROM trainee_log 
            WHERE trainkey = ? AND tbid = ? 
            GROUP BY logkey 
            ORDER BY MAX(date_added) DESC");
        $dataset->bind_param("si", $which, $generic_tbid);
        $dataset->execute();
        $dataset->store_result();
        $dataset->bind_result($date_added, $tablelogkey);
    
        while ($dataset->fetch()){
            $exlogdate = strtotime($date_added);
            $exlogdate = date("d-m-Y", $exlogdate);
            
            // Initialize counter for each dataset
            $ctr = 0;
            
            // Get field values
            $resultset = $mysqli->prepare("SELECT tab_fields.stid, select_types.single 
                FROM tab_fields, select_types 
                WHERE tab_fields.tbid = ? 
                AND tab_fields.sort_order != ? 
                AND tab_fields.stid = select_types.stid 
                ORDER BY tab_fields.sort_order ASC");
            $resultset->bind_param("ii", $generic_tbid, $value0);
            $resultset->execute();
            $resultset->store_result();
            $resultset->bind_result($stid, $single);
    
            while ($resultset->fetch()){
                $exselect_val = '';
                $expid = '';
                // get data for each field
                $stmt = $mysqli->prepare("SELECT tlogid, pid, select_val 
                    FROM trainee_log 
                    WHERE trainkey = ? AND stid = ? AND logkey = ?");
                $stmt->bind_param("sis", $which, $stid, $tablelogkey);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($extlogid, $expid, $exselect_val);
                $stmt->fetch();
                $stmt->close();
    
                // Format data based on type
                if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
                    if ($exselect_val == '' && $ctr == 0) {
                        $exselect_val = 'N/A';
                    }
                    $nextline = $nextline."$exselect_val,";
                }
                if ($single == 0) {
                    $stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                    $stmt->bind_param("i", $expid);
                    $stmt->execute();
                    $stmt->store_result();
                    $stmt->bind_result($exselect_val);
                    $stmt->fetch();
                    $stmt->close();
                    $nextline = $nextline."$exselect_val,";
                }
                if ($single == 3) {
                    if ($exselect_val > 1) {
                        $exselect_val = strtotime($exselect_val);
                        $nextline = $nextline."".date("d-m-y", $exselect_val).",";
                    } else {
                        $nextline = $nextline." ,";
                    }
                }
                $ctr++;
            }
            $resultset->close();
            $nextline = $nextline."\n"; # end of line
        }
        $dataset->close();
        
        // Add extra spacing after all entries for this tab
        $nextline = $nextline."\n\n\n"; // Add three newlines for clear separation between tabs
    }
    $tableset->close();
    
    // Handle the special case tabs (CYP, WAA, OA, LD)
    foreach ($CSarr as $CSvalue) {
        $stmt = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = ?");
        $stmt->bind_param("i", $CSvalue);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($tbid, $tab_name);
        $stmt->fetch();
        $stmt->close();
        
        $nextline = $nextline."$tab_name\n\n"; # do Table Title
    
        $numpass = 0; # reset
        $stmt = $mysqli->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
        $stmt->bind_param("is", $tbid, $which);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($who_notes, $date_signed);
        $numpass = $stmt->num_rows;
        $stmt->fetch();
        $stmt->close();
        if ($date_signed !== null) {
            $date_signed = strtotime($date_signed);
        } else {
            $date_signed = '';
        }
        if ($numpass > 0) {
            // who signed it off?
            $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
            $stmt->bind_param("s", $who_notes);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($supername);
            $stmt->fetch();
            $stmt->close();
            $nextline = $nextline."Signed off: , ".$supername.",".date("D jS M Y", $date_signed)."\n\n";
        }
          
        // loop through fields for the generic table ($tbid == 1) and get field name 
        $tabset = $mysqli->prepare("SELECT tab_fields.stid, select_types.str, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
        $tabset->bind_param("ii", $value1, $value0);
        $tabset->execute();
        $tabset->store_result();
        $tabset->bind_result($stid, $str, $dispsingle);
        while ($tabset->fetch()){
            // output the field names
            $nextline = $nextline."$str,";
        }
        $tabset->close();
        $nextline = $nextline."\n"; # end of line
    
        // define 'set' as a date
        // look at where stid = 2 or 75 AND 
        // then get any results entered for the $stid for that date
        if ($CSvalue == 11 || $CSvalue == 14 || $CSvalue == 15 || $CSvalue == 16) {
            if ($CSvalue == 11) {
                $stidchk1 = 2; # check for this stid...
                $pidchk1 = 4; # ... with this pid
                $stidchk2 = 75; # or check for this stid...
                $pidchk2 = 654; # ... with this pid
            }
            if ($CSvalue == 14) {
                $stidchk1 = 2; # check for this stid...
                $pidchk1 = 1; # ... with this pid
                $stidchk2 = 75; # or check for this stid...
                $pidchk2 = 651; # ... with this pid
            }
            if ($CSvalue == 15) {
                $stidchk1 = 2; # check for this stid...
                $pidchk1 = 2; # ... with this pid
                $stidchk2 = 75; # or check for this stid...
                $pidchk2 = 652; # ... with this pid
            }
            if ($CSvalue == 16) {
                $stidchk1 = 2; # check for this stid...
                $pidchk1 = 3; # ... with this pid
                $stidchk2 = 75; # or check for this stid...
                $pidchk2 = 653; # ... with this pid
            }
            $dataset = $mysqli->prepare("SELECT MAX(date_added) as date_added, logkey 
                FROM trainee_log 
                WHERE trainkey = ? AND (stid = ? AND pid = ?) 
                GROUP BY logkey 
                ORDER BY MAX(date_added) DESC");
            $dataset->bind_param("sii", $which, $stidchk1, $pidchk1); 
            $dataset->execute();
            $dataset->store_result();
            $dataset->bind_result($date_added, $tablelogkey);
            while ($dataset->fetch()){
                $exlogdate = strtotime($date_added);
                $exlogdate = date("d-m-Y", $exlogdate);
                // loop through chosen fields
    
                $ctr = 0; # a counter to know the first column
                // loop through same fields as the <th> cells
                $resultset = $mysqli->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
                $resultset->bind_param("ii", $value1, $value0);
                $resultset->execute();
                $resultset->store_result();
                $resultset->bind_result($stid, $single);
                while ($resultset->fetch()){
                    $exselect_val = '';
                    $expid = '';
                    // get data for each in turn
                    $stmt = $mysqli->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ? AND stid = ? AND logkey = ?");
                    $stmt->bind_param("sis", $which,  $stid, $tablelogkey);
                    $stmt->execute();
                    $stmt->store_result();
                    $stmt->bind_result($extlogid, $expid, $exselect_val);
                    $stmt->fetch();
                    $stmt->close();
                    // format data according to data type
                    // 0=single select, 2 = text, 3 = date, 4=numeric (0.1) 5=numeric(int)
                    
                    // For this section, we only display a line if:
                    // the field matches the table, so only field LD for table LD etc 
                    
    
                    if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
                        // for the first column in each row we create a link
                        // to view the data as if we'd chosen that date
                        // in case the data is blank we add 'N/A'
                        if ($exselect_val == '' && $ctr == 0) {
                            $exselect_val = 'N/A';
                        }
                        $nextline = $nextline."$exselect_val,";
                    }
                    if ($single == 0) {
                        // fetch the select menu value for $pid
                        $stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                        $stmt->bind_param("i", $expid);
                        $stmt->execute();
                        $stmt->store_result();
                        $stmt->bind_result($exselect_val);
                        $stmt->fetch();
                        $stmt->close();
                        $nextline = $nextline."$exselect_val,";
                    }
                    if ($single == 3) {
                        // $exselect_val is date YYYYMMDD
                        if ($exselect_val > 1) {
                            // null 01-01/1970 dates
                            $exselect_val = strtotime($exselect_val);
                            $nextline = $nextline."".date("d-m-y", $exselect_val).",";
                        } else {
                            $nextline = $nextline." ,";
                        }             
                    }
                    if ($single == 1) {
                        // $exselect_val is multiple values, one per record
                        $exarr = array();
                        $fieldset = $mysqli->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
                        $fieldset->bind_param("is", $stid, $tablelogkey);
                        $fieldset->execute();
                        $fieldset->store_result();
                        $fieldset->bind_result($expid);
                        while ($fieldset->fetch()){
                            array_push($exarr, $expid); # add to array for checking in select
                        }
                        $fieldset->close();
                        // now make array unique as this will create for each seperate record
                        $exarruq = (array_unique($exarr));
                        // now loop through array of pids to get values
                        $exselect_val = '';
                        $mult_val = 0;
                        foreach($exarruq as $x) {
                            // fetch the select menu value for $pid
                            $stmt = $mysqli->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                            $stmt->bind_param("i", $x);
                            $stmt->execute();
                            $stmt->store_result();
                            $stmt->bind_result($mult_val);
                            $stmt->fetch();
                            $stmt->close();
                            $exselect_val = $exselect_val." ".$mult_val;
                        }
                        $nextline = $nextline."".$exselect_val.",";
                        $exselect_val = '';
                        unset($exarruq);
                    }
                    $ctr++; # increment counter
    
                }
                $resultset->close();
                $nextline = $nextline."\n"; # end of line
    
            }
            $dataset->close();
        } # checking CSvalue
    
        // Add extra spacing after all entries for this special case tab
        $nextline = $nextline."\n\n\n"; // Add three newlines for clear separation between tabs
    }
    
    // Output the CSV
    $finalline = $firstline.$nextline;
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Length: " . strlen($finalline));
    header("Content-type: text/csv");
    header("Content-Disposition: attachment; filename=$filename");
    echo $finalline;
}

?>