<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
include 'incl/sess.php';
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title><?php echo $googleTitle ?></title>
    <meta name="description" content="<?php echo $googleDesc ?>">
    <meta name="keywords" content="<?php echo $googleKeywords ?>">
    <?php include 'incl/meta.php' ?>  
  </head>
  <body>
    <?php include 'incl/banner.php' ?>
    <?php include 'incl/subnav.php' ?>
    <div class="container-fluid py-5">
      <div class="container">
        <div class="row">
        <div class="col-xs-12 col-sm-6">
          <?php
          if ($page_title != '') {
            echo "<h1>$page_title</h1>";
          }
          ?>
          <?php echo $page_txt2 ?>
          <?php
          if(isset($loggedout)) {
            if ($loggedout == "loggedout") {
              echo "<p>Thank you for visiting www.oxex.co.uk</p>\n";
            }
          }
            ?>
          <!-- Login form -->
          <form class="form-horizontal my-5 mx-auto" method="post" action="login2.php">
            <!-- Email -->
            <div class="form-group">
              <label class="control-label col-lg-3" for="ee">Email</label>
              <div class="col-lg-9">
                <input type="email" class="form-control" id="ee" placeholder="Email" name="ee" required>
              </div>
            </div>
            <!-- Password -->
            <div class="form-group">
              <label class="control-label col-lg-3" for="pp">Password</label>
              <div class="col-lg-9">
                <input type="password" class="form-control" id="pp" placeholder="Password" name="pp" required>
              </div>
            </div>
            <div class="col-lg-9 col-lg-offset-3">
              <button type="submit" class="btn btn-nhs">Sign in</button>
            </div>
          </form>
          <p><a href="reset.php" class="my-3" role="button" title="reset password">Reset Password</a></p>
          <?php echo $page_txt3 ?>
        </div>
        <div class="col-xs-12 col-sm-5 offset-sm-1">
          <?php
          if ($page_txt4 != '') {
            echo "<div class=\"px-3 py-3\">$page_txt4</div>";
          }
          if ($page_txt5 != '') {
            echo "<div class=\"px-3 py-3 mt-3\">$page_txt5</div>";
          }
          if ($page_txt6 != '') {
            echo "<div class=\"px-3 py-3 mt-3\">$page_txt6</div>";
          }
          ?>
        </div>
      </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php include 'incl/js.php' ?>
  </body>
</html>