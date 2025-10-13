<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
include 'incl/admin_vars.php';
$pagetitle = "Trainee Group Stats";
$subtitle = "Stats";
$listurl = "subsets.php";
$listname = "Stats";
$value60 = 60;
// THIS PAGE:
// loops through trainees in a subset group and outputs the stats

// create common array of colours for reports
$dispcolorarr = array();
$dispborderarr = array();

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
$thisyear = date("Y");
// page actions
$group = isset($_GET['group']) ? $_GET['group'] : ''; # setkey
$start = isset($_GET['start']) ? $_GET['start'] : $thisyear;
$end = isset($_GET['end']) ? $_GET['end'] : $thisyear;

$which = isset($_GET['which']) ? $_GET['which'] : '';


//Which group?
$stmt = $pdo->prepare("SELECT subset, description, date_added, date_modified FROM subset_tbl WHERE setkey = ?");
$stmt->execute([$group]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
  $subset = $row['subset'];
  $description = $row['description'];
  $date_added = $row['date_added'];
  $date_modified = $row['date_modified'];
}
$stmt->closeCursor();
$date_added = strtotime($date_added);
$date_modified = strtotime($date_modified);

// set up dates for js array data

$datestart = $start.'0101';
$dateend = $end.'1231';
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
               <div class="content-title"><?php echo $subset ?><small><?php echo $subtitle ?> <a href="<?php echo $listurl ?>">(Back to <?php echo $listname ?>)</a></small> </div>
               <p>Record created on <?php echo date("D jS M Y", $date_added) ?> and last modified on <?php echo date("D jS M Y", $date_modified) ?> </p>
                
            </div>

            <?php
            // decide how to key graphs with dates
            // could be a year or a year range
            if ($start == $end) {
               $dispyear = $start;
            } else {
               $dispyear = "$start to $end";
            }
            ?>
<!-- reports -->

