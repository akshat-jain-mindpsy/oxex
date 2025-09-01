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
               <div class="row" id="composite">
                  <div class="col">
<?php
// run through composite searches
$tableset = $mysqli->prepare("SELECT csname, stid1, pid1, stid2, pid2, stid3, pid3, sort_order, target FROM compositesearch WHERE sort_order != ? ORDER BY sort_order");
$tableset->bind_param("i", $value0);
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($csname, $stid1, $pid1, $stid2, $pid2, $stid3, $pid3, $sort_order, $target);
while ($tableset->fetch()){
   $target = $target * 60; # convert target to minutes
   echo "<h3 class=\"bg-primary text-white mt-2 p-2\">Composite Search: <small>$csname</small></h3>";
   echo "<table class=\"table table-striped\">";
   echo "<thead>";
   echo "<tr><th>Trainee</th><th>Sessions</th><th>Hours</th></tr>";
   echo "</thead>";
   echo "<tbody>";
   // for each trainee in group
   $groupset = $mysqli->prepare("SELECT trainkey FROM subset_link_tbl WHERE setkey = ?");
   $groupset->bind_param("s", $group);
   $groupset->execute();
   $groupset->store_result();
   $groupset->bind_result($trainkey);
   while ($groupset->fetch()){
      $troopstmt = $mysqli->prepare("SELECT name, year FROM trainee_tbl WHERE trainkey = ?");
      $troopstmt->bind_param("s", $trainkey);
      $troopstmt->execute();
      $troopstmt->store_result();
      $troopstmt->bind_result($name, $cohort);
      $troopstmt->fetch();
      $troopstmt->close();
      $sessctr = 0;
      $sesshrs = 0;
      //$temparr = array();
      // loop through all sessions in logbook for each trainee
      $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND pid = ?");
      $hrsset->bind_param("sii", $trainkey, $stid1, $pid1);
      $hrsset->execute();
      $hrsset->store_result();
      $hrsset->bind_result($logkey);
      while ($hrsset->fetch()){

         // now find time for this same logkey session ($stid = 60)
         $hours = "00:00";
         $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
         $stmt->bind_param("si", $logkey, $value60);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($hours);
         $stmt->fetch();
         $stmt->close();
         if (!empty($hours)) {
            // Note, $hours<0 omits anything less than one hour
            //array_push($temparr,$hours);
            $time = explode(':', $hours); # stored as HH:mm
            if (isset($time[1])) {
               $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
            }

         } else {
            $minutes = 0;
         }
         
            
         //$hours = $minutes / 60;
         // check if additional criteria in search which nulls this value if no compliance
         $comply = 1;

         if ($stid2 != 0) {
            $stmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? AND stid = ? AND pid = ?");
            $stmt->bind_param("sii", $logkey, $stid2, $pid2);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($tlogid2);
            $stmt->fetch();
            $stmt->close();
            if ($tlogid2 == 0) {
               // if there's no matching data, trainee does not comply
               $comply = 0;
            }
         }
         if ($stid3 != 0) {
            $stmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? AND stid = ? AND pid = ?");
            $stmt->bind_param("sii", $logkey, $stid3, $pid3);
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($tlogid3);
            $stmt->fetch();
            $stmt->close();
            if ($tlogid3 == 0) {
               // if there's no matching data, trainee does not comply
               $comply = 0;
            }
         }
         if ($comply == 1) {
            // either no extra constraints,
            // or trainee data complies with constraints
            // so record data
            $sessctr ++;
            $sesshrs = $sesshrs + $minutes;
         }
         

      }
      $hrsset->close();
      echo "<tr>";
      echo "<td>$name</td>";
      echo "<td>";
      // display No of sessions
      echo $sessctr;
      echo "</td>";
      
      echo "<td>";
      // display total hours
      // convert back to HH:mm for hrsbadge
      // badge related to $target ($target is in minutes):
         // default if target == 0
         // red if =< 50% of target
         // yellow if > 50% of target
         // green if >= target
      $hr = floor($sesshrs / 60);
      $min = floor($sesshrs % 60);
      
      $hrsbadge = "<span class=\"badge badge-danger\">".$hr.":".$min."</span>"; # default
      if ($sesshrs >= ($target / 2)) {
         $hrsbadge = "<span class=\"badge badge-warning\">".$hr.":".$min."</span>";
      }
      if ($sesshrs >= $target) {
         $hrsbadge = "<span class=\"badge badge-success\">".$hr.":".$min."</span>";
      }
      if ($target == 0) {
         $hrsbadge = "<span class=\"badge badge-secondary\">".$hr.":".$min."</span>";
      }
      
      //$hrsbadge = $sesshrs;
      echo $hrsbadge;
      //print_r($temparr);
      echo "</td>";
      echo "</tr>";
   }
   $groupset->close();
   echo "</tbody>";
   echo "</table>";
}
$tableset->close();
?>
<?php
// now do two manual OR searches - OR search 1:
// Trainee role / Lead AND
// Primary modality/(CBT or PBS or ACT or CFT or Mindfulness)
$csname = 'Hours and minutes of CBT practice (Lead)';
$stid1 = 18; # Primary modality
$pid1 = 64; # CBT
$pid2 = 67; # PBS
$pid3 = 71; # ACT
$pid4 = 70; # CFT
$pid5 = 79; # Mindfulness
$target = 200; # 200 hours

