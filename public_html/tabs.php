<?php 
include 'OXEXfolder/config.php';
include 'OXEXfolder/p_functions.php';
sec_session_start();
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
  <?php
    if (login_check($pdo) != false) {
      // logged in only!
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
            }
            ?>
          </div>
        </div>
      </div>
      <div class="container">
        <div class="row">
          <div class="col-xs-12 col-sm-6">
            <?php
              echo $page_txt2;
            ?>
          </div>
          <div class="col-xs-12 col-sm-6">
            <?php
              echo $page_txt3;
            ?>
          </div>
        </div>
        <div class="row mt-3">
          <?php
          // loop through notes for Tables
          $tableset = $supabase_pdo->prepare('select tbid, tab_name, tab_notes from tabs_tbl where sort_order != ? order by sort_order');
          $tableset->execute([$value0]);
          while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
            $tbid = (int)$row['tbid'];
            $tab_name = $row['tab_name'];
            $tab_notes = $row['tab_notes'];
            echo "<div class=\"col-xs-12 col-sm-6\">";
            echo "<h3>$tab_name</h3>";
            echo $tab_notes;
            echo "</div>";
          }
          ?>
          
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
  </body>
  <?php
    // logged in only!
    }
    ?>
</html>