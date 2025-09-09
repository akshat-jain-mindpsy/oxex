<?php
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
 // find the required trainee
$stmt = $mysqli->prepare("SELECT name, who_by, date_added, date_modified, last_used FROM trainee_tbl WHERE trainkey = ?");
$stmt->bind_param("s", $which);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($name, $who_by, $date_added, $date_modified, $last_used);
$stmt->fetch();
$stmt->close();
   $date_added = $date_added ? strtotime($date_added) : false;


$firstline = "$name,".date("D jS M Y", $todaydisp)."\n";
$nextline = "";
$tabctr = 0;
$tableset = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($tbid, $tab_name);
while ($tableset->fetch()){
	if ($tabctr > 0) {
		// so, don't do extra space first time only
		$nextline = $nextline."\n\n";
	}
	// now do Table Title
	$nextline = $nextline."$tab_name\n";
	// if the trainee has a pass for this table, show the supervisor and date
	$numpass = 0; # reset
	$stmt = $mysqli->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
	$stmt->bind_param("is", $tbid, $which);
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($who_notes, $date_signed);
	$numpass = $stmt->num_rows;
	$stmt->fetch();
	$stmt->close();
	$date_signed = $date_signed ? strtotime($date_signed) : false;
	if ($numpass > 0) {
		// who signed it off?
	  $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
	  $stmt->bind_param("s", $who_notes);
	  $stmt->execute();
	  $stmt->store_result();
	  $stmt->bind_result($supername);
	  $stmt->fetch();
	  $stmt->close();
	  $date_display = $date_signed ? date("D jS M Y", $date_signed) : 'N/A';
	  $nextline = $nextline."Signed off: , ".$supername.",".$date_display."\n";
	}

	$tabctr++; # increment counter to now add space before titles

	// loop through fields for the chosen table and get field name 
  $tabset = $mysqli->prepare("SELECT tab_fields.stid, select_types.str FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
  $tabset->bind_param("ii", $tbid, $value0);
  $tabset->execute();
  $tabset->store_result();
  $tabset->bind_result($stid, $str);
  while ($tabset->fetch()){
    // output the field names
    //echo "<th>$str</th>";
    $nextline = $nextline."$str,";
    }
  $tabset->close();
  $nextline = $nextline."\n"; # end of line

	// now do one row per set of data for this person/table
	$dataset = $mysqli->prepare("SELECT date_added, logkey FROM trainee_log WHERE trainkey = ? AND tbid = ? AND date_added >= ? AND date_added <= ? GROUP BY logkey ORDER BY date_added DESC ");
	  $dataset->bind_param("siii", $which, $tbid, $valueyearstart, $valueyearend);
	  $dataset->execute();
	  $dataset->store_result();
	  $dataset->bind_result($date_added, $tablelogkey);
	  while ($dataset->fetch()){
	    $exlogdate = strtotime($date_added);
	    $exlogdate = date("d-m-Y", $exlogdate);
	    // loop through chosen fields
	    
	    $ctr = 0; # a counter to know the first column
	    // loop through same fields as the <th> cells
	    // 20221125, removed AND select_types.single != ? ($value1)
	    $resultset = $mysqli->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
      $resultset->bind_param("ii", $tbid, $value0);
	    $resultset->execute();
	    $resultset->store_result();
	    $resultset->bind_result($stid, $single);
	    while ($resultset->fetch()){
	      $exselect_val = '';
	      $expid = '';
	      // get data for each in turn
	      // TAKEN TBID out of the search!!??
	      $stmt = $mysqli->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ?  AND stid = ? AND logkey = ?");
	      $stmt->bind_param("sis", $which,  $stid, $tablelogkey);
	      $stmt->execute();
	      $stmt->store_result();
	      $stmt->bind_result($extlogid, $expid, $exselect_val);
	      $stmt->fetch();
	      $stmt->close();
	      // format data according to data type
	      // 0=single select, 2 = text, 3 = date, 4=numeric (0.1) 5=numeric(int)
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
          if ($exselect_val && $exselect_val > 1) {
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
}
//$nextline = $nextline."";
$tableset->close();


// create file
$finalline = $firstline.$nextline;
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Length: " . strlen($finalline));
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$filename");
echo $finalline;
?>