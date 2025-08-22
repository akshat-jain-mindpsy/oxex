<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
check_session_timeout();

include 'incl/sess.php';
$formurl = 'logbook.php'; # which page send form
// this page shows the logbook data entry for the selected table
//  1. show a date selector to enter data for that date
//  2. show a list of previously-entered data
//  3. replace 2) with the data form (will show previous data where applicable)

// the trainee must be approved to use this particular table
// we first need to know what date the log is for
// we store values for the trainee, for this table, for the selected date in 'trainee_log'
// if an existing adta is chosen, we delete all old data and write new but show old data
$modifylog = 0;
$delalert = '';
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';

$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;

$tbid = isset($_GET['tab']) ? $_GET['tab'] : 0;
    $tbid = (int)$tbid;
$table = isset($_GET['table']) ? $_GET['table'] : ''; # if from table
    $table = preg_replace("/[^A-Z, a-z]/", "", $table);
$done = isset($_POST['done']) ? $_POST['done'] : 'no';
  $done = preg_replace("/[^A-Z, a-z]/", "", $done);
$dupe = isset($_POST['dupe']) ? $_POST['dupe'] : 'no';
  $dupe = preg_replace("/[^A-Z, a-z]/", "", $dupe);
$del = isset($_GET['del']) ? $_GET['del'] : ''; # if from table
  $del = preg_replace("/[^A-Z, a-z]/", "", $del);
if ($done == 'done') {# if from data form
  $tbid = isset($_POST['tab']) ? $_POST['tab'] : 0; # which table
    $tbid = (int)$tbid;
  $logkey = isset($_POST['logkey']) ? $_POST['logkey'] : ''; # which data set
}
if ($done == 'date' || $dupe == 'dupe' ) {# if from new data button or duplication
  $tbid = isset($_POST['tab']) ? $_POST['tab'] : 0;
    $tbid = (int)$tbid;
    // new data set so create new logkey
    $numids = 1;
    while (!$numids == 0) {
      // make unique 32 digit hex string
      $logkey = substr(md5(rand()), 0, 32);   
      $stmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? LIMIT 1");
      $stmt->bind_param('s', $logkey);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($tlogid);
      $stmt->fetch();
      $numids = $stmt->num_rows;
      $stmt->close();
    }
}

$logdate = isset($_POST['logdate']) ? $_POST['logdate'] : $checkhol;
  $logdate = preg_replace("/[^0-9]/", "", $logdate); #29032022
  $fromddd = substr($logdate, 0, 2);
  $frommm = substr($logdate, 2, 2);
  $fromyyyy = substr($logdate, 4, 4);
  $logdate = $fromyyyy.$frommm.$fromddd; # convert to yyyymmdd
  $todaydisp = strtotime($logdate);

if ($table == 'table') {# if from table
  $logkey = isset($_GET['logkey']) ? $_GET['logkey'] : ''; # unique ID for data set
  $logdate = isset($_GET['logdate']) ? $_GET['logdate'] : $checkhol;
  $logdate = preg_replace("/[^0-9]/", "", $logdate); #29032022
  $fromddd = substr($logdate, 0, 2);
  $frommm = substr($logdate, 2, 2);
  $fromyyyy = substr($logdate, 4, 4);
  $logdate = $fromyyyy.$frommm.$fromddd; # convert to yyyymmdd
  $todaydisp = strtotime($logdate);
  $done = 'done'; # to trigger viewing data
}
if ($del == 'del') {# if deleting log
  $logkey = isset($_GET['logkey']) ? $_GET['logkey'] : ''; # unique ID for data set
  
  $stmt = $mysqli->prepare("DELETE FROM trainee_log WHERE trainkey = ? AND logkey = ?");
  $stmt->bind_param("ss", $trainkey, $logkey); 
  $stmt->execute();
  if ($mysqli->affected_rows > 0) {
    $delalert = "<div class=\"row\"><div class=\"col\"><div class=\"alert alert-danger\" role=\"alert\"><strong>Record Deleted</strong></div></div></div>";
  }
  $stmt->close();
  
}

// has this trainee entered data for this $logkey already?
$vids = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND tbid = ? AND logkey = ?");
$vids->bind_param("sis", $trainkey, $tbid, $logkey);
$vids->execute();
$vids->store_result();
$modifylog = $vids->num_rows;
$vids->close();
//echo "trainkey $trainkey | tbid $tbid | logdate $logdate | modifylog $modifylog";