$rolestid = 13; # Field 'Trainee Role'
$rolepid = 31; # Value 'Lead'

$target = $target * 60; # convert target to minutes
echo "<h3 class=\"bg-primary text-white mt-2 p-2\">Composite Search: <small>$csname</small></h3>";
echo "<table class=\"table table-striped\">";
echo "<thead>";
echo "<tr><th>Trainee</th><th>Sessions</th><th>Hours</th></tr>";
echo "</thead>";
echo "<tbody>";
// for each trainee in group
   $groupset = $mysqli->prepare("SELECT trainkey FROM subset_link_tbl WHERE setkey = ?");
   $groupset->bind_param("s", $group);
   $groupset->execute();
   $groupset->store_result();
   $groupset->bind_result($trainkey);
   while ($groupset->fetch()){
      $troopstmt = $mysqli->prepare("SELECT name, year FROM trainee_tbl WHERE trainkey = ?");
      $troopstmt->bind_param("s", $trainkey);
      $troopstmt->execute();
      $troopstmt->store_result();
      $troopstmt->bind_result($name, $cohort);
      $troopstmt->fetch();
      $troopstmt->close();
      $sessctr = 0;
      $sesshrs = 0;
      // loop through all sessions in logbook for each trainee
      $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND (pid = ? OR pid = ? OR pid = ? OR pid = ? OR pid = ?)");
      $hrsset->bind_param("siiiiii", $trainkey, $stid1, $pid1, $pid2, $pid3, $pid4, $pid5);
      $hrsset->execute();
      $hrsset->store_result();
      $hrsset->bind_result($logkey);
      while ($hrsset->fetch()){
         // now find time for this same logkey session ($stid = 60)
         $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
         $stmt->bind_param("si", $logkey, $value60);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($hours);
         $stmt->fetch();
         $stmt->close();
         if (!empty($hours)) {
            // Note, $hours<0 omits anything less than one hour
            //array_push($temparr,$hours);
            $time = explode(':', $hours); # stored as HH:mm
            if (isset($time[1])) {
               $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
            }

         } else {
            $minutes = 0;
         }
         // we only want 'Lead' times so check if this logkey includes this
         $tlogid = 0;
         $stmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? AND stid = ? AND pid = ?");
         $stmt->bind_param("sii", $logkey, $rolestid, $rolepid);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($tlogid);
         $stmt->fetch();
         $stmt->close();
         if ($tlogid > 0) { #so, trainee is a 'Lead'
            $sessctr ++;
            $sesshrs = $sesshrs + $minutes;
         }
         

      }
      $hrsset->close();
      echo "<tr>";
      echo "<td>$name</td>";
      echo "<td>";
      // display No of sessions
      echo $sessctr;
      echo "</td>";
      
      echo "<td>";
      // display total hours
      // convert back to HH:mm for hrsbadge
      // badge related to $target ($target is in minutes):
         // default if target == 0
         // red if =< 50% of target
         // yellow if > 50% of target
         // green if >= target
      $hr = floor($sesshrs / 60);
      $min = floor($sesshrs % 60);
      $hrsbadge = "<span class=\"badge badge-danger\">".$hr.":".$min."</span>"; # default
      if ($sesshrs >= ($target / 2)) {
         $hrsbadge = "<span class=\"badge badge-warning\">".$hr.":".$min."</span>";
      }
      if ($sesshrs >= $target) {
         $hrsbadge = "<span class=\"badge badge-success\">".$hr.":".$min."</span>";
      }
      if ($target == 0) {
         $hrsbadge = "<span class=\"badge badge-secondary\">".$hr.":".$min."</span>";
      }
      echo $hrsbadge;
      echo "</td>";
      echo "</tr>";
   }
   $groupset->close();
echo "</tbody>";
echo "</table>";

