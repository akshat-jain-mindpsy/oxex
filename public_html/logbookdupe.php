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
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$tbid = isset($_GET['tab']) ? $_GET['tab'] : 0;
    $tbid = (int)$tbid;
$table = isset($_GET['table']) ? $_GET['table'] : ''; # if from table
    $table = preg_replace("/[^A-Z, a-z]/", "", $table);
$done = isset($_POST['done']) ? $_POST['done'] : 'no';
  $done = preg_replace("/[^A-Z, a-z]/", "", $done);
$dupe = isset($_POST['dupe']) ? $_POST['dupe'] : 'no';
  $dupe = preg_replace("/[^A-Z, a-z]/", "", $dupe);

if ($done == 'done'){# if from data form
  $tbid = isset($_POST['tab']) ? $_POST['tab'] : 0; # which table
    $tbid = (int)$tbid;
  $logkey = isset($_POST['logkey']) ? $_POST['logkey'] : ''; # which data set

}
if ($done == 'date' || $dupe == 'dupe'){# if from new data button or duplication
  $tbid = isset($_POST['tab']) ? $_POST['tab'] : 0;
    $tbid = (int)$tbid;
    // new data set so create new logkey
    $numids = 1;
    while (!$numids == 0) {
      // make unique 32 digit hex string
      $logkey = substr(md5(rand()), 0, 32);   
      $stmt = $pdo->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? LIMIT 1");
      $stmt->execute([$logkey]);
      $numids = $stmt->rowCount();
      $stmt->closeCursor();
    }
}

$logdate = isset($_POST['logdate']) ? $_POST['logdate'] : $checkhol;
  $logdate = preg_replace("/[^0-9]/", "", $logdate); #29032022
  $fromddd = substr($logdate, 0, 2);
  $frommm = substr($logdate, 2, 2);
  $fromyyyy = substr($logdate, 4, 4);
  $logdate = $fromyyyy.$frommm.$fromddd; # convert to yyyymmdd
  $todaydisp = strtotime($logdate);

