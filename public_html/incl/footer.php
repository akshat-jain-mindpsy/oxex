<div class="container-fluid footer py-3 mt-5">
  <div class="container">
    <div class="row justify-content-md-center">
      <div class="col-md-auto">
        <?php
        // stick a © symbol in
        $copy = " ©".date("Y")." ";
        $footerl = str_replace("<p>", "<p> $copy", $footerl);
        ?>
        <?php echo $footerl ?>
      </div>
      <div class="col">
        <?php $footerm ?>
      </div>
      <div class="col">
        <?php $footerr ?>
      </div>
    </div>
  </div>
</div>