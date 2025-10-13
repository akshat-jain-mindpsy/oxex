<div id="passfail" class="row">
   <?php
   // who is this supervisor?
   $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->execute([$usrkey]);
   $row = $stmt->fetch(PDO::FETCH_ASSOC);
   $whoami = $row ? $row['realname'] : '';
   ?>
   <div class="col-xl-7 mt-5">
      <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" >
         <div class="card border-pink">
            <div class="card-header bg-pink">
               <div class="card-title">Supervisor Sign-Off for <?php echo $name ?> </div>
            </div>
            <div class="card-body">
               
               <div class="row">
                    <div class="col">
                        <div class="form-group">
                           <label class="col-form-label" for="super_pass">Competency for Supervisor to sign off</label>
                           <select class="custom-select mb-3" id="super_pass" name="super_pass" required>
                             <option selected="selected" value="0">Select...</option>
                             <?php
                             // list competency passes
                             $compset = $supabase_pdo->prepare("SELECT tbid, tab_name FROM tabs_tbl ");
                              $compset->execute();
                              while ($row = $compset->fetch(PDO::FETCH_ASSOC)){
                                 $tbid = $row['tbid'];
                                 $tab_name = $row['tab_name'];
                                 echo "<option value=\"$tbid\">$tab_name Competency Passed</option>";
                              }
                              
                             ?>
                           </select>
                        </div>
                    </div>
                 </div>
                 <div class="form-group">
                  <label class="col-form-label" for="super_txt">Notes (for admin only)</label>
                  <textarea id="super_txt" name="super_txt" class="form-control"></textarea>
                </div>
                <p><strong>Signed off by placement Tutor/Supervisor <?php echo $whoami ?> </strong></p>
                 
                
            </div>
            
            <div class="card-footer">
               <input type="hidden" name="done" value="passfail">
               <input type="hidden" name="which" value="<?PHP echo $which ?>">
               <div class="float-right"><button class="btn btn-pink" type="submit">Record Sign-off</button></div>
            </div>
         </div><!-- END card-->
      </form>
   </div>
   
   <div class="col-xl-5 mt-5">
      <div class="card border-pink">
            <div class="card-header bg-pink">
               <div class="card-title">Competencies Passed for <?php echo $name ?> </div>
            </div>
            <div class="card-body">
               <?php
               // list supervisor sign-offs
               $tableset = $supabase_pdo->prepare("SELECT trid, who_by, super_pass, super_txt, date_added, date_modified, tbid FROM trainee_report_ok WHERE trainkey = ? ");
               $tableset->execute([$which]);
               while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
                  $trid = $row['trid'];
                  $who_notes = $row['who_by'];
                  $super_pass = $row['super_pass'];
                  $super_txt = $row['super_txt'];
                  $date_added = $row['date_added'];
                  $date_modified = $row['date_modified'];
                  $tbid = $row['tbid'];
                  $date_added = strtotime($date_added);
                  $date_modified = strtotime($date_modified);
                  // who?
                  $stmt = $supabase_pdo->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
                  $stmt->execute([$who_notes]);
                  $row2 = $stmt->fetch(PDO::FETCH_ASSOC);
                  $supername = $row2 ? $row2['realname'] : '';
                  // which competency
                  $stmt = $supabase_pdo->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
                  $stmt->execute([$super_pass]);
                  $row3 = $stmt->fetch(PDO::FETCH_ASSOC);
                  $tab_name = $row3 ? $row3['tab_name'] : '';
                  echo "<p><em>Created on ".date("D jS M Y", $date_added). " and last modified on ".date("D jS M Y", $date_modified)." by $supername</em></p>";
                  
                     echo "<p><strong>$tab_name Competency Passed</strong></p>";
                     echo "<a href=\"pdftest.php?trainee=$which&amp;trid=$trid\" class=\"btn btn-pink\">View PDF</a>";
                  
                  if ($super_txt != '') {
                     echo "<p><small>$super_txt</small></p>";
                  }
                  echo "<hr>";
               }
               $numnotes = $tableset->rowCount();
               if ($numnotes == 0) {
                  echo "<p>No notes currently</p>";
               }
               ?>
               
               
                 
                
            </div>
            
            <div class="card-footer">
               <a href="csv-logbook.php?which=<?php echo $which ?>" class="btn btn-secondary ml-5">Download CSV Logbook</a>
            </div>
         </div><!-- END card-->
   </div>

</div>