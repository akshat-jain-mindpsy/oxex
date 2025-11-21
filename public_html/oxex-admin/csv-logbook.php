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
$stmt = $supabase_pdo->prepare("SELECT name, who_by, date_added, date_modified, last_used FROM trainee_tbl WHERE trainkey = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $name = $row['name'];
    $who_by = $row['who_by'];
    $date_added = $row['date_added'];
    $date_modified = $row['date_modified'];
    $last_used = $row['last_used'];
}
   $date_added = $date_added ? strtotime($date_added) : false;


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

$tableset = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl  WHERE (tbid != ? AND tbid != ? AND tbid != ? AND tbid != ?)");
$tableset->execute([$value11, $value14, $value15, $value16]);
while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
    $tbid = (int)$row['tbid'];
    $tab_name = $row['tab_name'];
	if ($tabctr > 0) {
		// so, don't do extra space first time only
		$nextline = $nextline."\n\n";
	}
	// now do Table Title
	$nextline = $nextline."$tab_name\n";
	// if the trainee has a pass for this table, show the supervisor and date
	$numpass = 0; # reset
	$stmt = $supabase_pdo->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
	$stmt->execute([$tbid, $which]);
	$numpass = $stmt->rowCount();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$who_notes = null;
	$date_signed = null;
	if ($row) {
	    $who_notes = $row['who_by'];
	    $date_signed = $row['date_added'];
	}
	$date_signed = $date_signed ? strtotime($date_signed) : false;
	if ($numpass > 0) {
		// who signed it off?
	  $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
	  $stmt->execute([$who_notes]);
	  $row = $stmt->fetch(PDO::FETCH_ASSOC);
	  if ($row) {
	      $supername = $row['realname'];
	  }
	  $date_display = $date_signed ? date("D jS M Y", $date_signed) : 'N/A';
	  $nextline = $nextline."Signed off: , ".$supername.",".$date_display."\n";
	}

	$tabctr++; # increment counter to now add space before titles

  // loop through fields for the chosen table and get field name 
  // omit data type 'multiple'
  $tabset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.str FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
  $tabset->execute([$tbid, $value0]);
  while ($row = $tabset->fetch(PDO::FETCH_ASSOC)){
    $stid = (int)$row['stid'];
    $str = $row['str'];
    // output the field names
    //echo "<th>$str</th>";
    $nextline = $nextline."$str,";
    }
  $nextline = $nextline."\n"; # end of line

	// now do one row per set of data for this person/table
	// No longer look at dates 
	// AND date_added >= ? AND date_added <= ?
	// , $valueyearstart, $valueyearend
	$dataset = $supabase_pdo->prepare("SELECT MAX(date_added) AS date_added, logkey FROM trainee_log WHERE trainkey = ? AND tbid = ? GROUP BY logkey ORDER BY date_added DESC ");
	  $dataset->execute([$which, $tbid]); 
	  while ($row = $dataset->fetch(PDO::FETCH_ASSOC)){
	    $date_added = $row['date_added'];
	    $tablelogkey = $row['logkey'];
	    $exlogdate = strtotime($date_added);
	    $exlogdate = date("d-m-Y", $exlogdate);
	    // loop through chosen fields
	    
	    $ctr = 0; # a counter to know the first column
	    // loop through same fields as the <th> cells
	    $resultset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
	    $resultset->execute([$tbid, $value0]);
	    while ($row = $resultset->fetch(PDO::FETCH_ASSOC)){
	      $stid = (int)$row['stid'];
	      $single = (int)$row['single'];
	      $exselect_val = '';
	      $expid = '';
	      // get data for each in turn
	      // TAKEN TBID out of the search!!??
	      $stmt = $supabase_pdo->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ?  AND stid = ? AND logkey = ?");
	      $stmt->execute([$which, $stid, $tablelogkey]);
	      $row = $stmt->fetch(PDO::FETCH_ASSOC);
	      if ($row) {
	          $extlogid = (int)$row['tlogid'];
	          $expid = (int)$row['pid'];
	          $exselect_val = $row['select_val'];
	      }
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
	        $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
	        $stmt->execute([$expid]);
	        $row = $stmt->fetch(PDO::FETCH_ASSOC);
	        if ($row) {
	            $exselect_val = $row['select_val'];
	        }
	        //echo "<td>$exselect_val</td>\n";
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
          $fieldset = $supabase_pdo->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
          $fieldset->execute([$stid, $tablelogkey]);
          while ($row = $fieldset->fetch(PDO::FETCH_ASSOC)){
            array_push($exarr, (int)$row['pid']); # add to array for checking in select
          }
          // now make array unique as this will create for each seperate record
          $exarruq = (array_unique($exarr));
          // now loop through array of pids to get values
          $exselect_val = '';
          $mult_val = 0;
          foreach($exarruq as $x) {
            // fetch the select menu value for $pid
            $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
            $stmt->execute([$x]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $mult_val = $row['select_val'];
            }
            $exselect_val = $exselect_val." ".$mult_val;
          }
          $nextline = $nextline."".$exselect_val.",";
          $exselect_val = '';
          unset($exarruq);
        }
	      $ctr++; # increment counter
	    }
	    $nextline = $nextline."\n"; # end of line
	      
	  }
}
$nextline = $nextline."";

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
  $stmt = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE tbid = ?");
  $stmt->execute([$CSvalue]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
      $tbid = (int)$row['tbid'];
      $tab_name = $row['tab_name'];
  }
  
  $nextline = $nextline."$tab_name\n"; # do Table Title

  $numpass = 0; # reset
  $stmt = $supabase_pdo->prepare("SELECT who_by, date_added FROM trainee_report_ok WHERE super_pass = ? AND trainkey = ?");
  $stmt->execute([$tbid, $which]);
  $numpass = $stmt->rowCount();
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $who_notes = null;
  $date_signed = null;
  if ($row) {
      $who_notes = $row['who_by'];
      $date_signed = $row['date_added'];
  }
  $date_signed = $date_signed ? strtotime($date_signed) : false;
  if ($numpass > 0) {
    // who signed it off?
    $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
    $stmt->execute([$who_notes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $supername = $row['realname'];
    }
    $date_display = $date_signed ? date("D jS M Y", $date_signed) : 'N/A';
    $nextline = $nextline."Signed off: , ".$supername.",".$date_display."\n";
  }
      
      // loop through fields for the generic table ($tbid == 1) and get field name 
      $tabset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.str, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
      $tabset->execute([$value1, $value0]);
      while ($row = $tabset->fetch(PDO::FETCH_ASSOC)){
        $stid = (int)$row['stid'];
        $str = $row['str'];
        $dispsingle = (int)$row['single'];
        // output the field names
        $nextline = $nextline."$str,";
        }
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
      $dataset = $supabase_pdo->prepare("SELECT MAX(date_added) AS date_added, logkey FROM trainee_log WHERE trainkey = ? AND (stid = ? AND pid = ?) GROUP BY logkey ORDER BY date_added DESC ");
      $dataset->execute([$which, $stidchk1, $pidchk1]); 
      while ($row = $dataset->fetch(PDO::FETCH_ASSOC)){
        $date_added = $row['date_added'];
        $tablelogkey = $row['logkey'];
        $exlogdate = strtotime($date_added);
        $exlogdate = date("d-m-Y", $exlogdate);
        // loop through chosen fields

          $ctr = 0; # a counter to know the first column
          // loop through same fields as the <th> cells
          $resultset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
          $resultset->execute([$value1, $value0]);
          while ($row = $resultset->fetch(PDO::FETCH_ASSOC)){
            $stid = (int)$row['stid'];
            $single = (int)$row['single'];
            $exselect_val = '';
            $expid = '';
            // get data for each in turn
            $stmt = $supabase_pdo->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ? AND stid = ? AND logkey = ?");
            $stmt->execute([$which, $stid, $tablelogkey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $extlogid = (int)$row['tlogid'];
                $expid = (int)$row['pid'];
                $exselect_val = $row['select_val'];
            }
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
              $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
              $stmt->execute([$expid]);
              $row = $stmt->fetch(PDO::FETCH_ASSOC);
              if ($row) {
                  $exselect_val = $row['select_val'];
              }
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
              $fieldset = $supabase_pdo->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
              $fieldset->execute([$stid, $tablelogkey]);
              while ($row = $fieldset->fetch(PDO::FETCH_ASSOC)){
                array_push($exarr, (int)$row['pid']); # add to array for checking in select
              }
              // now make array unique as this will create for each seperate record
              $exarruq = (array_unique($exarr));
              // now loop through array of pids to get values
              $exselect_val = '';
              $mult_val = 0;
              foreach($exarruq as $x) {
                // fetch the select menu value for $pid
                $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                $stmt->execute([$x]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $mult_val = $row['select_val'];
                }
                $exselect_val = $exselect_val." ".$mult_val;
              }
              $nextline = $nextline."".$exselect_val.",";
              $exselect_val = '';
              unset($exarruq);
            }
            $ctr++; # increment counter

          }
          $nextline = $nextline."\n"; # end of line

      }
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