if ($table == 'table'){# if from table
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


// has this trainee entered data for this $logkey already?
$vids = $pdo->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND tbid = ? AND logkey = ?");
$vids->execute([$trainkey, $tbid, $logkey]);
$modifylog = $vids->rowCount();
$vids->closeCursor();
//echo "trainkey $trainkey | tbid $tbid | logdate $logdate | modifylog $modifylog";

if ($done == 'done' && $table != 'table') {
  //$logkey = isset($_POST['logkey']) ? $_POST['logkey'] : ''; # unique ID for data set
  if ($modifylog > 0) {
  // we're overwriting data so delete the old data for this user/tab/date
    $stmt = $pdo->prepare("DELETE FROM trainee_log WHERE trainkey = ? AND tbid = ? AND logkey = ?");
    $stmt->execute([$trainkey, $tbid, $logkey]); 
    $stmt->closeCursor();
  }

        
  // receive logbook data
  $tableset = $pdo->prepare("SELECT stid FROM tab_fields WHERE tbid = ? AND sort_order != ? ORDER BY sort_order ASC");
  $tableset->execute([$tbid, $value0]); 
  while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
    $stid = $row['stid'];
    $posmarker = 'stid'.$stid;
    // what sort of data are we expecting?
    // 0=single select, 1=allow multiple, 2 = text, 3 = date, 4=numeric (0.1) 5=numeric(int), 6=time
    $stmt = $pdo->prepare("SELECT single FROM select_types WHERE stid = ?");
    $stmt->execute([$stid]);
    $single = $stmt->fetchColumn();
    $stmt->closeCursor();
    if ($single == 0) {
      // store select value
      $logvalue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : 0;
        // insert new value
      $insert_stmt = $pdo->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->execute([$trainkey, $tbid, $stid, $logvalue, $valueblank, $logdate, $today, $logkey]);
      $newid = $pdo->lastInsertId();

      $insert_stmt->closeCursor();
    }
    if ($single == 1 && (isset($_POST[$posmarker]))) {
      // will be array, step through values
        foreach(($_POST[$posmarker]) as $mval) {
          // insert new value
          $insert_stmt = $pdo->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
          $insert_stmt->execute([$trainkey, $tbid, $stid, $mval, $valueblank, $logdate, $today, $logkey]);
          $newid = $pdo->lastInsertId();

          $insert_stmt->closeCursor();
        }
    }
    if ($single == 2 || $single == 4 || $single == 5 || $single == 6) {
      // expect text or numeric or hr:min input
      $logvalue = isset($_POST[$posmarker]) ? $_POST[$posmarker] : '';
      // insert new value
      $insert_stmt = $pdo->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->execute([$trainkey, $tbid, $stid, $value0, $logvalue, $logdate, $today, $logkey]);
      $newid = $pdo->lastInsertId();

      $insert_stmt->closeCursor();
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
      $insert_stmt = $pdo->prepare("INSERT INTO trainee_log (trainkey, tbid, stid, pid, select_val, date_added, date_modified, logkey) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_stmt->execute([$trainkey, $tbid, $stid, $value0, $logvalue, $logdate, $today, $logkey]);
      $newid = $pdo->lastInsertId();

      $insert_stmt->closeCursor();
    }
  }
  $tableset->closeCursor();

  $modifylog = 1; # either way, there is now data for this date
}

// which Table is this and is this user entitled to it
$stmt = $pdo->prepare("SELECT tab_name, tab_notes FROM tabs_tbl WHERE tbid = ?");
$stmt->execute([$tbid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tab_name = $row['tab_name'];
$tab_notes = $row['tab_notes'];
$stmt->closeCursor();

$vids = $pdo->prepare("SELECT ttid FROM trainee_tab_link ttl JOIN tabs_tbl tt ON ttl.tbid = tt.tbid WHERE ttl.tbid = ? AND ttl.trainkey = ? AND tt.isvis = 1");
$vids->execute([$tbid, $trainkey]);
$numlinks = $vids->rowCount();
$vids->closeCursor();

?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $tab_name ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="//cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
  </head>
  <?php
    if (login_check($pdo) != false) {
      // logged in only!
    ?>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <?php
    if ($numlinks > 0) { // entitled to table
    ?>

    <div class="container-fluid py-5" id="main">
      <div class="container">
        <div class="row">
          <div class="col-xs-12">
            <?php
            if ($tab_name != '') {
              echo "<div class=\"row justify-content-center mb-4\">";
              echo "<div class=\"col-md-10\">";
              echo "<div class=\"card border-primary\">";
              echo "<div class=\"card-header bg-primary text-white d-flex justify-content-between align-items-center\" style=\"cursor: pointer; z-index: 1100; position: relative;\" onclick=\"toggleNotes()\">";
              echo "<h2 class=\"mb-0\" style=\"z-index: 1101; position: relative;\">" . htmlentities($tab_name) . "</h2>";
              echo "<div class=\"d-flex align-items-center\" style=\"z-index: 1102; position: relative;\">";
              echo "<i class=\"fa fa-arrow-down text-white mr-2\" style=\"opacity: 0.9; font-size: 1rem; z-index: 1103; position: relative;\"></i>";
              echo "<i class=\"fa fa-chevron-down text-white\" id=\"notesToggleIcon\" style=\"font-size: 2rem; font-weight: 900; color: #ffffff !important; z-index: 1104; position: relative;\"></i>";
              echo "</div>";
              echo "</div>";
              
              // Display table notes if available
              if (!empty($tab_notes)) {
                echo "<div class=\"card-body\" id=\"notesContent\" style=\"display: block;\">";
                echo "<div class=\"row\">";
                echo "<div class=\"col-md-12\">";
                echo "<div class=\"d-flex align-items-start\">";
                echo "<i class=\"fa fa-info-circle text-primary mr-3 mt-1\" style=\"font-size: 1.2rem;\"></i>";
                echo "<div class=\"flex-grow-1\">";
                echo $tab_notes;
                echo "</div>";
                echo "</div>";
                echo "</div>";
                echo "</div>";
                
                // Add the Create New Data Set button at the bottom of the notes
                echo "<div class=\"card-footer bg-light text-center\" id=\"notesFooter\" style=\"display: block;\">";
                echo "<form method=\"post\" name=\"logbook\" action=\"" . $formurl . "\" class=\"mb-0\">";
                echo "<input type=\"hidden\" name=\"done\" value=\"date\">";
                echo "<input type=\"hidden\" name=\"tab\" value=\"" . $tbid . "\">";
                echo "<button type=\"submit\" class=\"btn btn-primary px-4 py-2\">";
                echo "<i class=\"fa fa-plus mr-2\"></i>Create New Data Set";
                echo "</button>";
                echo "</form>";
                echo "</div>";
                
                echo "</div>";
              } else {
                // If no notes, still show the button below the header
                echo "<div class=\"card-footer bg-light text-center\">";
                echo "<form method=\"post\" name=\"logbook\" action=\"" . $formurl . "\" class=\"mb-0\">";
                echo "<input type=\"hidden\" name=\"done\" value=\"date\">";
                echo "<input type=\"hidden\" name=\"tab\" value=\"" . $tbid . "\">";
                echo "<button type=\"submit\" class=\"btn btn-primary px-4 py-2\">";
                echo "<i class=\"fa fa-plus mr-2\"></i>Create New Data Set";
                echo "</button>";
                echo "</form>";
                echo "</div>";
              }
              
              echo "</div>";
              echo "</div>";
              echo "</div>";
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
            <?php
              echo $page_txt2;
              //echo "logkey $logkey | modifylog $modifylog";
            ?>
          </div>
        </div>
      </div>


      



      <?php
      if ($done != 'no') {
        // we don't want to show the logbook until a date has been selected
      ?>
      <form method="post" name="logbook" action="<?php echo $formurl ?>">
        <div class="row">
          <div class="col">
            <h3>E-log Data Entry</h3>
            <?php
            if ($modifylog == 0) {
              echo $page_txt9;
            } else {
              echo $page_txt10;
            }
            //echo "<p>#TEST# $trainkey, $tbid, $logdate</p>";
            ?>
          </div>
        </div>
        <div class="row">
          <?php
          // loop through fields in order
          $tableset = $pdo->prepare("SELECT stid FROM tab_fields WHERE tbid = ? AND sort_order != ? ORDER BY sort_order ASC");
          $tableset->execute([$tbid, $value0]); 
          while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
            $stid = $row['stid'];
            // get field name
            $stmt = $pdo->prepare("SELECT str, single FROM select_types WHERE stid = ?");
            $stmt->execute([$stid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $str = $row['str'];
            $single = $row['single'];
            $stmt->closeCursor();
            // get existing values (with a twist if there are multiple vales)
            $stmt = $pdo->prepare("SELECT pid, select_val FROM trainee_log WHERE trainkey = ? AND tbid = ? AND stid = ? AND logkey = ?");
            $stmt->execute([$trainkey, $tbid, $stid, $logkey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $expid = $row['pid'];
            $exlogvalue = $row['select_val'];
            $stmt->closeCursor();
            echo "<div class=\"col-xs-12 col-sm-6 col-md-4 col-xl-3 mb-3 px-3\">";
            echo "<div class=\"form-group\">";
            // what type of input
            if ($single == 0) {
              // *single* select menu: loop through select values
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<select class=\"form-control\" id=\"stid$stid\" name=\"stid$stid\">";
              $fieldset = $pdo->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ");
              $fieldset->execute([$stid]);
              while ($row = $fieldset->fetch(PDO::FETCH_ASSOC)){
                $pid = $row['pid'];
                $select_val = $row['select_val'];
                if ($pid == $expid) {
                  $selected = 'selected';
                } else {
                  $selected = '';
                }
                echo "<option value=\"$pid\" $selected>$select_val</option>";
              }
              $fieldset->closeCursor();
              echo "</select>";
            }
            if ($single == 1) {
              // *multi* select menu: loop through select values, send as array
              // for old values, need to build array
              $exarr = array();
              // The twist: loop through existing values
              $fieldset = $pdo->prepare("SELECT pid FROM trainee_log WHERE trainkey = ? AND tbid = ? AND stid = ? AND date_added = ?");
              $fieldset->execute([$trainkey, $tbid, $stid, $logdate]);
              while ($row = $fieldset->fetch(PDO::FETCH_ASSOC)){
                array_push($exarr, $row['pid']); # add to array for checking in select
              }
              $fieldset->closeCursor();
              
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<select class=\"form-control\" id=\"stid$stid\" name=\"stid".$stid."[]\" multiple>";
              $fieldset = $pdo->prepare("SELECT pid, select_val FROM select_gen WHERE stid = ? ");
              $fieldset->execute([$stid]);
              while ($row = $fieldset->fetch(PDO::FETCH_ASSOC)){
                $pid = $row['pid'];
                $select_val = $row['select_val'];
                if (in_array($pid, $exarr)) {
                  // if the value is in the array show it as selected
                  $selected = 'selected';
                  //$yesmkr = ' &#10004;'; # tick mark
                  $yesmkr = ''; # temp; omit tick mark
                } else {
                  $selected = '';
                  $yesmkr = '';
                }
                echo "<option value=\"$pid\" $selected>$select_val $yesmkr</option>";
              }
              $fieldset->closeCursor();
              echo "</select>";
              //print_r($exarr);
              unset($exarr); # array no longer needed
            }
            if ($single == 2) {
              // text input menu
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<input type=\"text\" class=\"form-control\" id=\"stid$stid\" name=\"stid$stid\" value=\"$exlogvalue\">";
            }
            if ($single == 3) {
              // date input menu
              // reformat any existing value to dd-mm-yyyy, $exlogvalue will be yyyymmdd
              if ($exlogvalue > 0) {
                $fromddd = substr($exlogvalue, 6, 2);
                $frommm = substr($exlogvalue, 4, 2);
                $fromyyyy = substr($exlogvalue, 0, 4);
                $dispdate = $fromddd.'-'.$frommm.'-'.$fromyyyy; # convert to yyyymmdd
              } else {
                $dispdate = '';
              }
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<input type=\"text\" class=\"form-control datepicker\" id=\"stid$stid\" name=\"stid$stid\" value=\"$dispdate\">";
            }
            if ($single == 6) {
              // time 00:00 input menu (step is in seconds, 600 =10 mins)
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<input type=\"time\" class=\"form-control\" id=\"stid$stid\" name=\"stid$stid\"  min=\"00:00\" max=\"12:00\" step=\"300\" value=\"$exlogvalue\">";
            }
            if ($single == 4) {
              // numeric 0.1 step input menu
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<input type=\"number\" class=\"form-control\" id=\"stid$stid\" name=\"stid$stid\" min=\"0\" step=\"0.1\" value=\"$exlogvalue\">";
            }
            if ($single == 5) {
              // numeric integer step input menu
              echo "<label for=\"stid$stid\">$str</label>";
              echo "<input type=\"number\" class=\"form-control\" id=\"stid$stid\" name=\"stid$stid\" min=\"0\" step=\"1\" value=\"$exlogvalue\">";
            }

            echo "</div>";
            echo "</div>";
          }
          $tableset->closeCursor();
          ?>
        </div>
        <div class="row">
          <div class="col-xs-12 col-sm-2">
            <input type="hidden" name="done" value="done">
            <input type="hidden" name="dupe" value="dupe"><!-- create new record -->
            <input type="hidden" name="tab" value="<?php echo $tbid ?>">
            <input type="hidden" name="logkey" value="<?php echo $logkey ?>">
            <input type="hidden" name="logdate" value="<?php echo date("dmY", $todaydisp)?>">
            <button type="submit" class="btn btn-block btn-nhs float-right">Save</button>
          </div>
        </div>
      </form>
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
    if (login_check($pdo) != false) {
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
          order: [[1, 'asc'], [7, 'asc']],
      });
  });
    </script>
    
    <script>
    // Toggle notes visibility function - preserve layout flow
    function toggleNotes() {
      const notesContent = document.getElementById('notesContent');
      const notesFooter = document.getElementById('notesFooter');
      const toggleIcon = document.getElementById('notesToggleIcon');
      
      if (notesContent && notesFooter && toggleIcon) {
        if (notesContent.style.maxHeight === '0px' || notesContent.style.maxHeight === '') {
          // Show notes
          notesContent.style.maxHeight = 'none';
          notesContent.style.overflow = 'visible';
          notesContent.style.padding = '';
          notesFooter.style.maxHeight = 'none';
          notesFooter.style.overflow = 'visible';
          notesFooter.style.padding = '';
          toggleIcon.className = 'fa fa-chevron-down text-white';
        } else {
          // Hide notes
          notesContent.style.maxHeight = '0px';
          notesContent.style.overflow = 'hidden';
          notesContent.style.padding = '0';
          notesFooter.style.maxHeight = '0px';
          notesFooter.style.overflow = 'hidden';
          notesFooter.style.padding = '0';
          toggleIcon.className = 'fa fa-chevron-up text-white';
        }
      }
    }
    </script>
  </body>
  <?php
  }// logged in only!
  ?>
</html>