// OR Search 2 Model (Systemic)/Structural or Strategic or MBFT or Milan or Post Milan or Social Constructivist or Narrative schools
$csname = 'Hours and minutes of Systemic practice (Lead)';
$stid1 = 30; #  Model (Systemic)
$pid1 = 242; # Structural
$pid2 = 243; # Strategic
$pid3 = 244; # MBFT
$pid4 = 245; # Milan
$pid5 = 246; # Post Milan
$pid6 = 247; # Social Constructivist
$pid7 = 248; # Narrative schools
$target = 60; # 60 hours

$rolestid = 13; # Field 'Trainee Role'
$rolepid = 31; # Value 'Lead'

$target = $target * 60; # convert target to minutes
echo "<h3 class=\"bg-primary text-white mt-2 p-2\">Composite Search: <small>$csname</small></h3>";
echo "<table class=\"table table-striped\">";
echo "<thead>";
echo "<tr><th>Trainee</th><th>Sessions</th><th>Hours</th></tr>";
echo "</thead>";
echo "<tbody>";
// for each trainee in group
   $groupset = $mysqli->prepare("SELECT trainkey FROM subset_link_tbl WHERE setkey = ?");
   $groupset->bind_param("s", $group);
   $groupset->execute();
   $groupset->store_result();
   $groupset->bind_result($trainkey);
   while ($groupset->fetch()){
      $troopstmt = $mysqli->prepare("SELECT name, year FROM trainee_tbl WHERE trainkey = ?");
      $troopstmt->bind_param("s", $trainkey);
      $troopstmt->execute();
      $troopstmt->store_result();
      $troopstmt->bind_result($name, $cohort);
      $troopstmt->fetch();
      $troopstmt->close();
      $sessctr = 0;
      $sesshrs = 0;
      // loop through all sessions in logbook for each trainee
      $hrsset = $mysqli->prepare("SELECT logkey FROM trainee_log WHERE trainkey = ? AND stid = ? AND (pid = ? OR pid = ? OR pid = ? OR pid = ? OR pid = ? OR pid = ? OR pid = ?)");
      $hrsset->bind_param("siiiiiiii", $trainkey, $stid1, $pid1, $pid2, $pid3, $pid4, $pid5, $pid6, $pid7);
      $hrsset->execute();
      $hrsset->store_result();
      $hrsset->bind_result($logkey);
      while ($hrsset->fetch()){
         // now find time for this same logkey session ($stid = 60)
         $stmt = $mysqli->prepare("SELECT select_val FROM trainee_log WHERE logkey = ? AND stid = ?");
         $stmt->bind_param("si", $logkey, $value60);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($hours);
         $stmt->fetch();
         $stmt->close();
         if (!empty($hours)) {
            // Note, $hours<0 omits anything less than one hour
            //array_push($temparr,$hours);
            $time = explode(':', $hours); # stored as HH:mm
            if (isset($time[1])) {
               $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
            }

         } else {
            $minutes = 0;
         }
         // we only want 'Lead' times so check if this logkey includes this
         $tlogid = 0;
         $stmt = $mysqli->prepare("SELECT tlogid FROM trainee_log WHERE logkey = ? AND stid = ? AND pid = ?");
         $stmt->bind_param("sii", $logkey, $rolestid, $rolepid);
         $stmt->execute();
         $stmt->store_result();
         $stmt->bind_result($tlogid);
         $stmt->fetch();
         $stmt->close();
         if ($tlogid > 0) { #so, trainee is a 'Lead'
            $sessctr ++;
            $sesshrs = $sesshrs + $minutes;
         }

      }
      $hrsset->close();
      echo "<tr>";
      echo "<td>$name</td>";
      echo "<td>";
      // display No of sessions
      echo $sessctr;
      echo "</td>";
      
      echo "<td>";
      // display total hours
      // convert back to HH:mm for hrsbadge
      // badge related to $target ($target is in minutes):
         // default if target == 0
         // red if =< 50% of target
         // yellow if > 50% of target
         // green if >= target
      $hr = floor($sesshrs / 60);
      $min = floor($sesshrs % 60);
      $hrsbadge = "<span class=\"badge badge-danger\">".$hr.":".$min."</span>"; # default
      if ($sesshrs >= ($target / 2)) {
         $hrsbadge = "<span class=\"badge badge-warning\">".$hr.":".$min."</span>";
      }
      if ($sesshrs >= $target) {
         $hrsbadge = "<span class=\"badge badge-success\">".$hr.":".$min."</span>";
      }
      if ($target == 0) {
         $hrsbadge = "<span class=\"badge badge-secondary\">".$hr.":".$min."</span>";
      }
      echo $hrsbadge;
      echo "</td>";
      echo "</tr>";
   }
   $groupset->close();
echo "</tbody>";
echo "</table>";

?>

                  </div>
               </div>




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