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
          <div class="col">
            <?php
            if ($page_title != '') {
              echo "<h1>$page_title</h1>";
            }
            ?>
            <?php echo $page_txt2 ?>
          </div>
        </div>
      </div>
      <div class="container-md my-5 ">
        <div class="row">
          <div class="col-sm-6 py-5 bg-nhsuk-grey-3">
            <form method="post" action="reset2.php" class="py-3 pz-3">
              <div class="form-group">
                <label for="email" class="sr-only">Email address</label>
                <input type="email" class="form-control" name="email" id="email" aria-describedby="emailHelp" placeholder="Email address">
              </div>
                <input type="hidden" name="done" value="done">
                <button type="submit" class="btn btn-nhs">Reset password</button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php include 'incl/footer.php' ?>
    <?php include 'incl/js.php' ?>
  </body>
</html>