if ($done == 'done' && $table != 'table') {
  //$logkey = isset($_POST['logkey']) ? $_POST['logkey'] : ''; # unique ID for data set
  if ($modifylog > 0) {
  // we're overwriting data so delete the old data for this user/tab/date
    $stmt = $mysqli->prepare("DELETE FROM trainee_log WHERE trainkey = ? AND tbid = ? AND logkey = ?");
    $stmt->bind_param("sis", $trainkey, $tbid, $logkey); 
    $stmt->execute();
    $stmt->close();
  } 
        
  // receive logbook data
  $tableset = $mysqli->prepare("SELECT stid FROM tab_fields WHERE tbid = ? AND sort_order != ? ORDER BY sort_order ASC");
  $tableset->bind_param("ii", $tbid, $value0); 
  $tableset->execute();
  $tableset->store_result();
  $tableset->bind_result($stid);
  while ($tableset->fetch()){
    $posmarker = 'stid'.$stid;
    // what sort of data are we expecting?
    // 0=single select, 1=allow multiple, 2 = text, 3 = date, 4=numeric (0.1) 5=numeric(int), 6=time
    $stmt = $mysqli->prepare("SELECT single FROM select_types WHERE stid = ?");
    $stmt->bind_param("i", $stid);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($single);
    $stmt->fetch();
    $stmt->close();


    
    if ($single == 0) {
      // store select value
      $logvalue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : 0;
        // insert new value
      $insert_stmt = $mysqli->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->bind_param("siiisiis", $trainkey, $tbid, $stid, $logvalue, $valueblank, $logdate, $today, $logkey);
      $insert_stmt->execute();
      $newid = $insert_stmt->insert_id;
      $insert_stmt->close();
    }
    if ($single == 1 && (isset($_POST[$posmarker]))) {
      // will be array, step through values
        foreach(($_POST[$posmarker]) as $mval) {
          // insert new value
          $insert_stmt = $mysqli->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          $insert_stmt->bind_param("siiisiis", $trainkey, $tbid, $stid, $mval, $valueblank, $logdate, $today, $logkey);
          $insert_stmt->execute();
          $newid = $insert_stmt->insert_id;
          $insert_stmt->close();
        }
    }
    if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
      // expect text or numeric or hr:min input
      $logvalue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
      // insert new value
      $insert_stmt = $mysqli->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->bind_param("siiisiis", $trainkey, $tbid, $stid, $value0, $logvalue, $logdate, $today, $logkey);
      $insert_stmt->execute();
      $newid = $insert_stmt->insert_id;
      $insert_stmt->close();
    }
    if ($single == 3) {
      // convert dd-mm-yyyy date to yyyymmdd
      $logvalue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
      $logvalue = preg_replace("/[^0-9]/", "", $logvalue); # convert to ddmmyyyy
      $fromddd = substr($logvalue, 0, 2);
      $frommm = substr($logvalue, 2, 2);
      $fromyyyy = substr($logvalue, 4, 4);
      $logvalue = $fromyyyy.$frommm.$fromddd;

      // insert new value
      $insert_stmt = $mysqli->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->bind_param("siiisiis", $trainkey, $tbid, $stid, $value0, $logvalue, $logdate, $today, $logkey);
      $insert_stmt->execute();
      $newid = $insert_stmt->insert_id;
      $insert_stmt->close();
    }
  }
  $tableset->close();

  $modifylog = 1; # either way, there is now data for this date
}

// which Table is this and is this user entitled to it
$stmt = $mysqli->prepare("SELECT tab_name, tab_notes FROM tabs_tbl WHERE tbid = ?");
$stmt->bind_param("i", $tbid);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($tab_name, $tab_notes);
$stmt->fetch();
$stmt->close();

$vids = $mysqli->prepare("SELECT ttid FROM trainee_tab_link WHERE tbid = ? AND trainkey = ? ");
$vids->bind_param("is", $tbid, $trainkey);
$vids->execute();
$vids->store_result();
$numlinks = $vids->num_rows;
$vids->close();

function validateAndConvertTime($timeValue) {
    // Handle null or empty values
    if ($timeValue === null || $timeValue === '') {
        return '00:00';
    }
    
    // If it's a numeric value (integer or float)
    if (is_numeric($timeValue)) {
        $minutes = floatval($timeValue);
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $remainingMinutes);
    }
    
    // If it's in HH:MM format
    if (preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $timeValue)) {
        return $timeValue; // Return as is if it's a valid time format
    }
    
    return '00:00'; // Default return if invalid
}

