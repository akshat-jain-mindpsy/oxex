<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'public_html/error.log');  // Specify your desired log path

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
          <div class="col-xs-12 col-sm-8 offset-sm-2 text-center">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
            }
            ?>
          </div>
        </div>
      </div>
    </div>
    <div class="container-fluid py-5">
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
              echo $page_txt2;
            ?>
          </div>
        </div>
      </div>
    </div>
    <div class="container-fluid">
      <div class="container">
        <div class="row my-3">
          <div class="col-xs-12 col-sm-8 offset-sm-2">
            <?php
              echo $page_txt3;
            ?>
            <a href="login.php" class="btn btn-lg btn-nhs my-3">Login</a>
          </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php include 'incl/js.php' ?>
  </body>
</html>