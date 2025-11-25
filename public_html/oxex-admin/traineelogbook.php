<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$which = isset($_GET['which']) ? $_GET['which'] : '';
 // find the required record
$stmt = $supabase_pdo->prepare("SELECT name, who_by, date_added, date_modified, last_used FROM trainee_tbl WHERE trainkey = ?");
$stmt->execute([$which]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$name = $row ? $row['name'] : '';
$who_by = $row ? $row['who_by'] : '';
$date_added = $row ? $row['date_added'] : 0;
$date_modified = $row ? $row['date_modified'] : 0;
$last_used = $row ? $row['last_used'] : 0;
   $date_added = strtotime($date_added);
   $date_modified = strtotime($date_modified);
   if ($last_used != 0) {
      $last_used = strtotime($last_used);
   }
   // who changed last?
 $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
  $stmt->execute([$who_by]);
  $who_by = $stmt->fetchColumn();

$pagetitle = "Trainee Logbook - $name";
$subtitle = "Trainee";
$listurl = "traineedetail.php?which=$which";
$listname = $name;
$done = isset($_POST['done']) ? $_POST['done'] : '';
// adding pass
if ($done == "passfail" && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) { 
   // add new notes 
  $which = isset($_POST['which']) ? $_POST['which'] : ''; # 32 char str
  $super_pass = isset($_POST['super_pass']) ? $_POST['super_pass'] : 0;
  $super_txt = isset($_POST['super_txt']) ? $_POST['super_txt'] : '';
  $insert_stmt = $supabase_pdo->prepare("INSERT INTO trainee_report_ok (trainkey, who_by, super_pass, super_txt, date_added, date_modified) VALUES (?, ?, ?, ?, ?, ?)");
  $insert_stmt->execute([$which, $usrkey, $super_pass, $super_txt, $today, $today]);
  $newid = (int)$supabase_pdo->lastInsertId();
}

if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
</head>

<?php
 
?>
<body>
   <div class="wrapper">
      <!-- top, left side and right navbars -->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small> </div> <a href="#passfail" class="btn btn-pink ml-5">Course tutor sign off</a> <a href="csv-logbook.php?which=<?php echo $which ?>" class="btn btn-secondary ml-5">Download CSV Logbook</a>
            </div>

            <div class="row my-5">
               <div class="col-xl-12">
<?php
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
$datestart = 20200101; #TEMP DATES
$dateend = 20991231; #TEMP DATES
$valueyearstart = 20200101;
$valueyearend = 20991231;
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
$value0 = 0;
$value1 = 1;
$tableset = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl WHERE (tbid != ? AND tbid != ? AND tbid != ? AND tbid != ?)");
$tableset->execute([$value11, $value14, $value15, $value16]);
$tab_rows = $tableset->fetchAll(PDO::FETCH_ASSOC);
foreach ($tab_rows as $trow){
  $tbid = $trow['tbid'];
  $tab_name = $trow['tab_name'];
echo "<h2 class=\"bg-dark text-white p-2\">$tab_name (t$tbid) <a href=\"#passfail\" class=\"btn btn-sm btn-pink ml-3\">Course tutor sign off</a></h2>";
?>
<div class="table-responsive">
<table class="table table-hover table-sm maintable">
  <thead>
    <tr>
      <?php
      // loop through fields for the chosen table and get field name 
      // 20221125, removed  AND select_types.single != ?, ($value1)

      $tabset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.str, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
      $tabset->execute([$tbid, $value0]);
      $field_rows = $tabset->fetchAll(PDO::FETCH_ASSOC);
      foreach ($field_rows as $frow){
        $stid = $frow['stid'];
        $str = $frow['str'];
        $dispsingle = $frow['single'];
        // output the field names
        echo "<th>$str (s$stid)</th>";
        }
      ?>
    </tr>
  </thead>
  <tbody>
    <?php     
        // now do one row per set of data for this person/table
        // only do dates for this year

        // define 'set' as a date
        // then get any results entered for the $stid for all dates
      $dataset = $supabase_pdo->prepare("SELECT MAX(date_added) AS date_added, logkey FROM trainee_log WHERE trainkey = ? AND tbid = ? GROUP BY logkey ORDER BY MAX(date_added) DESC ");
      $dataset->execute([$which, $tbid]); 
      $data_rows = $dataset->fetchAll(PDO::FETCH_ASSOC);
      foreach ($data_rows as $drow){
        $date_added = $drow['date_added'];
        $tablelogkey = $drow['logkey'];
        $exlogdate = strtotime($date_added);
        $exlogdate = date("d-m-Y", $exlogdate);
        // loop through chosen fields
        echo "<tr>";
        $ctr = 0; # a counter to know the first column
        // loop through same fields as the <th> cells
        // 20221125, removed  AND select_types.single != ?, ($value1)
        $resultset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
        $resultset->execute([$tbid, $value0]);
        $res_rows = $resultset->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res_rows as $rrow){
          $stid = $rrow['stid'];
          $single = $rrow['single'];
          $exselect_val = '';
          $expid = '';
          // get data for each in turn
          // TAKEN TBID out of the search!!??
          $stmt = $supabase_pdo->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ?  AND stid = ? AND logkey = ?");
          $stmt->execute([$which,  $stid, $tablelogkey]);
          $row = $stmt->fetch(PDO::FETCH_ASSOC);
          $extlogid = $row ? $row['tlogid'] : null;
          $expid = $row ? $row['pid'] : null;
          $exselect_val = $row ? $row['select_val'] : '';
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
              echo "<td>$exselect_val</td>\n";
              //echo "<td>$exselect_val ($tbid $stid $tablelogkey)</td>\n";
            //}
            
          }
          if ($single == 0) {
            // fetch the select menu value for $pid
            $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
            $stmt->execute([$expid]);
            $exselect_val = $stmt->fetchColumn();
            echo "<td>$exselect_val</td>\n";
          }
          if ($single == 3) {
            // $exselect_val is date YYYYMMDD
            if ($exselect_val > 1) {
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
            $fieldset = $supabase_pdo->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
            $fieldset->execute([$stid, $tablelogkey]);
            $exarr = $fieldset->fetchAll(PDO::FETCH_COLUMN);
            // now make array unique as this will create for each seperate record
            $exarruq = (array_unique($exarr));
            // now loop through array of pids to get values
            $exselect_val = '';
            $mult_val = 0;
            foreach($exarruq as $x) {
              // fetch the select menu value for $pid
              $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
              $stmt->execute([$x]);
              $mult_val = $stmt->fetchColumn();
              $exselect_val = $exselect_val." ".$mult_val;
            }
            echo "<td>$exselect_val</td>\n";
            $exselect_val = '';
            unset($exarruq);
          }
          $ctr++; # increment counter
        }
        $resultset->closeCursor();
        echo "</tr>";
          
      }
      // PDO auto-closes
    ?>
  </tbody>
</table>
</div>
<?php
}
$tableset->closeCursor();
?>
<?php
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
$stmt = $supabase_pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
$stmt->execute([$CSvalue]);
$tab_name = $stmt->fetchColumn();
  echo "<h2 class=\"bg-dark text-white p-2\">$tab_name (t$tbid - M) <a href=\"#passfail\" class=\"btn btn-sm btn-pink ml-3\">Course tutor sign off</a></h2>";
  echo "<div class=\"table-responsive\">";
  echo "<table class=\"table table-hover table-sm maintable\">";
  echo "<thead>";
  echo "  <tr>";
      
      // loop through fields for the generic table ($tbid == 1) and get field name 
      $tabset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.str, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
      $tabset->execute([$value1, $value0]);
      $rows = $tabset->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as $r){
        $stid = $r['stid'];
        $str = $r['str'];
        $dispsingle = $r['single'];
        // output the field names
        echo "<th>$str (s$stid)</th>";
        }
      $tabset->closeCursor();

  echo "  </tr>";
  echo "</thead>";
  echo "<tbody>";

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
      $dataset = $supabase_pdo->prepare("SELECT MAX(date_added) AS date_added, logkey FROM trainee_log WHERE trainkey = ? AND (stid = ? AND pid = ?) GROUP BY logkey ORDER BY MAX(date_added) DESC ");
      $dataset->execute([$which, $stidchk1, $pidchk1]); 
      $rows = $dataset->fetchAll(PDO::FETCH_ASSOC);
      foreach ($rows as $r){
        $date_added = $r['date_added'];
        $tablelogkey = $r['logkey'];
        $exlogdate = strtotime($date_added);
        $exlogdate = date("d-m-Y", $exlogdate);
        // loop through chosen fields
        
        echo "<tr>";
          $ctr = 0; # a counter to know the first column
          // loop through same fields as the <th> cells
          $resultset = $supabase_pdo->prepare("SELECT tab_fields.stid, select_types.single FROM tab_fields, select_types WHERE tab_fields.tbid = ? AND tab_fields.sort_order != ? AND tab_fields.stid = select_types.stid ORDER BY tab_fields.sort_order ASC");
          $resultset->execute([$value1, $value0]);
          $rrs = $resultset->fetchAll(PDO::FETCH_ASSOC);
          foreach ($rrs as $rr){
            $stid = $rr['stid'];
            $single = $rr['single'];
            $exselect_val = '';
            $expid = '';
            // get data for each in turn
            $stmt = $supabase_pdo->prepare("SELECT tlogid, pid, select_val FROM trainee_log WHERE trainkey = ? AND stid = ? AND logkey = ?");
            $stmt->execute([$which,  $stid, $tablelogkey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $extlogid = $row ? $row['tlogid'] : null;
            $expid = $row ? $row['pid'] : null;
            $exselect_val = $row ? $row['select_val'] : '';
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
                echo "<td>$exselect_val</td>\n";
                //echo "<td>$exselect_val ($tbid $stid $tablelogkey)</td>\n";
              //}
              
            }
            if ($single == 0) {
              // fetch the select menu value for $pid
              $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
              $stmt->execute([$expid]);
              $exselect_val = $stmt->fetchColumn();
              echo "<td>$exselect_val</td>\n";
            }
            if ($single == 3) {
              // $exselect_val is date YYYYMMDD
              if ($exselect_val > 1) {
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
              $fieldset = $supabase_pdo->prepare("SELECT pid FROM trainee_log WHERE stid = ? AND logkey = ?");
              $fieldset->execute([$stid, $tablelogkey]);
              $exarr = $fieldset->fetchAll(PDO::FETCH_COLUMN);
              // now make array unique as this will create for each seperate record
              $exarruq = (array_unique($exarr));
              // now loop through array of pids to get values
              $exselect_val = '';
              $mult_val = 0;
              foreach($exarruq as $x) {
                // fetch the select menu value for $pid
                $stmt = $supabase_pdo->prepare("SELECT select_val FROM select_gen WHERE pid = ?");
                $stmt->execute([$x]);
                $mult_val = $stmt->fetchColumn();
                $exselect_val = $exselect_val." ".$mult_val;
              }
              echo "<td>$exselect_val</td>\n";
              $exselect_val = '';
              unset($exarruq);
            }
            $ctr++; # increment counter
            


          }
          $resultset->closeCursor();
          echo "</tr>";
        
          
      }
      // PDO auto-closes
} # checking CSvalue
  echo "</tbody>";
  echo "</table>";
  echo "</div>";
} # loop through selected tbid values
?>
</div>

            <?php include 'incl/sign_off.php' ?>
         </div>
      </section>

   </div>
   <?php include 'incl/adminjs.php' ?>
   
   <script>
   $(document).ready(function() {
      $('.maintable').dataTable( {
        "pageLength": 100
      });
   });
   </script>

</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>