<div class="container-fluid bg_nhsuk-blue">
	<div class="container">
		<div class="row">
			<div class="col py-3">
<?php
if (login_check($mysqli) != false) {// show account nav
?>
<div class="dropdown">
	<a href="#" data-toggle="modal" data-target="#glossaryModal" class="btn btn-nhs float-left mx-3">Glossary</a>
  <button class="btn btn-nhs dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-expanded="false">
    Enter e-Log:
  </button>
  <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
    <a class="dropdown-item" href="attendance.php">Placement Attendance</a>
    <div class="dropdown-divider"></div>
    <?php
    $numtabs = 0;
    $isok = 0;
    // goto logbook page with whatever dataset tabs
    // only if permitted for this trainee

    // loop through tabs
    $tableset = $mysqli->prepare("SELECT tbid, tab_name, sort_order FROM tabs_tbl ORDER BY sort_order");
    //$tableset->bind_param("i", $value0);
    $tableset->execute();
    $tableset->store_result();
    $tableset->bind_result($navtbid, $navtab_name, $sort_order);
    while ($tableset->fetch()){
      // we only want tables that have questions
      // not the ones just used for reporting
      $numtabs = 0;
     $vids = $mysqli->prepare("SELECT tbid FROM tab_fields WHERE tbid = ? ");
     $vids->bind_param("i", $navtbid);
     $vids->execute();
     $vids->store_result();
     $numtabs = $vids->num_rows;
     $vids->close();
      // is this trainee permitted to use this tab? 
      $vids = $mysqli->prepare("SELECT ttid FROM trainee_tab_link WHERE tbid = ? AND trainkey = ? ");
      $vids->bind_param("is", $navtbid, $trainkey);
      $vids->execute();
      $vids->store_result();
      $isok = $vids->num_rows;
      $vids->close();
      if ($isok == 1 && $numtabs != 0) {
        echo "<a class=\"dropdown-item\" href=\"logbook.php?tab=$navtbid\">".htmlentities($navtab_name)."</a>";
      }
    }
    $numtabs = $tableset->num_rows;
    $tableset->close();
    ?>
    
  </div>
</div>
<?php
}
?>		
			</div>
		</div>
	</div>
</div>