?><!doctype html>
<html lang="en">
<style>
    /* General styles */
    body {
        font-family: Arial, sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        overflow-x: hidden; /* Prevent horizontal overflow */
    }

    #main {
        padding: 3rem 0;
    }

    .logbook-container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
        box-sizing: border-box; 
    }

    /* Header styles */
    .logbook-header {
        margin-bottom: 3rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #005eb8; /* NHS Blue */
    }

    /* Form container */
    .logbook-form {
      max-width: 150%;
        background-color: #f8f9fa;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        box-sizing: border-box; /* Prevent overflow from padding */
    }

    /* Form groups */
    .form-group {
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        background: #AEB7BD;
        border-radius: 8px; 
    }

    .form-label {
        font-weight: 500;
        margin-bottom: 0.5rem;w
        color: #212529;
    }

    .form-control {
        border: 1px solid #ced4da;
        border-radius: 4px;
        width: 100%; /* Ensure controls fit within their container */
    }

    .form-control:focus {
        border-color: #005eb8;
        box-shadow: 0 0 0 0.2rem rgba(0, 94, 184, 0.25);
    }
    
    /* Checkbox group styling for multiple selection fields */
    .checkbox-group {
        background-color: #f8f9fa;
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 15px;
        max-height: 200px;
        overflow-y: auto;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    .checkbox-group:hover {
        border-color: #005eb8;
    }
    
    .checkbox-group .form-check {
        margin-bottom: 8px;
        padding-left: 0;
        display: flex;
        align-items: flex-start;
        justify-content: flex-start;
    }
    
    .checkbox-group .form-check-input {
        margin-right: 8px;
        margin-top: 2px;
        flex-shrink: 0;
        order: -1;
    }
    
    .checkbox-group .form-check-label {
        cursor: pointer;
        font-size: 0.9rem;
        line-height: 1.4;
        margin-bottom: 0;
        padding-left: 0;
        flex: 1;
        text-align: left;
    }
    
    .checkbox-group .form-check-input:checked {
        background-color: #007F3B;
        border-color: #007F3B;
    }
    
    .checkbox-group .form-check-input:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 127, 59, 0.25);
        border-color: #007F3B;
    }

    /* Adjust multiple select box height */
    select[multiple] {
        height: 150px !important;
    }

   
    /* Full-width container for the row */
    .full-width-bg {
        background-color: #AEB7BD; /* NHS grey background color */
        margin-left: calc(-50vw + 50%); /* Align to viewport */
        width: 100vw; /* Full width of the viewport */
        padding: 1rem 0; /* Optional padding */
        margin-bottom: 2rem; 
        box-sizing: border-box;
    }

    /* Buttons */
    .btn-nhs {
        background-color: #007F3B;
        border-color: #007F3B;
        color: white;
        padding: 0.75rem 2rem;
        margin-top: 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 4px;
    }

    .btn-nhs:hover {
        background-color: #004985;
        border-color: #004985;
    }

    @media (max-width: 768px) {
      /* Full-width adjustments for smaller screens */
      .full-width-bg {
          margin-left: 0; /* Remove negative margin */
          width: 100%; /* Use full screen width */
      }

      .full-width-box {
          max-width: 100%; /* Use full width for content */
      }

      .row.mb-4 {
          max-width: 100%; /* Use full width for content */
      }

  }
    .row.mb-4 {
      margin-left: auto;
      margin-right: auto;
      max-width: 1400px; /* Align content width with the Create New Data Set section */
      padding: 0 1rem; /* Add responsive padding */
  } 

    /* Full-width inner box for .col-12 */
    .full-width-box {
      padding: 0 1rem; /* Padding for inner alignment */
      margin: 0 auto; /* Center the content */
      max-width: 1400px; /* Align with E-log Data Entry section width */
      box-sizing: border-box; /* Include padding and border in width */
  }

  .table-responsive {
      max-width: 1150px;
      /* max-width: 1200px; Adjust as per your requirement */
      margin: 0 auto; /* Center the table within the responsive container */
      overflow-x: auto; /* Scroll the table horizontally if it overflows */
  }
  .table{
    border: 1px solid #ddd;
    border-radius: 8px;
    max-width: 1150px;

    /* Adjust as per your requirement */
    
  }

    @media (min-width: 768px) {

        .table {
            font-size: 0.9rem; /* Slightly smaller font on mobile */
        }
        #main {
            padding: 3rem 0;
        }
        .full-width-bg {
        margin-left: 0; /* Remove negative margin */
        width: 100%; /* Use full screen width */
    }

    .full-width-box {
        max-width: 100%; /* Use full width for content */
    }

    .row.mb-4 {
        max-width: 100%; /* Use full width for content */
    }
    }
    
    </style>
  <head>
    <meta charset="utf-8">
    <title><?php echo $tab_name ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="//cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
    <style>
      /* Improve dropdown readability for long text */
      select.form-control option {
        word-wrap: break-word;
        white-space: normal;
        padding: 8px;
        line-height: 1.4;
      }
      
      select.form-control {
        word-wrap: break-word;
        white-space: normal;
      }
      
      /* Ensure dropdowns are wide enough for long text */
      .form-group select {
        min-width: 200px;
        max-width: 100%;
        box-sizing: border-box;
      }
      
      /* Improve option text readability */
      select option {
        font-size: 14px;
        padding: 6px 8px;
        max-width: 100%;
        word-wrap: break-word;
        white-space: normal;
      }
      
      /* Handle extremely long text with ellipsis */
      select option[title] {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        max-width: 100%;
      }
      
      /* Ensure dropdown container can handle long text */
      .form-group {
        position: relative;
        overflow: hidden;
      }
      
      /* Add some spacing between form groups for better readability */
      .form-group + .form-group {
        margin-top: 15px;
      }
      
      /* Ensure consistent container structure */
      .col-sm-6, .col-lg-4 {
        overflow: hidden;
        position: relative;
      }
      
      /* Fix dropdown overflow issues */
      select.form-control {
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
      }
      
      /* Ensure form fields stay within their containers */
      .form-group {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
      }
      
      /* Consistent spacing and alignment */
      .form-group label {
        display: block;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      /* Ensure all form elements respect container boundaries */
      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
      }
      
      /* Consistent grid layout */
      .row {
        margin-left: 0;
        margin-right: 0;
      }
      
      .col-sm-6, .col-lg-4 {
        padding: 10px;
        margin-bottom: 15px;
      }
      
      /* Ensure form containers have consistent height */
      .form-group {
        min-height: 80px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
      }
      
      /* Consistent label styling */
      .form-label {
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        line-height: 1.2;
      }
      
      /* Ensure dropdowns don't break layout */
      select.form-control:focus {
        outline: none;
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
      }
      
      /* Fix multi-select dropdown overflow */
      select[multiple] {
        max-height: 150px;
        overflow-y: auto;
        overflow-x: hidden;
      }
      
      /* Ensure dropdown options stay within bounds */
      select option {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      
      /* Container overflow protection */
      .container, .container-fluid {
        overflow: hidden;
        position: relative;
      }
      
      /* Section container consistency */
      .section-container {
        overflow: hidden;
        position: relative;
        width: 100%;
      }
      
      /* Fix dropdown z-index to appear above banner */
      select.form-control {
        z-index: 1000;
        position: relative;
      }
      
      /* Ensure dropdown options appear above all other elements */
      select option {
        z-index: 1001;
        position: relative;
      }
      
      /* Fix multi-select dropdown z-index */
      select[multiple] {
        z-index: 1000;
        position: relative;
      }
      
      /* Ensure form elements appear above banner */
      .form-group {
        z-index: 999;
        position: relative;
      }
      
      /* Button positioning and styling */
      .text-left {
        text-align: left !important;
      }
      
      /* Ensure button container is properly positioned */
      .form-group.text-left {
        display: flex;
        justify-content: flex-start;
        align-items: center;
      }
      
      /* Button size adjustments */
      .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border-radius: 0.2rem;
      }
      
      /* Additional z-index fixes for complex layouts */
      .container, .container-fluid {
        z-index: 1;
        position: relative;
      }
      
      /* Ensure dropdowns are always on top */
      select:focus {
        z-index: 1002 !important;
      }
      
      /* Fix banner z-index interference */
      .banner, .navbar, .nav {
        z-index: 100;
        position: relative;
      }
      
      /* Ensure main content area has proper z-index */
      #main {
        z-index: 10;
        position: relative;
      }
      
      /* Override any conflicting z-index from external CSS */
      .form-control:focus,
      .form-control:active,
      select:focus,
      select:active {
        z-index: 1002 !important;
        position: relative !important;
      }
    </style>
  </head>
  <?php
    if (login_check($mysqli) != false) {
      // logged in only!
    ?>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <?php
    if ($numlinks > 0) { // entitled to table
    ?>

    <div class="container py-5" id="main">
      <div class="container">
        <div class="row">
          <div class="col-xs-12">
            <?php
            if ($tab_name != '') {
              echo "<h2 class=\"text-center\">".htmlentities($tab_name)."</h2>";
            }
            //echo $tab_notes;
            //echo "modifylog $modifylog";
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12">
            
          </div>
        </div>
        <?php echo $delalert ?>
      </div>

      <!-- offer to start a new data set -->
      <form method="post" name="logbook" class="mb-3" action="<?php echo $formurl ?>">
          <div class="row">
              <div class="col-12">
                  <div class="form-group mb-0">
                      <input type="hidden" name="done" value="date">
                      <input type="hidden" name="tab" value="<?php echo $tbid ?>">
                      <button type="submit" class="btn btn-sm btn-nhs px-3 py-2" style="width: 30%; max-width: 400px;">Create New Data Set</button>
                  </div>
              </div>
          </div>
      </form>


      <?php
      if ($done == 'no') {
        // show a table of data previously added for this task for this trainee
        // one date's worth of data per row
      ?>
      <div class="container">
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="logbk">
                <thead>
                  <tr>
                    <?php
                    // Define the specific fields we want to show
                    $wanted_fields = array('Placement', 'Patient ID', 'Clinical specialism', 'Date (latest session/contact)', 
                    'Supervision type', 'Format', 'Leadership activity',	'Supervision model','Supervision methods used');
                    
                    $tableset = $mysqli->prepare("SELECT tab_fields.stid, select_types.str 
                      FROM tab_fields, select_types 
                      WHERE tab_fields.tbid = ? 
                      AND tab_fields.sort_order != ? 
                      AND tab_fields.stid = select_types.stid 
                      AND select_types.str IN ('" . implode("','", $wanted_fields) . "')
                      ORDER BY tab_fields.sort_order ASC");
                    $tableset->bind_param("ii", $tbid, $value0);
                    $tableset->execute();
                    $tableset->store_result();
                    $tableset->bind_result($stid, $str);
                    while ($tableset->fetch()){
                      echo "<th>$str</th>";
                    }
                    $tableset->close();
                    echo "<th>Changed:</th>";
                    echo "<th>Delete</th>";
                    ?>
                  </tr>
                </thead>
                <tbody>
                  <?php     
                      // now do one row per set of data for this person/table
                      // only do dates for this year

                      // define 'set' as a date
                      // then get any results entered for the $stid for that date
                    $dataset = $mysqli->prepare("SELECT 
                        MIN(date_added) as date_added, 
                        logkey, 
                        MAX(date_modified) as date_modified
                        FROM trainee_log 
                        WHERE trainkey = ? AND tbid = ? 
                        AND date_added >= ? AND date_added <= ? 
                        GROUP BY logkey 
                        ORDER BY MAX(date_modified) DESC");
                    $dataset->bind_param("siii", $trainkey, $tbid, $valueyearstart, $valueyearend);
                    $dataset->execute();
                    $dataset->store_result();
                    if ($dataset->num_rows > 0) {
                      $dataset->bind_result($date_added, $tablelogkey, $tabledate_modified);
                      while ($dataset->fetch()){
                        $exlogdate = strtotime($date_added);
                        $exlogdate = date("d-m-Y", $exlogdate);
                        $exmoddate = strtotime($tabledate_modified);
                        $exmoddate = date("d-m-Y", $exmoddate);
                        // loop through chosen fields
                        echo "<tr>";
                        $ctr = 0; # a counter to know the first column
                        // loop through same fields as the <th> cells
                        // Removed  LIMIT 10 which constrained how many columns
                        $tableset = $mysqli->prepare("SELECT tab_fields.stid, select_types.single 
                          FROM tab_fields, select_types 
                          WHERE tab_fields.tbid = ? 
                          AND tab_fields.sort_order != ? 
                          AND tab_fields.stid = select_types.stid 
                          AND select_types.str IN ('" . implode("','", $wanted_fields) . "')
                          ORDER BY tab_fields.sort_order ASC");
                        $tableset->bind_param("ii", $tbid, $value0);
                        $tableset->execute();
                        $tableset->store_result();
                        $tableset->bind_result($stid, $single);
                        while ($tableset->fetch()){
                          $exselect_val = '';
                          // get data for each in turn
                          // TAKEN TBID out of the search!!??
                          $stmt = $mysqli->prepare("SELECT pid, select_val FROM trainee_log WHERE trainkey = ?  AND stid = ? AND logkey = ?");
                          $stmt->bind_param("sis", $trainkey,  $stid, $tablelogkey);
                          $stmt->execute();
                          $stmt->store_result();
                          $stmt->bind_result($expid, $exselect_val);
                          $stmt->fetch();
                          $stmt->close();
                          // format data according to data type
                          // 0=single select, 2 = text, 3 = date, 4=numeric (0.1) 5=numeric(int)
                          if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
                            // for the first column in each row we create a link
                            // to view the data as if we'd chiosen trhat date
                            // in case the data is blank we add 'N/A'
                            if ($exselect_val == '' && $ctr == 0) {
                              $exselect_val = 'N/A';
                            }
                            if ($ctr == 0) {
                              echo "<td><a class=\"btn btn-sm btn-nhs\" href=\"$formurl?table=table&amp;tab=$tbid&amp;logkey=$tablelogkey\">" . htmlspecialchars($exselect_val ?? '') . "</a></td>\n";
                            } else {
                              echo "<td>" . htmlspecialchars($exselect_val ?? '') . "</td>\n";
                            }
                            
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
                            if ($ctr == 0) {
                              echo "<td><a class=\"btn btn-sm btn-nhs\" href=\"$formurl?table=table&amp;tab=$tbid&amp;logkey=$tablelogkey\">" . htmlspecialchars($exselect_val ?? '') . "</a></td>\n";
                            } else {
                              echo "<td>" . htmlspecialchars($exselect_val ?? '') . "</td>\n";
                            }
                          }
                          if ($single == 3) {
                            // $exselect_val is date YYYYMMDD
                            if ($exselect_val > 1 && $exselect_val !== null) {
                              // null 01-01/1970 dates
                              $exselect_val = strtotime($exselect_val);
                              echo "<td>".date("d-m-y", $exselect_val)."</td>\n";
                            } else {
                              echo "<td>&nbsp;</td>";
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
                            echo "<td>" . htmlspecialchars($exselect_val ?? '') . "</td>\n";
                            $exselect_val = '';
                            unset($exarruq);
                          }
                          $ctr++; # increment counter
                        }
                        $tableset->close();
                        echo "<td>" . htmlspecialchars($exmoddate ?? '') . "</td>";
                        echo "<td><a class=\"btn btn-sm btn-danger\" href=\"$formurl?del=del&amp;tab=$tbid&amp;logkey=$tablelogkey\" onclick=\"return confirm('Are you sure you want to immediately delete this line in your logbook (there  is NO undo)?')\">Delete</a></td>\n";
                        echo "</tr>";
                          
                      }
                    } else {
                      // If no data, show "No data available"
                      echo "<tr><td colspan=\"100%\" class=\"text-center\">No data available</td></tr>";
                  }
                    $dataset->close();
                  ?>
                </tbody>
              </table>
        </div>
      </div>

          </div>
        </div>
      </div>
      <?php
      }
      ?>



      <?php
      if ($done != 'no') {
        // we don't want to show the logbook until a date has been selected
      ?>
      <div class="row">
      <div class="col-12">
        <!-- Success message container -->
        <div id="save-success-message" class="alert alert-success d-none" role="alert">
            Your changes have been saved successfully!
        </div>
        
        <?php
        // Start form
        echo '<form method="post" name="logbook" action="' . $formurl . '">';
        echo '<div class="row g-4">';

        // Get all sections from the database
        // Use the section_table_link to get only sections assigned to this table
        $sections_query = "
          SELECT fs.section_id, fs.section_name, fs.section_order
          FROM field_sections fs
          JOIN section_table_link stl ON fs.section_id = stl.section_id
          WHERE stl.tbid = ?
          ORDER BY fs.section_order ASC, fs.section_name ASC
        ";
        
        $sections_stmt = $mysqli->prepare($sections_query);
        if ($sections_stmt) {
          $sections_stmt->bind_param("i", $tbid);
          $sections_stmt->execute();
          $sections_result = $sections_stmt->get_result();
          $sections_stmt->close();
        } else {
          // Fallback to original query if prepare fails
          $sections_result = $mysqli->query("SELECT section_id, section_name FROM field_sections ORDER BY section_order ASC");
        }
        
        // Track which fields have been displayed in sections
        $displayed_field_ids = array();

        if ($sections_result && $sections_result->num_rows > 0) {
          while ($section = $sections_result->fetch_assoc()) {
            $section_id = $section['section_id'];
            $section_name = $section['section_name'];
            
            // Generate section heading with a clear button
            echo '<div class="col-12 mb-4 d-flex justify-content-between align-items-center">';
            echo '<h4>' . htmlspecialchars($section_name ?? '') . '</h4>';
            echo '<button type="button" class="btn btn-secondary btn-sm clear-section" data-section="' . md5($section_name ?? '') . '">Clear</button>';
            echo '</div>';
            
            // Wrap the fields of this section in a container with a unique identifier
            echo '<div class="row g-4 section-container" id="section-' . md5($section_name ?? '') . '">';
            
            // Get fields for this section AND table
            $fields_query = "
              SELECT st.stid, st.str, st.single 
              FROM select_types st
              JOIN tab_fields tf ON st.stid = tf.stid
              WHERE st.section_id = ? AND tf.tbid = ?
              ORDER BY tf.sort_order ASC, st.str ASC
            ";
            
            $fields_stmt = $mysqli->prepare($fields_query);
            $fields_stmt->bind_param("ii", $section_id, $tbid);
            $fields_stmt->execute();
            $fields_result = $fields_stmt->get_result();
            $fields_stmt->close();
            
            if ($fields_result && $fields_result->num_rows > 0) {
              $field_count = 0;
              
              while ($field = $fields_result->fetch_assoc()) {
                $stid = $field['stid'];
                $str = $field['str'];
                $single = $field['single'];
                
                // Add to displayed fields tracker
                $displayed_field_ids[] = $stid;
                
                // Check if required
                $isreqd = ($stid == 59 || $stid == 2) ? 'required' : '';
                
                // Retrieve existing values
                $stmt = $mysqli->prepare("SELECT pid, select_val FROM trainee_log WHERE trainkey = ? AND tbid = ? AND stid = ? AND logkey = ?");
                $stmt->bind_param("siis", $trainkey, $tbid, $stid, $logkey);
                $stmt->execute();
                $stmt->store_result();
                $stmt->bind_result($expid, $exlogvalue);
                $stmt->fetch();
                $stmt->close();
                
                echo '<div class="col-sm-6 col-lg-4">';
                echo '<div class="form-group mb-4">';
                echo '<label class="form-label" for="stid' . $stid . '">' . htmlspecialchars($str ?? '') . '</label>';
                
                // Render the appropriate input field
                switch ($single) {
                  case 0: // Single select menu
                    echo "<select class='form-control' id='stid$stid' name='stid$stid' $isreqd style='word-wrap: break-word; white-space: normal;'>";
                    
                    $fieldset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ?");
                    $fieldset->bind_param("i", $stid);
                    $fieldset->execute();
                    $fieldset->store_result();
                    $fieldset->bind_result($pid, $select_val);
                    while ($fieldset->fetch()) {
                      $selected = ($pid == $expid) ? 'selected' : '';
                      echo "<option value='$pid' $selected style='word-wrap: break-word; white-space: normal;'>" . htmlspecialchars($select_val ?? '') . "</option>";
                    }
                    $fieldset->close();
                    echo "</select>";
                    break;
                    
                  case 1: // Multi-select menu
                    $exarr = [];
                    $fieldset = $mysqli->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
                    $fieldset->bind_param("is", $stid, $logkey);
                    $fieldset->execute();
                    $fieldset->store_result();
                    $fieldset->bind_result($expid);
                    while ($fieldset->fetch()) {
                      $exarr[] = $expid;
                    }
                    $fieldset->close();
                    
                    // Create checkbox options instead of multiple select
                    echo "<div class='checkbox-group' id='stid$stid' style='max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background-color: #f8f9fa;'>";
                    
                    $fieldset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ?");
                    $fieldset->bind_param("i", $stid);
                    $fieldset->execute();
                    $fieldset->store_result();
                    $fieldset->bind_result($pid, $select_val);
                    while ($fieldset->fetch()) {
                      $checked = in_array($pid, $exarr) ? 'checked' : '';
                      echo "<div class='form-check mb-2'>";
                      echo "<input class='form-check-input' type='checkbox' name='stid{$stid}[]' value='$pid' id='stid{$stid}_{$pid}' $checked>";
                      echo "<label class='form-check-label' for='stid{$stid}_{$pid}' style='word-wrap: break-word; white-space: normal;'>" . htmlspecialchars($select_val ?? '') . "</label>";
                      echo "</div>";
                    }
                    $fieldset->close();
                    echo "</div>";
                    break;
                    
                  case 2: // Text input
                    echo "<input type='text' class='form-control' id='stid$stid' name='stid$stid' value='" . htmlspecialchars($exlogvalue ?? '') . "'>";
                    break;
                    
                  case 3: // Date input
                    $dispdate = ($exlogvalue > 0 && $exlogvalue !== null) ? date("d-m-Y", strtotime($exlogvalue)) : '';
                    echo "<input type='text' class='form-control datepicker' id='stid$stid' name='stid$stid' value='$dispdate'>";
                    break;
                    
                  case 4: // Numeric (0.1 step)
                    echo "<input type='number' class='form-control' id='stid$stid' name='stid$stid' min='0' step='0.1' value='$exlogvalue'>";
                    break;
                    
                  case 5: // Numeric integer
                    echo "<input type='number' class='form-control' id='stid$stid' name='stid$stid' min='0' step='1' value='$exlogvalue' $isreqd>";
                    break;
                    
                  case 6: // Time input
                    $posmarker = 'stid'.$stid;
                    $timeValue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : $exlogvalue;
                    $timeValue = validateAndConvertTime($timeValue);
                    echo "<input type='time' class='form-control' id='stid$stid' name='stid$stid' min='00:00' step='300' value='$timeValue'>";
                    break;
                }
                
                echo '</div></div>';
                $field_count++;
              }
              
              if ($field_count == 0) {
                echo '<div class="col-12"><p class="text-muted">No fields found in this section</p></div>';
              }
            } else {
              echo '<div class="col-12"><p class="text-muted">No fields found in this section</p></div>';
            }
            
            echo '</div>'; // Close section-container
          }
        }
        
        // Add a section for unsectioned fields (fields not belonging to any section)
        echo '<div class="col-12 mb-4 d-flex justify-content-between align-items-center">';
        echo '<h4>Other Fields</h4>';
        echo '<button type="button" class="btn btn-secondary btn-sm clear-section" data-section="unsectioned">Clear</button>';
        echo '</div>';

        echo '<div class="row g-4 section-container" id="section-unsectioned">';

        // Get fields that are not assigned to any section but are in this table
        $unsectioned_query = "
          SELECT st.stid, st.str, st.single 
          FROM tab_fields tf
          JOIN select_types st ON tf.stid = st.stid
          WHERE tf.tbid = ?
          AND (st.section_id IS NULL OR st.section_id = 0)
          AND tf.sort_order != 0
          AND st.stid NOT IN (" . (count($displayed_field_ids) > 0 ? implode(',', $displayed_field_ids) : "0") . ")
          ORDER BY tf.sort_order ASC, st.str ASC
        ";
        
        $unsectioned_stmt = $mysqli->prepare($unsectioned_query);
        $unsectioned_stmt->bind_param("i", $tbid);
        $unsectioned_stmt->execute();
        $unsectioned_result = $unsectioned_stmt->get_result();
        $unsectioned_stmt->close();

        if ($unsectioned_result && $unsectioned_result->num_rows > 0) {
          while ($field = $unsectioned_result->fetch_assoc()) {
            $stid = $field['stid'];
            
            // Skip if this field was already displayed in a section
            if (in_array($stid, $displayed_field_ids)) {
              continue;
            }
            
            $str = $field['str'];
            $single = $field['single'];
            
            // Check if required
            $isreqd = ($stid == 59 || $stid == 2) ? 'required' : '';
            
            // Retrieve existing values
            $stmt = $mysqli->prepare("SELECT pid, select_val FROM trainee_log WHERE trainkey = ? AND tbid = ? AND stid = ? AND logkey = ?");
            $stmt->bind_param("siis", $trainkey, $tbid, $stid, $logkey);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($expid, $exlogvalue);
            $stmt->fetch();
            $stmt->close();
            
            echo '<div class="col-sm-6 col-lg-4">';
            echo '<div class="form-group mb-4">';
            echo '<label class="form-label" for="stid' . $stid . '">' . htmlspecialchars($str ?? '') . '</label>';
            
            // Render the appropriate input field (same switch case as above)
            switch ($single) {
              case 0: // Single select menu
                echo "<select class='form-control' id='stid$stid' name='stid$stid' $isreqd style='word-wrap: break-word; white-space: normal;'>";
                
                $fieldset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ?");
                $fieldset->bind_param("i", $stid);
                $fieldset->execute();
                $fieldset->store_result();
                $fieldset->bind_result($pid, $select_val);
                while ($fieldset->fetch()) {
                  $selected = ($pid == $expid) ? 'selected' : '';
                  echo "<option value='$pid' $selected style='word-wrap: break-word; white-space: normal;'>" . htmlspecialchars($select_val ?? '') . "</option>";
                }
                $fieldset->close();
                echo "</select>";
                break;
                
              case 1: // Multi-select menu
                $exarr = [];
                $fieldset = $mysqli->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
                $fieldset->bind_param("is", $stid, $logkey);
                $fieldset->execute();
                $fieldset->store_result();
                $fieldset->bind_result($expid);
                while ($fieldset->fetch()) {
                  $exarr[] = $expid;
                }
                $fieldset->close();
                
                // Create checkbox options instead of multiple select
                echo "<div class='checkbox-group' id='stid$stid' style='max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background-color: #f8f9fa;'>";
                
                $fieldset = $mysqli->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ?");
                $fieldset->bind_param("i", $stid);
                $fieldset->execute();
                $fieldset->store_result();
                $fieldset->bind_result($pid, $select_val);
                while ($fieldset->fetch()) {
                  $checked = in_array($pid, $exarr) ? 'checked' : '';
                  echo "<div class='form-check mb-2'>";
                  echo "<input class='form-check-input' type='checkbox' name='stid{$stid}[]' value='$pid' id='stid{$stid}_{$pid}' $checked>";
                  echo "<label class='form-check-label' for='stid{$stid}_{$pid}' style='word-wrap: break-word; white-space: normal;'>" . htmlspecialchars($select_val ?? '') . "</label>";
                  echo "</div>";
                }
                $fieldset->close();
                echo "</div>";
                break;
                
              case 2: // Text input
                echo "<input type='text' class='form-control' id='stid$stid' name='stid$stid' value='" . htmlspecialchars($exlogvalue ?? '') . "'>";
                break;
                
              case 3: // Date input
                $dispdate = ($exlogvalue > 0 && $exlogvalue !== null) ? date("d-m-Y", strtotime($exlogvalue)) : '';
                echo "<input type='text' class='form-control datepicker' id='stid$stid' name='stid$stid' value='$dispdate'>";
                break;
                
              case 4: // Numeric (0.1 step)
                echo "<input type='number' class='form-control' id='stid$stid' name='stid$stid' min='0' step='0.1' value='$exlogvalue'>";
                break;
                
              case 5: // Numeric integer
                echo "<input type='number' class='form-control' id='stid$stid' name='stid$stid' min='0' step='1' value='$exlogvalue' $isreqd>";
                break;
                
              case 6: // Time input
                $posmarker = 'stid'.$stid;
                $timeValue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : $exlogvalue;
                $timeValue = validateAndConvertTime($timeValue);
                echo "<input type='time' class='form-control' id='stid$stid' name='stid$stid' min='00:00' step='300' value='$timeValue'>";
                break;
            }
            
            echo '</div></div>';
          }
        } else {
          echo '<div class="col-12"><p class="text-muted">No additional fields found</p></div>';
        }

        echo '</div>'; // Close unsectioned section-container

        // Close the form
        echo '</div>'; // Close the row
        echo '<div class="row mt-4">';
        echo '<div class="col-12 col-md-4">';
        echo '<input type="hidden" name="done" value="done">';
        echo '<input type="hidden" name="tab" value="' . $tbid . '">';
        echo '<input type="hidden" name="logkey" value="' . $logkey . '">';
        echo '<input type="hidden" name="logdate" value="' . date("dmY", $todaydisp) . '">';
        echo '<button type="submit" id="save-button" class="btn btn-nhs btn-lg">Save</button>';
        echo '</div></div>';
        echo '</form>';
      ?>
  </div>
    </div>
    <?php
    // offer duplicate action
    echo "<p class=\"mt-5\"><a href=\"logbookdupe.php?table=table&amp;tab=$tbid&amp;logkey=$logkey\">Make a duplicate of this data set.</a> (You must save any changes before duplicating)</p>\n";
    ?>
    <?php
    } # only show log if date entered
    ?>
  </div>
  <?php
  } else {// not entitled
  ?>
  <div class="container-fluid py-5" id="main">
    <div class="container">
      <div class="row">
        <div class="col-xs-12 col-sm-8 offset-sm-2 text-center">
          <?php
            echo $page_txt11; # you can't come in here
          ?>
        </div>
      </div>
    </div>
  </div>
  <?php
  }// not entitled
  ?>
  <?php include 'incl/footer.php' ?>
  <?php
  if (login_check($mysqli) != false) {
    include 'incl/glossary.php';
  }
  ?>
  <?php include 'incl/jsfull.php' ?>
  <script src="https://code.jquery.com/ui/1.13.1/jquery-ui.js"></script>
  <script src="//cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
  <script src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.8.4/moment.min.js"></script>
  <script src="//cdn.datatables.net/plug-ins/1.12.1/sorting/datetime-moment.js"></script>
  <script>
  $( function() {
    $( ".datepicker" ).datepicker({
      dateFormat: "dd-mm-yy"
    });
  });
  $(document).ready(function () {
     $.fn.dataTable.moment( 'D-MM-YY' );
    $('#logbk').DataTable({
        paging: false,
        ordering: true,
        info: false,
        order: [[4, 'desc']],
    });
});
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      // Attach click event to the Save button
      const saveButton = document.getElementById('save-button');
      saveButton.addEventListener('click', function (e) {
          const confirmSave = confirm('Are you sure you want to save these changes?');
          if (!confirmSave) {
              e.preventDefault(); // Prevent form submission if the user cancels
          }
      });

      // Show success message if form is submitted successfully
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('save_success') && urlParams.get('save_success') === '1') {
          const successMessage = document.getElementById('save-success-message');
          if (successMessage) {
              successMessage.classList.remove('d-none');
          }
      }
  });
</script>

  <script>
        document.addEventListener('DOMContentLoaded', function () {
      // Add event listener to all clear buttons
      const clearButtons = document.querySelectorAll('.clear-section');

      clearButtons.forEach(button => {
          button.addEventListener('click', function () {
              const sectionId = this.getAttribute('data-section');
              const sectionContainer = document.getElementById('section-' + sectionId);

              if (sectionContainer) {
                  // Reset all inputs within the section
                  sectionContainer.querySelectorAll('input, select').forEach(field => {
                      if (field.type === 'checkbox' || field.type === 'radio') {
                          field.checked = false;
                      } else if (field.tagName === 'SELECT') {
                          if (field.multiple) {
                              // For multi-select, deselect all options
                              Array.from(field.options).forEach(option => option.selected = false);
                          } else {
                              // For single select, set to empty value
                              field.value = ''; // This will select the empty option if it exists
                              // If no empty option exists, add one temporarily
                              if (field.value !== '') {
                                  const emptyOption = new Option('Select an option', '');
                                  field.insertBefore(emptyOption, field.firstChild);
                                  field.value = '';
                              }
                          }
                      } else {
                          field.value = ''; // Clear text inputs
                      }
                      // Trigger change event to update any dependent logic
                      field.dispatchEvent(new Event('change'));
                  });
              }
          });
      });
  });
  </script>

  <script>
  // Enhance dropdown readability and user experience
  document.addEventListener('DOMContentLoaded', function() {
         // Add tooltips to long dropdown options
     const selectElements = document.querySelectorAll('select.form-control');
     
     selectElements.forEach(select => {
       select.addEventListener('change', function() {
         const selectedOption = this.options[this.selectedIndex];
         if (selectedOption && selectedOption.text.length > 50) {
           // Add title attribute for long text
           selectedOption.title = selectedOption.text;
         }
       });
       
       // Add hover effect for better readability
       select.addEventListener('mouseenter', function() {
         this.style.cursor = 'pointer';
       });
       
       // Ensure dropdown appears above banner when focused
       select.addEventListener('focus', function() {
         this.style.zIndex = '1002';
         this.style.position = 'relative';
       });
       
       // Reset z-index when not focused
       select.addEventListener('blur', function() {
         this.style.zIndex = '1000';
       });
     });
    
    // Ensure consistent container sizing and prevent overflow
    const formGroups = document.querySelectorAll('.form-group');
    formGroups.forEach(group => {
      const select = group.querySelector('select');
      const input = group.querySelector('input');
      const textarea = group.querySelector('textarea');
      
      // Handle select elements
      if (select) {
        // Ensure select doesn't exceed container width
        const container = group.closest('.col-sm-6, .col-lg-4');
        if (container) {
          const containerWidth = container.offsetWidth;
          select.style.maxWidth = (containerWidth - 20) + 'px'; // Account for padding
        }
        
        // Set reasonable minimum width for long text
        const options = Array.from(select.options);
        const maxLength = Math.max(...options.map(opt => opt.text.length));
        if (maxLength > 50) {
          select.style.minWidth = Math.min(maxLength * 6, containerWidth - 20) + 'px';
        }
      }
      
      // Handle input elements
      if (input) {
        input.style.maxWidth = '100%';
        input.style.boxSizing = 'border-box';
      }
      
      // Handle textarea elements
      if (textarea) {
        textarea.style.maxWidth = '100%';
        textarea.style.boxSizing = 'border-box';
      }
    });
    
          // Ensure all containers have consistent structure
      const containers = document.querySelectorAll('.col-sm-6, .col-lg-4');
      containers.forEach(container => {
        container.style.overflow = 'hidden';
        container.style.position = 'relative';
        
        // Ensure form groups within containers are properly sized
        const formGroups = container.querySelectorAll('.form-group');
        formGroups.forEach(group => {
          group.style.width = '100%';
          group.style.maxWidth = '100%';
          group.style.overflow = 'hidden';
        });
      });
      
      // Handle window resize to maintain layout consistency
      window.addEventListener('resize', function() {
        setTimeout(function() {
          formGroups.forEach(group => {
            const select = group.querySelector('select');
            if (select) {
              const container = group.closest('.col-sm-6, .col-lg-4');
              if (container) {
                const containerWidth = container.offsetWidth;
                select.style.maxWidth = (containerWidth - 20) + 'px';
              }
            }
          });
        }, 100);
      });
    });
  </script>


</body>
<?php
}// logged in only!
?>
</html>

