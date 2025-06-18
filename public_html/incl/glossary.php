<!-- Modal -->
<div class="modal fade" id="glossaryModal" tabindex="-1" aria-labelledby="glossaryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg_nhsuk-blue">
        <h5 class="modal-title text-white" id="glossaryModalLabel">Glossary</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
<?php
$tableset = $mysqli->prepare("SELECT term, description FROM glossary ");
$tableset->execute();
$tableset->store_result();
$tableset->bind_result($term, $description);
while ($tableset->fetch()){
  $description = str_replace("<p>", "<p><strong>$term - </strong> ", $description);
  echo "<p>$description</p>";
}
$numrows = $tableset->num_rows;
$tableset->close();
?>
      </div>
      <div class="modal-footer bg-nhsuk-grey-3">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>