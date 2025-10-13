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
// Render glossary terms using Supabase/Postgres
if (isset($supabase_pdo) && $supabase_pdo instanceof PDO) {
  try {
    $stmt = $supabase_pdo->query('select term, description from glossary');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $term = $row['term'];
      $description = $row['description'];
      $description = str_replace("<p>", "<p><strong>$term - </strong> ", $description);
      echo "<p>$description</p>";
    }
  } catch (Throwable $e) {
    error_log('Glossary load failed: ' . $e->getMessage());
    echo '<p class="text-danger">Failed to load glossary.</p>';
  }
} else {
  echo '<p class="text-warning">Database not available.</p>';
}
?>
      </div>
      <div class="modal-footer bg-nhsuk-grey-3">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>