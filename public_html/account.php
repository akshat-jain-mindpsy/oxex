<?php 
error_log("CHECKPOINT 1: Account.php starting");
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
check_session_timeout();

include 'incl/sess.php';
$usingSupabase = (isset($supabase_pdo) && $supabase_pdo instanceof PDO);
$valueyearstart = date('Y').'0101'; # YYYYMMDD format
$valueyearend = date('Y').'1231';
// set up dates for stats panel js array data
$thisyear = date("Y");
$start = isset($_GET['start']) ? $_GET['start'] : $thisyear;
$end = isset($_GET['end']) ? $_GET['end'] : $thisyear;
$datestart = $start.'0101';
$dateend = $end.'1231';
$datestart = 20200101;
$dateend = date('Y') . '1231';
$valueyearstart = 20200101;
$valueyearend = 20991231;

error_log("About to start HTML output");
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>
  </head>
  <?php
    if (login_check($pdo) != false) {
      // logged in only!
      error_log("CHECKPOINT 2: Login check passed, starting body");
    ?>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <div class="container-fluid py-5" id="main">
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-sm-8 offset-sm-2 text-center">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
              //echo "<p>trainkey $trainkey</p>";
            }
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
              echo $page_txt2;
            ?>
            <?php
              echo $page_txt3;
            ?>
            <?php echo $page_txt4; ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            
            <?php
            // Using Supabase (PDO) exclusively

            // list the coloured task labels
            if ($usingSupabase) {
                try {
                    $stmtTasks = $supabase_pdo->query('select dtid, task, colour, textcolor from tasks');
                    while ($rowTask = $stmtTasks->fetch(PDO::FETCH_ASSOC)) {
                        $dtid = (int)$rowTask['dtid'];
                        $task = $rowTask['task'];
                        $colour = $rowTask['colour'];
                        $textcolor = (int)$rowTask['textcolor'];
                        // count tasks for trainee within year
                        $stmtCount = $supabase_pdo->prepare('select count(*) as c from timesheet where trainkey = ? and dtid = ? and taskdate >= ? and taskdate <= ?');
                        $stmtCount->execute([$trainkey, $dtid, $valueyearstart, $valueyearend]);
                        $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC);
                        $numtasks = (int)($countRow['c'] ?? 0);
                        if ($textcolor == 1) {
                            $task = "<span class=\"text-white\">$task</span>";
                        } else {
                            $task = "<span class=\"text-dark\">$task</span>";
                        }
                        echo "<div id=\"d$dtid\" class=\"rounded px-3 py-2 mr-1 mb-2\" style=\"background-color:#$colour\">$task <span class=\"badge badge-dark float-right mr-2\" id=\"taskqty$dtid\">$numtasks</span></div>";
                    }
                } catch (Throwable $e) {
                    error_log('Supabase tasks section failed: ' . $e->getMessage());
                }
            }
            error_log("CHECKPOINT 3: Task labels section completed");
            ?>
          </div>
        </div>
        <div class="row mt-5">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
            error_log("CHECKPOINT 4: Starting stats section");
            // Stats - as admin traineedetail.php but no output until the stats
            // could be a year or a year range
            if ($start == $end) {
               $dispyear = $start;
            } else {
               $dispyear = "$start to $end";
            }
             // these are for stats at end
             $allpass = 0; # flag for how many passed in total
             $allreports = 0; # flag for how many graphs in total
             $namarr = array(); # array of table names
             $pasarr = array(); # array of passes in each table
             $resarr = array(); # array of results in each table
             $prevtabname = '';
             // Create divs for graphs from report manager
             // All have IDs that align with equivalent javascript
             // first loop through Tables (that are agreed for thsi Trainee)
            if ($usingSupabase) {
                $tables = [];
                $stmtTabs = $supabase_pdo->prepare('select t.tbid, t.tab_name from tabs_tbl t join trainee_tab_link l on t.tbid = l.tbid where l.trainkey = ? and t.isvis = 1 order by t.sort_order');
                $stmtTabs->execute([$trainkey]);
                while ($r = $stmtTabs->fetch(PDO::FETCH_ASSOC)) { $tables[] = $r; }
                error_log("CHECKPOINT 5: Starting tables loop, found " . count($tables) . " tables");
            } else {
                $tableset = $pdo->prepare("SELECT tabs_tbl.tbid, tabs_tbl.tab_name FROM tabs_tbl, trainee_tab_link WHERE trainee_tab_link.trainkey = ? AND tabs_tbl.tbid = trainee_tab_link.tbid AND tabs_tbl.isvis = 1 ORDER BY tabs_tbl.sort_order");
                $tableset->execute([$trainkey]);
                $tables = [];
                while ($r = $tableset->fetch(PDO::FETCH_ASSOC)) { $tables[] = $r; }
                error_log("CHECKPOINT 5: Starting tables loop, found " . count($tables) . " tables");
            }
             
             // OPTIMIZATION: Pre-collect all trainee_log data for this trainee and date range
             // This replaces hundreds of individual queries with a single efficient query
            $allTraineeData = array();
            if ($usingSupabase) {
                $q = $supabase_pdo->prepare('select tlogid, logkey, stid, pid, select_val, date_added from trainee_log where trainkey = ? and date_added >= ? and date_added <= ? order by stid, pid');
                $q->execute([$trainkey, $datestart, $dateend]);
                while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
                    $allTraineeData[] = array(
                        'tlogid' => $r['tlogid'],
                        'logkey' => $r['logkey'],
                        'stid' => (int)$r['stid'],
                        'pid' => (int)$r['pid'],
                        'select_val' => $r['select_val'],
                        'date_added' => $r['date_added'],
                    );
                }
            } else {
                $traineeDataQuery = $pdo->prepare("
                   SELECT tlogid, logkey, stid, pid, select_val, date_added 
                   FROM trainee_log 
                   WHERE trainkey = ? AND date_added >= ? AND date_added <= ?
                   ORDER BY stid, pid
                ");
                $traineeDataQuery->execute([$trainkey, $datestart, $dateend]);
                while ($r = $traineeDataQuery->fetch(PDO::FETCH_ASSOC)) {
                   $allTraineeData[] = array(
                      'tlogid' => $r['tlogid'],
                      'logkey' => $r['logkey'],
                      'stid' => $r['stid'],
                      'pid' => $r['pid'],
                      'select_val' => $r['select_val'],
                      'date_added' => $r['date_added']
                   );
                }
                $traineeDataQuery->closeCursor();
            }
             
             // Create lookup arrays for fast access
             $traineeDataByStidPid = array();
             $traineeDataByLogkey = array();
             
             foreach ($allTraineeData as $data) {
                $key = $data['stid'] . '_' . $data['pid'];
                if (!isset($traineeDataByStidPid[$key])) {
                   $traineeDataByStidPid[$key] = array();
                }
                $traineeDataByStidPid[$key][] = $data;
                
                if (!isset($traineeDataByLogkey[$data['logkey']])) {
                   $traineeDataByLogkey[$data['logkey']] = array();
                }
                $traineeDataByLogkey[$data['logkey']][] = $data;
             }
             
            if ($usingSupabase) {
            foreach ($tables as $t) { $thistbid = (int)$t['tbid']; $tab_name = $t['tab_name'];
            
                array_push($namarr, $tab_name);
                $reportRows = [];
                $reportset = $supabase_pdo->prepare('select rmid, report_title, valtype, situation, stid from report_manager where tbid = ? order by sort_order');
                $reportset->execute([$thistbid]);
                while ($rr = $reportset->fetch(PDO::FETCH_ASSOC)) { $reportRows[] = $rr; }
                foreach ($reportRows as $rr) {
                    $rmid = (int)$rr['rmid'];
                    $report_title = $rr['report_title'];
                    $valtype = (int)$rr['valtype'];
                    $situation = $rr['situation'];
                    $stid = (int)$rr['stid'];
                    $allreports++;
                    if ($allreports % 10 == 0) {
                        error_log("CHECKPOINT 6: Processed " . $allreports . " reports total, currently on table " . count($namarr) . " (tbid: " . $thistbid . ")");
                    }
                    $ansarr = array();
                    $valarr = array();
                    $valBarr = array();
                    if ($valtype == 0) {
                        $dataset = $supabase_pdo->prepare('select s.select_val, d.valuea from report_data d join select_gen s on s.pid = d.valuea where d.rmid = ? order by d.rdid');
                        $dataset->execute([$rmid]);
                        while ($r = $dataset->fetch(PDO::FETCH_ASSOC)) { $valarr[] = $r['valuea']; }
                        foreach ($valarr as $valueA) {
                            $key = $stid . '_' . $valueA;
                            $count = isset($traineeDataByStidPid[$key]) ? count($traineeDataByStidPid[$key]) : 0;
                            array_push($ansarr, $count);
                        }
                    }
                    if ($valtype == 1) {
                        $dataset = $supabase_pdo->prepare('select valuea, valueb from report_data where rmid = ? order by rdid');
                        $dataset->execute([$rmid]);
                        while ($r = $dataset->fetch(PDO::FETCH_ASSOC)) { $valarr[] = (int)$r['valuea']; $valBarr[] = (int)$r['valueb']; }
                        $x = 0;
                        foreach ($valarr as $valueA) {
                            $valueB = $valBarr[$x];
                            $count = 0;
                            foreach ($allTraineeData as $data) {
                                if ($data['stid'] == $stid && $data['select_val'] >= $valueA && $data['select_val'] <= $valueB) {
                                    $count++;
                                }
                            }
                            array_push($ansarr, $count);
                            $x++;
                        }
                    }
                    if ($valtype == 2) {
                        $dataset = $supabase_pdo->prepare('select s.select_val, d.valuea, d.valueb from report_data d join select_gen s on s.pid = d.valuea where d.rmid = ? order by d.rdid');
                        $dataset->execute([$rmid]);
                        while ($r = $dataset->fetch(PDO::FETCH_ASSOC)) { $valarr[] = (int)$r['valuea']; }
                        $tothrs = 0;
                        $value60 = 60;
                        foreach ($valarr as $valueA) {
                            $hours = 0;
                            $key = $stid . '_' . $valueA;
                            if (isset($traineeDataByStidPid[$key])) {
                                foreach ($traineeDataByStidPid[$key] as $data) {
                                    $logkey = $data['logkey'];
                                    if (isset($traineeDataByLogkey[$logkey])) {
                                        foreach ($traineeDataByLogkey[$logkey] as $logData) {
                                            if ($logData['stid'] == $value60) { $hours = $logData['select_val']; break; }
                                        }
                                    }
                                    if ($hours > 0) {
                                        $time = explode(':', $hours);
                                        $minutes = (intval($time[0]) * 60.0 + intval($time[1]) * 1.0);
                                        $hours = $minutes / 60;
                                        $tothrs = $tothrs + $hours;
                                    }
                                }
                            }
                            array_push($ansarr, $tothrs);
                        }
                    }
                    include 'oxex-admin/incl/situations.php';
                }
                $prevtabname = $tab_name;
                array_push($resarr, $allreports);
                array_push($pasarr, $allpass);
                $allpass = 0;
                $allreports = 0;
            }
            }
             ?>
             <h2 class="bg_nhsuk-blue text-white p-2">Statistics (<?php echo $dispyear ?>)</h2>
                  <?php
                  //$pcp = ($allpass/$allreports)*100;
                  //echo "<p>Total of reports: $allreports. Total passed $allpass. Percentage passed ".ceil($pcp)."%</p>";
                  echo "<table class=\"table table-sm table-striped\">";
                  echo "<thead>";
                  echo "<tr class=\"table-primary\"><th>Sheet</th><th>No. Questions</th><th>No. Passed</th><th>%age Pass</th></tr>";
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

        <div class="row mt-5">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <div class="card border-primary">
               <div class="card-header bg_nhsuk-blue ">
                  <div class="card-title text-white h2">Detailed Data</div>
               </div>
               <div class="card-body">
                  <a href="account-graphs.php" class="btn btn-nhs my-3 mr-3">Graphs</a>
                  
                  <div class="dropdown d-inline-block">
                    <button class="btn btn-nhs dropdown-toggle my-3 mr-3" type="button" id="csvDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                      CSV Download
                    </button>
                    <div class="dropdown-menu" aria-labelledby="csvDropdown">
                      <a class="dropdown-item" href="csv-logbook.php?which=<?php echo $trainkey ?>">Default Format</a>
                      
                      <?php
                      // Check if csv_templates table exists
                      if ($usingSupabase) {
                          try {
                              $existsStmt = $supabase_pdo->prepare("select 1 from information_schema.tables where table_name = 'csv_templates' limit 1");
                              $existsStmt->execute();
                              $exists = (bool)$existsStmt->fetch(PDO::FETCH_NUM);
                              if ($exists) {
                                  $templates_result = $supabase_pdo->query('select id, template_name, description from csv_templates order by template_name');
                                  $rows = $templates_result->fetchAll(PDO::FETCH_ASSOC);
                                  if ($rows) {
                                      echo '<div class="dropdown-divider"></div>';
                                      echo '<h6 class="dropdown-header">Custom Templates</h6>';
                                      foreach ($rows as $template) {
                                          $template_name = htmlspecialchars($template['template_name']);
                                          $description = htmlspecialchars($template['description']);
                                          $template_id = $template['id'];
                                          echo "<a class=\"dropdown-item csv-template-link\" href=\"#\" data-template-id=\"{$template_id}\" title=\"{$description}\" data-toggle=\"modal\" data-target=\"#csvDateModal\">{$template_name}</a>";
                                      }
                                  }
                              }
                          } catch (Throwable $e) {
                              error_log('Supabase csv templates section failed: ' . $e->getMessage());
                          }
                      } else {
                          $template_table_exists = false;
                          $check_table = $pdo->query("SHOW TABLES LIKE 'csv_templates'");
                          if ($check_table && $check_table->rowCount() > 0) {
                              $template_table_exists = true;
                              $templates_query = "SELECT id, template_name, description FROM csv_templates ORDER BY template_name";
                              $templates_result = $pdo->query($templates_query);
                              if ($templates_result && $templates_result->rowCount() > 0) {
                                  echo '<div class="dropdown-divider"></div>';
                                  echo '<h6 class="dropdown-header">Custom Templates</h6>';
                                  while ($template = $templates_result->fetch(PDO::FETCH_ASSOC)) {
                                      $template_name = htmlspecialchars($template['template_name']);
                                      $description = htmlspecialchars($template['description']);
                                      $template_id = $template['id'];
                                      echo "<a class=\"dropdown-item csv-template-link\" href=\"#\" data-template-id=\"{$template_id}\" title=\"{$description}\" data-toggle=\"modal\" data-target=\"#csvDateModal\">{$template_name}</a>";
                                  }
                              }
                          }
                      }
                      
                      // Add link to CSV editor for admin users
                      if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1) {
                          echo '<div class="dropdown-divider"></div>';
                          echo '<a class="dropdown-item text-primary" href="oxex-admin/csv_editor.php">
                                  <i class="fas fa-cog"></i> Manage CSV Templates</a>';
                      }
                      ?>
                    </div>
                  </div>
                  <!-- was account-csv.php -->
               </div>
            </div>
         </div>
      </div>

        <div class="row mt-5">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <div class="card border-primary">
            <div class="card-header bg_nhsuk-blue ">
               <div class="card-title text-white h2">Competencies Passed</div>
            </div>
            <div class="card-body">
               <?php
               // list supervisor sign-offs
              if ($usingSupabase) {
                  $notes = $supabase_pdo->prepare('select trid, who_by, super_pass, super_txt, date_added, date_modified, tbid from trainee_report_ok where trainkey = ?');
                  $notes->execute([$trainkey]);
                  $rows = $notes->fetchAll(PDO::FETCH_ASSOC);
                  foreach ($rows as $row) {
                      $trid = $row['trid'];
                      $who_notes = $row['who_by'];
                      $super_pass = $row['super_pass'];
                      $super_txt = $row['super_txt'];
                      $date_added = strtotime($row['date_added']);
                      $date_modified = strtotime($row['date_modified']);
                      $stmt = $supabase_pdo->prepare('select realname from who_there where usrkey = ? limit 1');
                      $stmt->execute([$who_notes]);
                      $supername = ($tmp = $stmt->fetch(PDO::FETCH_ASSOC)) ? $tmp['realname'] : '';
                      $stmt = $supabase_pdo->prepare('select tab_name from tabs_tbl where tbid = ? limit 1');
                      $stmt->execute([$super_pass]);
                      $tab_name = ($tmp = $stmt->fetch(PDO::FETCH_ASSOC)) ? $tmp['tab_name'] : '';
                      echo "<p><em>Created on ".date("D jS M Y", $date_added). " and last modified on ".date("D jS M Y", $date_modified)." by $supername</em></p>";
                      echo "<p><strong>$tab_name Competency Passed</strong></p>";
                      echo "<a href=\"pdftest.php?trainee=$trainkey&amp;trid=$trid\" class=\"btn btn-nhs\">View PDF</a>";
                      if ($super_txt != '') {
                          echo "<p><small>$super_txt</small></p>";
                      }
                      echo "<hr>";
                  }
                  error_log("CHECKPOINT 10: Tables loop completed. Processed " . count($namarr) . " tables and " . $allreports . " reports");
                  $numnotes = is_array($rows) ? count($rows) : 0;
              }
               if ($numnotes == 0) {
                  echo "<p>No notes currently</p>";
               }
               ?>

            </div>

         </div><!-- END card-->
          </div>
        </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php
    if (login_check($pdo) != false) {
      include 'incl/glossary.php';
    }
    ?>
    <?php include 'incl/js.php' ?>

    <!-- Modal for CSV date selection -->
    <div class="modal fade" id="csvDateModal" tabindex="-1" role="dialog" aria-labelledby="csvDateModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="csvDateModalLabel">Select Timeframe for CSV Export</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="csvTemplateId" value="">
            <input type="hidden" id="csvWhich" value="<?php echo $trainkey ?>">
            
            <div class="form-group">
                <label for="timeframeSelect">Timeframe</label>
                <select class="form-control" id="timeframeSelect">
                    <option value="all" selected>All Time</option>
                    <option value="1year">Last Year</option>
                    <option value="6months">Last 6 Months</option>
                    <option value="3months">Last 3 Months</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div id="customDateRange" style="display: none;">
                <div class="form-group">
                    <label for="startDate">Start Date</label>
                    <input type="date" class="form-control" id="startDate">
                </div>
                <div class="form-group">
                    <label for="endDate">End Date</label>
                    <input type="date" class="form-control" id="endDate">
                </div>
            </div>
    
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="downloadCsvBtn">Download</button>
          </div>
        </div>
      </div>
    </div>

    <script>
    $(document).ready(function() {
        // When a template link is clicked, set the template ID in the modal
        $('.csv-template-link').on('click', function(e) {
            e.preventDefault();
            var templateId = $(this).data('template-id');
            $('#csvTemplateId').val(templateId);
        });
    
        // Show/hide custom date range fields
        $('#timeframeSelect').on('change', function() {
            if ($(this).val() === 'custom') {
                $('#customDateRange').show();
            } else {
                $('#customDateRange').hide();
            }
        });
    
        // Handle CSV download
        $('#downloadCsvBtn').on('click', function() {
            var which = $('#csvWhich').val();
            var templateId = $('#csvTemplateId').val();
            var timeframe = $('#timeframeSelect').val();
            
            var url = 'csv-logbook.php?which=' + which + '&template_id=' + templateId;
            
            var startDate = '';
            var endDate = '';
            var today = new Date();
    
            if (timeframe === '1year') {
                var lastYear = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
                startDate = lastYear.toISOString().split('T')[0];
                endDate = today.toISOString().split('T')[0];
            } else if (timeframe === '6months') {
                var last6Months = new Date(today.getFullYear(), today.getMonth() - 6, today.getDate());
                startDate = last6Months.toISOString().split('T')[0];
                endDate = today.toISOString().split('T')[0];
            } else if (timeframe === '3months') {
                var last3Months = new Date(today.getFullYear(), today.getMonth() - 3, today.getDate());
                startDate = last3Months.toISOString().split('T')[0];
                endDate = today.toISOString().split('T')[0];
            } else if (timeframe === 'custom') {
                startDate = $('#startDate').val();
                endDate = $('#endDate').val();
                if (!startDate || !endDate) {
                    alert('Please select a start and end date for the custom range.');
                    return;
                }
            }
    
            if (startDate && endDate) {
                url += '&start_date=' + startDate + '&end_date=' + endDate;
            }
            
            window.location.href = url;
            $('#csvDateModal').modal('hide');
        });
    });
    </script>

  </body>
  <?php
    // logged in only!
    error_log("CHECKPOINT 11: Account.php execution completed successfully");
    }
    ?>
</html>