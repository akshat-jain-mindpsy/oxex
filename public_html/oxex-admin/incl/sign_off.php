<div id="passfail" class="row">
   <?php
   // who is this supervisor?
   $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
   $stmt->bind_param("s", $usrkey);
   $stmt->execute();
   $stmt->store_result();
   $stmt->bind_result($whoami);
   $stmt->fetch();
   $stmt->close();
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
                             $compset = $mysqli->prepare("SELECT tbid, tab_name FROM tabs_tbl ");
                              $compset->execute();
                              $compset->store_result();
                              $compset->bind_result($tbid, $tab_name);
                              while ($compset->fetch()){
                                 echo "<option value=\"$tbid\">$tab_name Competency Passed</option>";
                              }
                              $compset->close();
                              
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
               $tableset = $mysqli->prepare("SELECT trid, who_by, super_pass, super_txt, date_added, date_modified, tbid FROM trainee_report_ok WHERE trainkey = ? ");
               $tableset->bind_param("s", $which);
               $tableset->execute();
               $tableset->store_result();
               $tableset->bind_result($trid, $who_notes, $super_pass, $super_txt, $date_added, $date_modified, $tbid);
               while ($tableset->fetch()){
                  $date_added = strtotime($date_added);
                  $date_modified = strtotime($date_modified);
                  // who?
                  $stmt = $mysqli->prepare("SELECT realname FROM who_there WHERE usrkey = ?");
                  $stmt->bind_param("s", $who_notes);
                  $stmt->execute();
                  $stmt->store_result();
                  $stmt->bind_result($supername);
                  $stmt->fetch();
                  $stmt->close();
                  // which competency
                  $stmt = $mysqli->prepare("SELECT tab_name FROM tabs_tbl WHERE tbid = ?");
                  $stmt->bind_param("s", $super_pass);
                  $stmt->execute();
                  $stmt->store_result();
                  $stmt->bind_result($tab_name);
                  $stmt->fetch();
                  $stmt->close();
                  echo "<p><em>Created on ".date("D jS M Y", $date_added). " and last modified on ".date("D jS M Y", $date_modified)." by $supername</em></p>";
                  
                     echo "<p><strong>$tab_name Competency Passed</strong></p>";
                     echo "<a href=\"pdftest.php?trainee=$which&amp;trid=$trid\" class=\"btn btn-pink\">View PDF</a>";
                  
                  if ($super_txt != '') {
                     echo "<p><small>$super_txt</small></p>";
                  }
                  echo "<hr>";
               }
               $numnotes = $tableset->num_rows;
               $tableset->close();
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