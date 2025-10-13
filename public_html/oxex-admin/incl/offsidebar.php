<aside class="offsidebar d-none">
   <!-- START Off Sidebar (right)-->
   <nav>
      <!-- START user info-->
      <div class="p-3" style="background-image: url('img/offsidebar-bg.jpg')">
         <div class="text-center">
            <p><img class="img-fluid rounded-circle img-thumbnail" src="assets/img/user/<?php echo $adminphoto ?>" style="width: 64px; height: 64px" alt="Image"></p>
            <p class="text-white"><strong><?php echo $realname ?></strong></p>
         </div>
      </div><!-- END user info-->
      <!-- START list title-->
      <div class="px-2 py-3"><small class="text-muted">ONLINE</small></div>
      <?php
      // users probably online
      $now = time();
      $tableset = $supabase_pdo->prepare("SELECT isonline, realname, photo, lastlogin, isdev FROM who_there WHERE isonline != ?");
      $tableset->execute([$value0]);
      while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
        $isonline = $row['isonline'];
        $realname = $row['realname'];
        $senderphoto = $row['photo'];
        $lastlogin = $row['lastlogin'];
        $isdev = $row['isdev'];
         if ($senderphoto == '') {
            $senderphoto = "assets/img/wizard.jpg";
         } else {
            $senderphoto = "assets/img/user/$senderphoto";
         }
         $hoursin = ($now - $lastlogin) / 3600;
         $hoursintxt = number_format($hoursin, 1, '.', ','). ' hours';
         if ($hoursin > 14400) {
            $hoursintxt = 'probably offline';
         }
         if ($isdev == 1) {
            $hoursintxt = 'Developer';
         }
         if ($isdev == 0) {
            // hide devs
            echo "<div class=\"p-2 dark-on-hover\">";
            echo "<div class=\"d-flex\">";
               echo "<div class=\"d-flex\"><img class=\"img-fluid rounded-circle\" src=\"$senderphoto\" style=\"width: 40px; height: 40px\" alt=\"Image\">";
                  echo "<div class=\"ml-2\"><strong class=\"text-white\">$realname</strong><br><small class=\"text-muted\">$hoursintxt</small></div>";
               echo "</div>";
               echo "<div class=\"ml-auto\">";
               if ($isonline == 1 && $hoursin < 14400) {
                  echo "<div class=\"p-1 rounded d-inline-block mr-2 bg-danger\">$isdev</div>";
               }
               if ($isonline == 2 && $hoursin < 14400) {
                  echo "<div class=\"p-1 rounded d-inline-block mr-2 bg-success\"></div>";
               }
               if ($hoursin > 4) {
                  echo "<div class=\"p-1 rounded d-inline-block mr-2 bg-gray\"></div>";
               }
               echo "</div>";
            echo "</div>";
         echo "</div>";
         }
      }
      ?>
      <div class="px-2 py-3"><small class="text-muted">OFFLINE</small></div>
      <?php
      // users probably offline
      $now = time();
      $tableset = $supabase_pdo->prepare("SELECT isonline, realname, photo, isdev FROM who_there WHERE isonline = ?");
      $tableset->execute([$value0]);
      while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
        $isonline = $row['isonline'];
        $realname = $row['realname'];
        $senderphoto = $row['photo'];
        $isdev = $row['isdev'];
         if ($senderphoto == '') {
            $senderphoto = "assets/img/wizard.jpg";
         } else {
            $senderphoto = "assets/img/user/$senderphoto";
         }
         if ($isdev == 0) {
            // hide devs
            echo "<div class=\"p-2 dark-on-hover\">";
            echo "<div class=\"d-flex\">";
               echo "<div class=\"d-flex\"><img class=\"img-fluid rounded-circle\" src=\"$senderphoto\" style=\"width: 40px; height: 40px\" alt=\"Image\">";
                  echo "<div class=\"ml-2\"><strong class=\"text-white\">$realname</strong></div>";
               echo "</div>";
               echo "<div class=\"ml-auto\">";
                  echo "<div class=\"p-1 rounded d-inline-block mr-2 bg-gray\"></div>";
               echo "</div>";
            echo "</div>";
         echo "</div>";
         }
      }
      ?>
      <div class="p-2 dark-on-hover">
         <!-- Optional link to list more users--><a class="p" href="#" title="See more contacts"><strong><small class="text-muted">&hellip;</small></strong></a></div>
   </nav>
</aside>