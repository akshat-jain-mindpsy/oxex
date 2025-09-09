<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';
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

if(login_check($mysqli) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
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
$stmt = $mysqli->prepare("SELECT subset, description, date_added, date_modified FROM subset_tbl WHERE setkey = ?");
$stmt->bind_param("s", $group);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($subset, $description, $date_added, $date_modified);
$stmt->fetch();
$stmt->close();
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
$groupset = $mysqli->prepare("SELECT trainkey FROM subset_link_tbl WHERE setkey = ?");
$groupset->bind_param("s", $group);
$groupset->execute();
$groupset->store_result();
$groupset->bind_result($trainkey);
while ($groupset->fetch()){
   // who is the Trainee?
   $troopstmt = $mysqli->prepare("SELECT name, year FROM trainee_tbl WHERE trainkey = ?");
   $troopstmt->bind_param("s", $trainkey);
   $troopstmt->execute();
   $troopstmt->store_result();
   $troopstmt->bind_result($name, $cohort);
   $troopstmt->fetch();
   $troopstmt->close();

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
               $tableset = $mysqli->prepare("SELECT tabs_tbl.tbid, tabs_tbl.tab_name FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
               $tableset->bind_param("s", $trainkey);
               $tableset->execute();
               $tableset->store_result();
               $tableset->bind_result($thistbid, $tab_name);
               while ($tableset->fetch()){
                  array_push($namarr, $tab_name);
                  // put table name as heading if changed
                  /*
                  if ($prevtabname != $tab_name) {
                     echo "<div class=\"col-xl-12 text-center text-white bg-dark mb-2 pt-3\"><h2>$tab_name <a href=\"#passfail\" class=\"btn btn-sm btn-pink ml-3\">Supervisor sign off</a></h2></div>\n";
                  }*/


                  $reportset = $mysqli->prepare("SELECT rmid, report_title, valtype, situation, stid FROM report_manager WHERE tbid = ? ORDER BY sort_order");
                  $reportset->bind_param("i", $thistbid);
                  $reportset->execute();
                  $reportset->store_result();
                  $reportset->bind_result($rmid, $report_title, $valtype, $situation, $stid);
                  while ($reportset->fetch()){
                     $allreports++; # count No. of reports

// START data collect

                     $ansarr = array();
                     $valarr = array();
                     $valBarr = array();
                     // look at trainee's data
                     if ($valtype == 0) { # Exact values
                        // loop through this report's data requirements held in valuea
                        // and get the matching field name (valuea = pid)
                        $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($select_val, $valuea);
                        while ($dataset->fetch()){
                           array_push($valarr,$valuea); # the id's to look for in Trainee's data
                        }
                        $dataset->close();
                        // find how many of each type for this trainee and push value to array
                        foreach ($valarr as $valueA) {
                           $vids = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ? AND date_added >= ? AND date_added <= ?"); 
                           $vids->bind_param("siiii", $trainkey, $stid, $valueA, $datestart, $dateend);
                           $vids->execute();
                           $vids->store_result();
                           $numages = $vids->num_rows;
                           $vids->close();
                           array_push($ansarr, $numages);
                        }
                        
                     }
                     if ($valtype == 1) { #range of values
                        $dataset = $mysqli->prepare("SELECT valuea, valueb FROM report_data WHERE rmid = ? ORDER BY rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($valuea, $valueb);
                        while ($dataset->fetch()){
                           $select_val = "$valuea - $valueb";
                           array_push($valarr,$valuea); # the 'from' value to search data
                           array_push($valBarr,$valueb); # the 'to' value to search data
                        }
                        $dataset->close();
                        // find how many of each type for this trainee and push value to array
                        $x = 0;
                        foreach ($valarr as $valueA) {
                           // find same key for vlueB
                           $valueB = $valBarr[$x];

                           $vids = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE trainkey = ? AND stid = ? AND select_val >= ? AND select_val <= ? AND date_added >= ? AND date_added <= ?"); 
                           $vids->bind_param("sisiii", $trainkey, $stid, $valueA, $valueB, $datestart, $dateend);
                           $vids->execute();
                           $vids->store_result();
                           $numages = $vids->num_rows;
                           $vids->close();
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
                        $dataset = $mysqli->prepare("SELECT select_gen.select_val, report_data.valuea, report_data.valueb FROM report_data, select_gen WHERE report_data.rmid = ? AND select_gen.pid = report_data.valuea ORDER BY report_data.rdid");
                        $dataset->bind_param("i", $rmid); 
                        $dataset->execute();
                        $dataset->store_result();
                        $dataset->bind_result($select_val, $valuea, $valueb);
                        while ($dataset->fetch()){
                           $valuea = intval($valuea);
                           array_push($valarr,$valuea); # the stid's to look for in Trainee's data
                        }
                        $dataset->close();

                        // loop through each stid in trainee's data matching array pid
                        $tothrs = 0;
                        foreach ($valarr as $valueA) {
                           $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ? AND date_added >= ? AND date_added <= ?");
                           $hrsset->bind_param("siiii", $trainkey, $stid, $valueA, $datestart, $dateend);
                           $hrsset->execute();
                           $hrsset->store_result();
                           $hrsset->bind_result($logkey);
                           while ($hrsset->fetch()){
                              $hours = '';
                              // we'll use the logkey to find the hours for the same session
                              //echo "logkey $logkey ($trainkey, $stid, $valueA, $datestart, $dateend)<br>";
                              // for each, find number of hours for the same logkey
                              // // looking for stid = 60
                              $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
                              $stmt->bind_param("si", $logkey, $value60);
                              $stmt->execute();
                              $stmt->store_result();
                              $stmt->bind_result($hours);
                              $stmt->fetch();
                              $stmt->close();
                              //echo "| $hours | ";
                              // data is in HH:mm format
                              if ($hours > 0) {
                                 $time = explode(':', $hours);
                                 $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
                                 $hours = $minutes / 60;
                                 $tothrs = $tothrs + $hours;
                              }
                              
                           }
                           $numrows = $hrsset->num_rows;
                           $hrsset->close();
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
                  $reportset->close();
                  $prevtabname = $tab_name;
                  
                  array_push($resarr, $allreports); # how many for this table
                  array_push($pasarr, $allpass); # how many for this table
                  $allpass = 0;
                  $allreports = 0;
               }
               $tableset->close();
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
            </div>
<?php
// main loop through trainees in this group
}
$groupset->close();
?>



         </div>
      </section>

   </div>
   <?php include 'incl/adminjslite.php' ?>
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