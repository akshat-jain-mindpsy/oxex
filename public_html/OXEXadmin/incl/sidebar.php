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
            <!--START User links collapse
            <li class="nav collapse" id="user-links">

               <ul class="sidebar-nav sidebar-subnav">
                  <li><a href="#">Profile</a></li>
                  <li><a href="#">Settings</a></li>
                  <li><a href="#"><span>Notifications</span><span class="badge badge-danger float-right">120</span></a></li>
                  <li><a href="#"><span>Messages</span><span class="badge badge-success float-right">300</span></a></li>
                  <li><a href="#">Logout</a></li>
               </ul>
            </li> END User links collapse-->
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
            <li class=" "><a href="#train" title="Trainees" data-toggle="collapse"><span>Trainees</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="train">
                  <li class="sidebar-subnav-header">Trainee Area</li>
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
               </ul>
            </li>
            <li class=" "><a href="#report" title="Reports" data-toggle="collapse"><span>Tables &amp; Reports</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="report">
                  <li class="sidebar-subnav-header">Tables &amp; Reports Area</li>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'tabsections.php';
                     $page = 'Field Sections';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'tables.php';
                     $urldetail = 'tabledetail.php';
                     $page = 'Table Names';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'listtypes.php';
                     $urldetail = 'listtypedetail.php';
                     $page = 'Table Field Names';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'lists.php';
                     $urldetail = 'listdetail.php';
                     $page = 'Field Values';
                     if ($thispage == $url || $thispage == $urldetail) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO"  || $admintype == "AE") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                     if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO" || $admintype == "AE") {
                     $url = 'graph_view.php';
                     $page = 'Graph View';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'csv_editor.php';
                     $page = 'CSV Templates';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 3;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
               </ul>
            </li>
            <li class=" "><a href="#pages" title="Pages" data-toggle="collapse"><span>Page Content</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="pages">
                  <li class="sidebar-subnav-header">Page Content</li>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT" || $admintype == "AO"  || $admintype == "AE") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
                     $url = 'footer.php';
                     $page = 'Footer';
                     if ($thispage == $url) {
                        $isactive = ' active';
                        $whichDocModal = 1;
                     } else {
                        $isactive = ' ';
                     }
                     echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  }
                  ?>
               </ul>
            </li>
            
            <li class=" "><a href="#admin" title="Admin" data-toggle="collapse"><span>Admin</span></a>
               <ul class="sidebar-nav sidebar-subnav collapse" id="admin">
                  <li class="sidebar-subnav-header">Admin</li>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
                  <?php
                  if ($admintype == "DV" || $admintype == "AT") {
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
                  }
                  ?>
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
                  
                  <?php
                  /*
                  $url = 'template.php';
                  $page = 'Empty Template';
                  if ($thispage == $url) {
                     $isactive = ' active';
                  } else {
                     $isactive = ' ';
                  }
                  echo "<li class=\"$isactive\"><a href=\"$url\" title=\"$page\"><span>$page</span></a></li>";
                  */
                  ?>
               </ul>
            </li>
            
         </ul>
      </nav>
   </div><!-- END Sidebar (left)-->
</aside>