<!-- Modal for admin documentation-->
<?php
// get title for this section
switch ($whichDocModal) {
  case 0:
  $section = 'Dashboard';
  break;
  case 1:
  $section = 'Pages';
  break;
  case 2:
  $section = 'Trainees';
  break;
  case 3:
  $section = 'Tables';
  break;
  case 4:
  $section = 'Blog';
  break;
  case 5:
  $section = 'Admin';
  break;
}
?>
<div class="modal fade" id="docsModal" tabindex="-1" aria-labelledby="docsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docsModalLabel">Documentation for "<?php echo $section ?>"</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <?php
        // get texts for this section
        $tableset = $supabase_pdo->prepare("SELECT sect_title, sect_txt FROM docs_tbl WHERE doc_section = ? AND sort_order != ? ORDER BY sort_order ASC");
        $tableset->execute([$whichDocModal, $value0]);
        $numdocs = 0;
        while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
          $sect_title = $row['sect_title'];
          $sect_txt = $row['sect_txt'];
          echo "<h5><em>$sect_title</em></h5>";
          echo $sect_txt;
          $numdocs++;
        }
        if ($numdocs == 0) {
          echo "<p><strong>Currently no documentation for this section</strong></p>";
        }
        ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- STORAGE API
<script src="assets/vendor/js-storage/js.storage.js"></script>
<script src="assets/vendor/screenfull/dist/screenfull.js"></script>
<script src="assets/vendor/i18next/i18next.js"></script>
<script src="assets/vendor/i18next-xhr-backend/i18nextXHRBackend.js"></script>-->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script>
// Fallback to local jQuery if CDN fails
if (typeof window.jQuery === 'undefined') {
  document.write('<script src="assets/vendor/jquery/dist/jquery.js"><\/script>');
}
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
<script>
// Fallback to local Bootstrap if CDN fails (requires jQuery)
(function(){
  if (!window.jQuery) return;
  var bsLoaded = !!(jQuery.fn && jQuery.fn.modal);
  if (!bsLoaded) {
    var s = document.createElement('script');
    s.src = 'assets/vendor/bootstrap/dist/js/bootstrap.js';
    document.head.appendChild(s);
  }
})();
</script>
<!-- Datatables-->
<script src="assets/vendor/datatables.net/js/jquery.dataTables.js"></script>
<script src="assets/vendor/datatables.net-bs4/js/dataTables.bootstrap4.js"></script>
<script src="assets/vendor/datatables.net-buttons/js/dataTables.buttons.js"></script>
<script src="assets/vendor/datatables.net-buttons-bs/js/buttons.bootstrap.js"></script>
<!--<script src="assets/vendor/datatables.net-buttons/js/buttons.colVis.js"></script>
<script src="assets/vendor/datatables.net-buttons/js/buttons.flash.js"></script>
<script src="assets/vendor/datatables.net-buttons/js/buttons.html5.js"></script>
<script src="assets/vendor/datatables.net-buttons/js/buttons.print.js"></script>-->
<script src="assets/vendor/datatables.net-keytable/js/dataTables.keyTable.js"></script>
<script src="assets/vendor/datatables.net-responsive/js/dataTables.responsive.js"></script>
<script src="assets/vendor/datatables.net-responsive-bs/js/responsive.bootstrap.js"></script>
<!-- PARSLEY validation -->
<script src="assets/vendor/parsleyjs/dist/parsley.js"></script>
<!-- Flot (required by app.js chart demos) -->
<script src="assets/vendor/flot/jquery.flot.js"></script>
<script src="assets/vendor/jquery.flot.tooltip/js/jquery.flot.tooltip.js"></script>
<!-- =============== APP ===============-->
<script src="assets/js/app.js"></script>
