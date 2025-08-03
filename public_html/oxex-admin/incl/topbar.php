<?php
// Set default values if not defined
$global_short_name = $global_short_name ?? 'OXEX Admin';
$adminname = $adminname ?? 'Admin';
$adminphoto = $adminphoto ?? 'default.jpg';
?>
<header class="topnavbar-wrapper">
   <!-- START Top Navbar-->
   <nav class="navbar topnavbar">
      <!-- START navbar header-->
      <div class="navbar-header">
            <div class="navbar-header"><a class="navbar-brand" href="#/">
            <h3 class="text-white py-3"><?php echo htmlspecialchars($global_short_name); ?></h3>
         </a></div><!-- END navbar header-->
      </div>
      <!-- START Left navbar-->
      <ul class="navbar-nav mr-auto flex-row">
         <li class="nav-item">
            <!-- Button used to collapse the left sidebar. Only visible on tablet and desktops--><a class="nav-link d-none d-md-block d-lg-block d-xl-block ml-5" href="#" data-trigger-resize="" data-toggle-state="aside-collapsed"><em class="fas fa-align-left"></em></a><!-- Button to show/hide the sidebar on mobile. Visible on mobile only.--><a class="nav-link sidebar-toggle d-md-none" href="#" data-toggle-state="aside-toggled" data-no-persist="true"><em class="fas fa-align-left"></em></a></li><!-- Search icon-->
         <li class="nav-item"><a class="nav-link btn btn-sm btn-secondary" href="#" data-toggle="modal" data-target="#docsModal">Docs</a></li>
      </ul><!-- END Left navbar-->
      <!-- START Right Navbar-->
      <ul class="navbar-nav flex-row">
         <li class="nav-item"><a class="nav-link" href="logout.php" title="Log Out"><em class="fas fa-sign-out-alt"></em></a></li>
         <!-- START Messages menu-->
         <?php 
         // how many unread messages?
         $vids = $mysqli->prepare("SELECT amid FROM admin_msg WHERE usrkey_to = ? AND isread = ?");
         $vids->bind_param("si", $usrkey, $value0);
         $vids->execute();
         $vids->store_result();
         $nummsg = $vids->num_rows;
         $vids->close();
         ?>
         <li class="nav-item dropdown dropdown-list"><a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" data-toggle="dropdown"><em class="fas fa-envelope"></em><span class="badge badge-danger"><?php echo $nummsg ?></span></a><!-- START Dropdown menu-->
            <div class="dropdown-menu dropdown-menu-right animated bounceIn">
               <div class="dropdown-header">You have <?php echo $nummsg ?> new messages</div>
               <div data-scrollable data-height="210">
                  <?php
                  $now = time();
                  // list 3 recent unread messages
                  if ($nummsg > 0) {
                     $tableset = $mysqli->prepare("SELECT usrkey_from, date_sent, msg FROM admin_msg WHERE usrkey_to = ? AND isread = ? ORDER BY date_sent DESC LIMIT 3");
                     $tableset->bind_param("si", $usrkey, $value0);
                     $tableset->execute();
                     $tableset->store_result();
                     $tableset->bind_result($usrkey_from, $date_sent, $msg);
                     while ($tableset->fetch()){
                        $msg_sm = substr($msg, 0, 80)."...";
                        // lookup sender
                        $stmt = $mysqli->prepare("SELECT realname, isonline, lastlogin, photo FROM who_there WHERE usrkey = ?");
                        $stmt->bind_param("s", $usrkey_from);
                        $stmt->execute();
                        $stmt->store_result();
                        $stmt->bind_result($mailername, $isonline, $lastlogin, $senderphoto);
                        $stmt->fetch();
                        $stmt->close();
                        if ($mailername == '') {
                           $mailername = "assets/img/wizard.jpg";
                        } else {
                           $mailername = "assets/img/user/$senderphoto";
                        }
                        $hoursin = ($now - $lastlogin) / 3600;
                        $hoursintxt = number_format($hoursin, 1, '.', ','). 'h';
                        if ($hoursin > 14400) {
                           $hoursintxt = 'offline';
                        }
                        if ($isdev == 1) {
                           $hoursintxt = 'Dev';
                        }
                        if ($isonline == 1 && $hoursin < 14400) {
                           $statuslabel = "bg-danger";
                        }
                        if ($isonline == 2 && $hoursin < 14400) {
                           $statuslabel = "bg-success";
                        }
                        if ($hoursin > 4) {
                           $statuslabel = "bg-gray";
                        }
                        echo "<div class=\"dropdown-item\">";
                        echo "   <div class=\"list-group\">";
                        echo "      <div class=\"list-group-item list-group-item-action\">";
                        echo "         <div class=\"media\">";
                        echo "            <div class=\"align-self-start mr-2\"><img class=\"media-object rounded\" style=\"width: 48px; height: 48px;\" src=\"$mailername\" alt=\"Image\"></div>";
                        echo "            <div class=\"media-body clearfix\"><small class=\"float-right\">$hoursintxt</small><strong class=\"media-heading text-primary\"><span class=\"p-1 rounded d-inline-block $statuslabel mr-2\"></span><span>$sendername</span></strong>
                                       <p class=\"mb-sm\"><small>$msg_sm</small></p>";
                        echo "            </div>";
                        echo "         </div>";
                        echo "      </div>";
                        echo "   </div>";
                        echo "</div>";
                     }
                     $numrows = $tableset->num_rows;
                     $tableset->close();
                  }
                  ?>
               </div>
               <div class="p-3"><small><a class="btn btn-sm btn-purple" href="messages.php">READ MESSAGES</a></small></div>
               <div class="p-3 my-3">
                  <h4>Create new message</h4>
                  <form id="messsageForm">
                     <div class="form-check">
                        <input class="form-check-input" id="msgEmailAll" type="checkbox" value="all"><label class="form-check-label" for="msgEmailAll">All Admins</label>
                     </div>
                     <?php 
                     // list all recipients; not developers, and not self
                     $tableset = $mysqli->prepare("SELECT whid, realname FROM who_there WHERE isdev = ? AND usrkey != ? ");
                     $tableset->bind_param("is", $value0, $usrkey);
                     $tableset->execute();
                     $tableset->store_result();
                     $tableset->bind_result($msgwhid, $msgrealname);
                     while ($tableset->fetch()){
                        echo "<div class=\"form-check\">\r
                        <input class=\"form-check-input\" id=\"msgEmail$msgwhid\" type=\"checkbox\" value=\"$msgwhid\">\r<label class=\"form-check-label\" for=\"msgEmail$msgwhid\">$msgrealname</label>\r";
                     
                     echo "</div>\r";
                     }
                     $tableset->close();
                     ?>
                     
                     <div class="form-group">
                        <textarea class="form-control mt-3" id="msgTxt" placeholder="Your Message..."></textarea>
                     </div>
                     <input id="msgSubmit" type="button" value="Send">
                  </form>
                  <div id="messageMsg"></div>
               </div>

               <!-- END list group-->
            </div><!-- END Dropdown menu-->
         </li>
         <!-- START User menu-->
         <li class="nav-item dropdown"><a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" data-toggle="dropdown"><em class="fas fa-user"></em></a><!-- START Dropdown menu-->
            <div class="dropdown-menu dropdown-menu-right animated bounceIn">
               <div class="dropdown-item"><a href="adminusersdetail.php?user=<?php echo $usrkey ?>">Profile</a></div>
               <div class="dropdown-item"><a href="logout.php">Logout</a></div>
            </div><!-- END Dropdown menu-->
         </li>
         <!-- START Offsidebar button-->
         <li class="nav-item"><a class="nav-link" href="#" data-toggle-state="offsidebar-open" data-no-persist="true"><em class="fas fa-align-right"></em></a></li><!-- END Offsidebar menu-->
      </ul><!-- END Right Navbar-->
      <!-- START Search form-->
      <form class="navbar-form" role="search" action="search.html">
         <div class="form-group"><input class="form-control" type="text" placeholder="Type and hit enter ...">
            <div class="fas fa-times navbar-form-close" data-search-dismiss=""></div>
         </div><button class="d-none" type="submit">Submit</button>
      </form><!-- END Search form-->
   </nav><!-- END Top Navbar-->
</header>