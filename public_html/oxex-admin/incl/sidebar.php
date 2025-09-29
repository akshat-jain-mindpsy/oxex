<?php
// Set default values if not defined
$adminstatus = $adminstatus ?? 'Active';
$admintype = $admintype ?? 'Unknown';
?>
<aside class="aside-container">
   <!-- START Sidebar (left)-->
   <div class="aside-inner">
      <nav class="sidebar" data-sidebar-anyclick-close>
         <!-- START sidebar nav-->
         <ul class="sidebar-nav">
            <li class="sidebar-app-logo d-flex align-items-center justify-content-center py-3 d-md-none text-white h3"><?php echo $adminname ?></li><!-- START user info-->
            <li class="has-user-block">
               <div id="user-block" data-toggle="collapse" data-target="#user-links">
                  <div class="item user-block">
                     <div class="user-block-content">
                        <div class="user-block-picture"><img class="img-thumbnail rounded-circle" src="assets/img/user/<?php echo $adminphoto ?>" alt="Avatar" width="60" height="60"></div><!-- Name and Job-->
                        <div class="user-block-info"><span class="user-block-name"><?echo $realname ?></span><!-- START Dropdown to change status-->
                           <div class="btn-group user-block-status"><button class="btn btn-inverse btn-xs dropdown-toggle no-caret" type="button" data-toggle="dropdown">
                              <?php
                              // our status
                              if ($adminstatus == 1) {
                                 echo "<div class=\"p-1 rounded d-inline-block bg-danger mr-2\"></div><span>Busy</span>";
                              } else {
                                 echo "<div class=\"p-1 rounded d-inline-block bg-success mr-2\"></div><span>Online</span>";
                              }
                              ?>
                              </button>
                              <ul class="dropdown-menu">
                                 <div class="dropdown-item"><a href="<?php echo $thispage."?adminstatus=2" ?>"><span class="p-1 rounded d-inline-block bg-success mr-1 mr-2"></span></span>Online</div>
                                 <div class="dropdown-item"><a href="<?php echo $thispage."?adminstatus=1" ?>"><span class="p-1 rounded d-inline-block bg-danger mr-1 mr-2"></span>Busy</a></div>
                              </ul>
                           </div><!-- END Dropdown to change status-->
                        </div>
                     </div>
                  </div>
               </div>
            </li><!-- END user info-->
            
            <!-- Iterates over all sidebar items-->
            <?php
            /* ADMIN TYPES:
             AT = "Full Admin Control";
             AO = "Oxford Course Tutor";
             AE = "Exeter Course Tutor";
             SO = "Oxford Supervisor";
             SE = "Exeter Supervisor";
             DV = "Developer";
            */
            // $whichDocModal selects which texts are displayed in the 'docsModal'
            // 0=dashboard, 1=Pages, 2=Trainees, 3=Tables, 4=Blog, 5=Admin
            $url = 'indextable.php';
            $page = 'Dashboard';
            if ($thispage == $url) {
               $isactive = ' active';
               $whichDocModal = 0;
            } else {
               $isactive = ' ';
            }
            echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
            ?>
            
            <!-- DATA MANAGEMENT SECTION -->
            <li class=" "><a href="#data" title="Data Management" data-toggle="collapse"><span>Data Management</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="data">
                  <li class="sidebar-subnav-header">Data Structure & Content</li>
                  
                                     <!-- Core Data Structure -->
                   <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                   <li class="sidebar-subnav-header-sub">Structure</li>
                  <?php
                     $url = 'sheets.php';
                     $urldetail = 'sheetdetail.php';
                     $page = 'Sheets';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php
                     $url = 'sections.php';
                     $page = 'Sections';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php
                     $url = 'categories.php';
                     $urldetail = 'listtypedetail.php';
                     $page = 'Categories';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php
                     $url = 'items.php';
                     $urldetail = 'listdetail.php';
                     $page = 'Items';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                                     <!-- Data Standards & Rules -->
                   <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                   <li class="sidebar-subnav-header-sub">Standards & Rules</li>
                  <?php
                     $url = 'pass_standards.php';
                     $urldetail = 'pass_standard_detail.php';
                     $page = 'Pass Standards';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php
                     $url = 'tasks.php';
                     $urldetail = 'taskdetail.php';
                     $page = 'Attendance Tasks';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                                     
               </ul>
            </li>
            
            <!-- USER MANAGEMENT SECTION -->
            <li class=" "><a href="#users" title="User Management" data-toggle="collapse"><span>User Management</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="users">
                  <li class="sidebar-subnav-header">Trainees & Groups</li>
                  
                  <!-- Trainee Management -->
                  <?php
                     $url = 'trainee.php';
                     $urldetail = 'traineedetail.php';
                     $urldetail2 = 'traineelogbook.php';
                     $page = 'Trainees';
                     if ($thispage == $url || $thispage == $urldetail || $thispage == $urldetail2) {
                        $isactive = ' active';
                        $whichDocModal = 2;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  
                  <!-- Group Management -->
                  <?php
                     $url = 'subsets.php';
                     $urldetail = 'subsetdetail.php';
                     $urldetail2 = 'subsetstats.php';
                     $urldetail3 = 'compositestats.php';
                     $page = 'Trainee Groups';
                     if ($thispage == $url || $thispage == $urldetail || $thispage == $urldetail2 || $thispage == $urldetail3) {
                        $isactive = ' active';
                        $whichDocModal = 2;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  
                                     <!-- Admin Users -->
                   <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                   <li class="sidebar-subnav-header-sub">System Users</li>
                  <?php
                     $url = 'adminusers.php';
                     $urldetail = 'adminusersdetail.php';
                     $page = 'Admin Users';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 5;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
               </ul>
            </li>
            
            <!-- ANALYSIS & REPORTING SECTION -->
            <li class=" "><a href="#analysis" title="Analysis & Reporting" data-toggle="collapse"><span>Analysis & Reporting</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="analysis">
                  <li class="sidebar-subnav-header">Reports & Analytics</li>
                  
                  <!-- Trainee Statistics -->
                  <?php if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO" || $admintype == "AE" || $admintype == "SO" || $admintype == "SE"): ?>
                  <?php
                     $url = 'trainee_stats.php';
                     $page = 'Trainee Statistics';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Reports -->
                  <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                  <?php
                     $url = 'reports.php';
                     $urldetail = 'reportdetail.php';
                     $urldetail2 = 'reportcompetency.php';
                     $page = 'Report Manager';
                     if ($thispage == $url || $thispage == $urldetail || $thispage == $urldetail2) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Advanced Searches -->
                  <?php if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO" || $admintype == "AE"): ?>
                  <?php
                     $url = 'composite.php';
                     $urldetail = 'compositedetail.php';
                     $page = 'Composite Searches';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Visualizations -->
                  <?php if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO" || $admintype == "AE"): ?>
                  <?php
                     $url = 'graph_view.php';
                     $page = 'Graph View';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                                     <!-- Graph Configuration -->
                   <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                   <?php
                      $url = 'graph.php';
                      $urldetail = 'graphdetail.php';
                      $page = 'Graph Key Colours';
                      if ($thispage == $url || $thispage == $urldetail) {
                         $isactive = ' active';
                         $whichDocModal = 3;
                      } else {
                         $isactive = ' ';
                      }
                      echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                   ?>
                   <?php endif; ?>
                   
                   <!-- Data Export -->
                   <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                   <li class="sidebar-subnav-header-sub">Export</li>
                   <?php
                      $url = 'csv_editor.php';
                      $page = 'CSV Templates';
                      if ($thispage == $url) {
                         $isactive = ' active';
                         $whichDocModal = 3;
                      } else {
                         $isactive = ' ';
                      }
                      echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                   ?>
                   <?php endif; ?>
               </ul>
            </li>
            
            <!-- CONTENT MANAGEMENT SECTION -->
            <li class=" "><a href="#content" title="Content Management" data-toggle="collapse"><span>Content Management</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="content">
                  <li class="sidebar-subnav-header">Website Content</li>
                  
                  <!-- Main Content -->
                  <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                  <?php
                     $url = 'pages.php';
                     $urldetail = 'pagedetail.php';
                     $page = 'Main Page Content';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 1;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Reference Content -->
                  <?php if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO" || $admintype == "AE"): ?>
                  <?php
                     $url = 'glossary.php';
                     $urldetail = 'glossarydetail.php';
                     $page = 'Glossary';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 1;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Site Configuration -->
                  <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                  <?php
                     $url = 'footer.php';
                     $page = 'Footer';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 1;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
               </ul>
            </li>
            
            <!-- SYSTEM ADMINISTRATION SECTION -->
            <li class=" "><a href="#admin" title="System Administration" data-toggle="collapse"><span>System Administration</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="admin">
                  <li class="sidebar-subnav-header">System Configuration</li>
                  
                  <!-- System Configuration -->
                  <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                  <?php
                     $url = 'course.php';
                     $urldetail = 'coursedetail.php';
                     $page = 'University / Course';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 5;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php
                     $url = 'emails.php';
                     $urldetail = 'emaildetail.php';
                     $page = 'Site Emails';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 5;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
                  
                  <!-- Communication -->
                  <?php
                     $url = 'messages.php';
                     $page = 'Admin Messages';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 5;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  
                  <!-- Documentation -->
                  <?php if ($admintype == "DV" || $admintype == "AT"): ?>
                  <?php
                     $url = 'docs.php';
                     $urldetail = 'docdetail.php';
                     $page = 'Admin Documentation';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 5;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  ?>
                  <?php endif; ?>
               </ul>
            </li>
            
         </ul>
      </nav>
   </div><!-- END Sidebar (left)-->
</aside>