<?php
// main loop through trainees in this group
$groupset = $pdo->prepare("SELECT trainkey FROM subset_link_tbl WHERE setkey = ?");
$groupset->execute([$group]);
while ($row = $groupset->fetch(PDO::FETCH_ASSOC)){
   $trainkey = $row['trainkey'];
   // who is the Trainee?
   $troopstmt = $pdo->prepare("SELECT name, year FROM trainee_tbl WHERE trainkey = ?");
   $troopstmt->execute([$trainkey]);
   $trainee = $troopstmt->fetch(PDO::FETCH_ASSOC);
   $name = $trainee['name'];
   $cohort = $trainee['year'];
   $troopstmt->closeCursor();

?>
            <div class="row mt-5" id="reports">

               <?php
               // these are for stats at end
               $allpass = 0; # flag for how many passed in total
               $allreports = 0; # flag for how many graphs in total
               $namarr = array(); # array of table names
               $pasarr = array(); # array of passes in each table
               $resarr = array(); # array of results in ecah table
               $prevtabname = '';
               // Create divs for graphs from report manager
               // All have IDs that align with equivalent javascript
               // first loop through Tables (that are agreed for thsi Trainee)
               $tableset = $pdo->prepare("SELECT tabs_tbl.tbid, tabs_tbl.tab_name FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
               $tableset->execute([$trainkey]);
               while ($table = $tableset->fetch(PDO::FETCH_ASSOC)){
                  $thistbid = $table['tbid'];
                  $tab_name = $table['tab_name'];
                  array_push($namarr, $tab_name);
                  // put table name as heading if changed
                  /*
                  if ($prevtabname != $tab_name) {
                     echo "<div class=\"col-xl-12 text-center text-white bg-dark mb-2 pt-3\"><h2>$tab_name <a href=\"#passfail\" class=\"btn btn-sm btn-pink ml-3\">Supervisor sign off</a></h2></div>\n";
                  }*/


                  $reportset = $pdo->prepare("SELECT rmid, report_title, valtype, situation, stid FROM report_manager WHERE tbid = ? ORDER BY sort_order");
                  $reportset->execute([$thistbid]);
                  while ($report = $reportset->fetch(PDO::FETCH_ASSOC)){
                     $rmid = $report['rmid'];
                     $report_title = $report['report_title'];
                     $valtype = $report['valtype'];
                     $situation = $report['situation'];
                     $stid = $report['stid'];
                     $allreports++; # count No. of reports

// START data collect

                     $ansarr = array();
                     $valarr = array();
                     $valBarr = array();
                     // look at trainee's data
                     if ($valtype == 0) { # Exact values
                        // loop through this report's data requirements held in valuea
                        // and get the matching field name (valuea = pid)
                        $dataset = $pdo->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->execute([$rmid]); 
                        while ($data = $dataset->fetch(PDO::FETCH_ASSOC)){
                           $select_val = $data['select_val'];
                           $valuea = $data['valuea'];
                           array_push($valarr,$valuea); # the id's to look for in Trainee's data
                        }
                        $dataset->closeCursor();
                        // find how many of each type for this trainee and push value to array
                        foreach ($valarr as $valueA) {
                           $vids = $pdo->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ? AND date_added >= ? AND date_added <= ?"); 
                           $vids->execute([$trainkey, $stid, $valueA, $datestart, $dateend]);
                           $numages = $vids->rowCount();
                           $vids->closeCursor();
                           array_push($ansarr, $numages);
                        }
                        
                     }
                     if ($valtype == 1) { #range of values
                        $dataset = $pdo->prepare("SELECT valuea, valueb FROM report_data WHERE rmid = ? ORDER BY rdid");
                        $dataset->execute([$rmid]); 
                        while ($data = $dataset->fetch(PDO::FETCH_ASSOC)){
                           $valuea = $data['valuea'];
                           $valueb = $data['valueb'];
                           $select_val = "$valuea - $valueb";
                           array_push($valarr,$valuea); # the 'from' value to search data
                           array_push($valBarr,$valueb); # the 'to' value to search data
                        }
                        $dataset->closeCursor();
                        // find how many of each type for this trainee and push value to array
                        $x = 0;
                        foreach ($valarr as $valueA) {
                           // find same key for vlueB
                           $valueB = $valBarr[$x];

                           $vids = $pdo->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND select_val >= ? AND select_val <= ? AND date_added >= ? AND date_added <= ?"); 
                           $vids->execute([$trainkey, $stid, $valueA, $valueB, $datestart, $dateend]);
                           $numages = $vids->rowCount();
                           $vids->closeCursor();
                           array_push($ansarr, $numages);
                           $x++;
                        }
                     }
                     if ($valtype == 2) { # count of hours
                        // find select_gen.pid from report_data.valuea
                        // this gives select_types.stid (and select_types.select_val is the x-axis label)
                        // 
                        // loop through this report's data requirements held in valuea
                        // and get the matching field name (valuea = pid)
                        $dataset = $pdo->prepare("SELECT select_gen.select_val, report_data.valuea, report_data.valueb FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->execute([$rmid]); 
                        while ($data = $dataset->fetch(PDO::FETCH_ASSOC)){
                           $select_val = $data['select_val'];
                           $valuea = $data['valuea'];
                           $valueb = $data['valueb'];
                           $valuea = intval($valuea);
                           array_push($valarr,$valuea); # the stid's to look for in Trainee's data
                        }
                        $dataset->closeCursor();

                        // loop through each stid in trainee's data matching array pid
                        $tothrs = 0;
                        foreach ($valarr as $valueA) {
                           $hrsset = $pdo->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ? AND date_added >= ? AND date_added <= ?");
                           $hrsset->execute([$trainkey, $stid, $valueA, $datestart, $dateend]);
                           while ($log = $hrsset->fetch(PDO::FETCH_ASSOC)){
                              $logkey = $log['logkey'];
                              $hours = '';
                              // we'll use the logkey to find the hours for the same session
                              //echo "logkey $logkey ($trainkey, $stid, $valueA, $datestart, $dateend)<br>";
                              // for each, find number of hours for the same logkey
                              // // looking for stid = 60
                              $stmt = $pdo->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
                              $stmt->execute([$logkey, $value60]);
                              $hours = $stmt->fetchColumn();
                              $stmt->closeCursor();
                              //echo "| $hours | ";
                              // data is in HH:mm format
                              if ($hours > 0) {
                                 $time = explode(':', $hours);
                                 $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
                                 $hours = $minutes / 60;
                                 $tothrs = $tothrs + $hours;
                              }
                              
                           }
                           $numrows = $hrsset->rowCount();
                           $hrsset->closeCursor();
                           //echo "valueA $valueA - numrows $numrows";

                           array_push($ansarr, $hours);
                        }
                     }

                     $howmanyvals = count($valarr); # how many values expected
                     
// END data collect



// START pass/fail
                  // $passtext shows pass/fail/no pass requirement
                     
                     // $situation shows requirements for 'pass'
                     include 'incl/situations.php';
// END pass/fail

                     
                     // we'll  use these arrays later in the js so empty
                     unset($ansarr);
                     unset($valarr);
                     unset($valBarr);
                     
                  }
                  $reportset->closeCursor();
                  $prevtabname = $tab_name;
                  
                  array_push($resarr, $allreports); # how many for this table
                  array_push($pasarr, $allpass); # how many for this table
                  $allpass = 0;
                  $allreports = 0;
               }
               $tableset->closeCursor();
               ?>
               
            </div>
            <div class="row">
               <div class="col">
                  <h2 class="bg-primary text-white p-2">Statistics for <?php echo $name ?> (Cohort <?php echo $cohort ?>)</h2>
                  <?php
                  // $namarr as $tname - the Table Names
                  // $resarr - 

                  //$pcp = ($allpass/$allreports)*100;
                  //echo "<p>Total of reports: $allreports. Total passed $allpass. Percentage passed ".ceil($pcp)."%</p>";
                  echo "<table class=\"table table-sm table-striped\">";
                  echo "<thead>";
                  echo "<tr class=\"table-primary\"><th>Table</th><th>No. Questions</th><th>No. Passed</th><th>%age Pass</th></tr>";
                  echo "</thead>";
                  echo "<tbody>";
                  $qq = 0;
                  foreach ($namarr as $tname) {
                     echo "<tr>";
                     echo "<td>$tname</td>";
                     echo "<td>$resarr[$qq]</td>";
                     echo "<td>$pasarr[$qq]</td>";
                     if ($resarr[$qq] != 0) {
                        $pcp = ($pasarr[$qq]/$resarr[$qq])*100;
                     } else {
                        $pcp = 0;
                     }
                     $celltxt = "<span class=\"badge badge-danger\">".ceil($pcp)."</span>";
                     if ($pcp > 50) {
                        $celltxt = "<span class=\"badge badge-warning\">".ceil($pcp)."</span>";
                     }
                     if ($pcp > 75) {
                        $celltxt = "<span class=\"badge badge-info\">".ceil($pcp)."</span>";
                     }
                     if ($pcp > 99.9) {
                        $celltxt = "<span class=\"badge badge-success\">".ceil($pcp)."</span>";
                     }
                     echo "<td>$celltxt</td>";
                     echo "</tr>";
                     $qq++;
                  }
                  echo "</tbody>";
                  echo "</table>";

                  ?>
</div>
<?php
// main loop through trainees in this group
}
$groupset->closeCursor();
?>



         </div>
      </section>

   </div>
   <?php include 'incl/adminjs.php' ?>
   <script>
   $(document).ready(function() {
      $('#maintable').dataTable( {
        "pageLength": 10
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