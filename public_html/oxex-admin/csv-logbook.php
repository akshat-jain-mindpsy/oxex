<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
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
   $date_added = strtotime($date_added);


$firstline = "$name,".date("D jS M Y", $todaydisp)."\n";
$nextline = "";
$tabctr = 0;

// some tables are special cases where we display logbook lines
// according to the Clinical specialism ($stid == 2 || $stid == 75)
// CYP, LD, OA, WAA
$CSarr = array();
array_push($CSarr,11, 14, 15, 16);
// 11 = Children and Young People (CYP)
// 14 = Working Age Adults (WAA)
// 15 = Older People (OA)
// 16 = Learning Disability (LD)

// omit if in $CSarr as these are handled later
$value11 = 11;
$value14 = 14;
$value15 = 15;
$value16 = 16;

$tableset = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl  WHERE (tbid != ? && tbid != ? && tbid != ? && tbid != ?)");
$tableset->bind_param("iiii", $value11, $value14, $value15, $value16);
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
	$date_signed = strtotime($date_signed);
	if ($numpass > 0) {
		// who signed it off?
	  $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
	  $stmt->bind_param("s", $who_notes);
	  $stmt->execute();
	  $stmt->store_result();
	  $stmt->bind_result($supername);
	  $stmt->fetch();
	  $stmt->close();
	  $nextline = $nextline."Signed off: , ".$supername.",".date("D jS M Y", $date_signed)."\n";
	}

	$tabctr++; # increment counter to now add space before titles

	// loop through fields for the chosen table and get field name 
  // omit data type 'multiple'
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
	// No longer look at dates 
	// AND date_added >= ? AND date_added <= ?
	// , $valueyearstart, $valueyearend
	$dataset = $mysqli->prepare("SELECT date_added, logkey FROM trainee_log WHERE trainkey = ? AND tbid = ?  GROUP BY logkey ORDER BY date_added DESC ");
	  $dataset->bind_param("si", $which, $tbid); 
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
	        //if ($ctr == 0) {
	        //  echo "<td><a class=\"btn btn-sm btn-nhs\" href=\"$formurl?table=table&amp;tab=$tbid&amp;logkey=$tablelogkey\">$exselect_val</a></td>\n";
	        //} else {
	          //echo "<td>$exselect_val</td>\n";
	          $nextline = $nextline."$exselect_val,";
	          //echo "<td>$exselect_val ($tbid $stid $tablelogkey)</td>\n";
	        //}
	        
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
	        //echo "<td>$exselect_val</td>\n";
	        $nextline = $nextline."$exselect_val,";
	      }
	      if ($single == 3) {
	        // $exselect_val is date YYYYMMDD
	        $exselect_val = strtotime($exselect_val);
	        //echo "<td>".date("d-m-y", $exselect_val)."</td>\n";
	        $nextline = $nextline."".date("d-m-y", $exselect_val).",";
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
$nextline = $nextline."";
$tableset->close();

// some tables are special cases where we display logbook lines
// according to the Clinical specialism ($stid == 2 || $stid == 75)
// CYP, LD, OA, WAA
// TABLES:
// 11 = Children and Young People (CYP)
// 14 = Working Age Adults (WAA)
// 15 = Older People (OA)
// 16 = Learning Disability (LD)

// Clinical specialism ($stid == 2):
// $pid == 4 = Children and Young People (CYP)
// $pid == 1 = Working Age Adults (WAA)
// $pid == 2 = Older People (OA)
// $pid == 3 = Learning Disability (LD)

// Additional Clinical specialism ($stid == 75):
// $pid == 654 = Children and Young People (CYP)
// $pid == 651 = Working Age Adults (WAA)
// $pid == 652 = Older People (OA)
// $pid == 653 = Learning Disability (LD)

// perform same routine but not limited to table tbid
foreach ($CSarr as $CSvalue) {
  $stmt = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = ?");
  $stmt->bind_param("i", $CSvalue);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($tbid, $tab_name);
  $stmt->fetch();
  $stmt->close();
  
  $nextline = $nextline."$tab_name\n"; # do Table Title

  $numpass = 0; # reset
  $stmt = $mysqli->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
  $stmt->bind_param("is", $tbid, $which);
  $stmt->execute();
  $stmt->store_result();
  $stmt->bind_result($who_notes, $date_signed);
  $numpass = $stmt->num_rows;
  $stmt->fetch();
  $stmt->close();
  $date_signed = strtotime($date_signed);
  if ($numpass > 0) {
    // who signed it off?
    $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
    $stmt->bind_param("s", $who_notes);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($supername);
    $stmt->fetch();
    $stmt->close();
    $nextline = $nextline."Signed off: , ".$supername.",".date("D jS M Y", $date_signed)."\n";
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
      $dataset = $mysqli->prepare("SELECT date_added, logkey FROM trainee_log WHERE trainkey = ? AND (stid = ? AND pid = ?) GROUP BY logkey ORDER BY date_added DESC ");
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
              //if ($ctr == 0) {
              //  echo "<td><a class=\"btn btn-sm btn-nhs\" href=\"$formurl?table=table&amp;tab=$tbid&amp;logkey=$tablelogkey\">$exselect_val</a></td>\n";
              //} else {
                $nextline = $nextline."$exselect_val,";
                //echo "<td>$exselect_val ($tbid $stid $tablelogkey)</td>\n";
              //}
              
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

} # loop through selected tbid values



// create file
$finalline = $firstline.$nextline;
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Length: " . strlen($finalline));
header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=$filename");
echo $finalline;
?>