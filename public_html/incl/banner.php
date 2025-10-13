<div class="container-fluid bg_nhsuk-blue" style="position: relative; z-index: 3;">
	<div class="container">
		<div class="row">
			<div class="col py-3">
<?php
// Hide Glossary and Enter e-Log on the login page explicitly
if ($thispage !== 'login.php' && login_check($pdo) != false) {// show account nav
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

    // loop through tabs (supports MySQLi and Supabase PDO)
    $usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
    if ($usingSupabase) {
        try {
            $tabsStmt = $supabase_pdo->query('select tbid, tab_name, sort_order from tabs_tbl where isvis = 1 order by sort_order');
            $tabs = $tabsStmt ? $tabsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($tabs as $tabRow) {
                $navtbid = (int)$tabRow['tbid'];
                $navtab_name = $tabRow['tab_name'];
                // count tab fields
                $countFields = $supabase_pdo->prepare('select count(*) as c from tab_fields where tbid = ?');
                $countFields->execute([$navtbid]);
                $numtabs = (int)($countFields->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                // check permission
                $permStmt = $supabase_pdo->prepare('select ttid from trainee_tab_link where tbid = ? and trainkey = ? limit 1');
                $permStmt->execute([$navtbid, $trainkey]);
                $isok = $permStmt->fetch(PDO::FETCH_ASSOC) ? 1 : 0;
                if ($isok == 1 && $numtabs != 0) {
                    echo "<a class=\"dropdown-item\" href=\"logbook.php?tab=$navtbid\">".htmlentities($navtab_name)."</a>";
                }
            }
        } catch (Throwable $e) {
            error_log('banner.php Supabase tabs load failed: ' . $e->getMessage());
        }
    }
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