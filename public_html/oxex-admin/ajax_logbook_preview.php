<?php
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
sec_session_start();
include 'incl/sess.php';

// Check authorization
if (login_check($mysqli) != true || !in_array($admintype, ['AT', 'AO', 'AE', 'SO', 'SE', 'DV'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Not authorized'
    ]);
    exit;
}

// Get table ID
$tbid = isset($_POST['tbid']) ? (int)$_POST['tbid'] : 0;

// Validate input
if ($tbid <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid table ID'
    ]);
    exit;
}

// Fetch table details
$stmt = $mysqli->prepare("SELECT tab_name FROM tables WHERE tbid = ?");
$stmt->bind_param("i", $tbid);
$stmt->execute();
$stmt->bind_result($tab_name);
$stmt->fetch();
$stmt->close();

// Start output buffering to capture the preview HTML
ob_start();
?>
<div id="logbookPreviewDiv" class="card border-info mb-4 w-100">
    <div class="card-header bg-info text-white">
        <div class="card-title">
            <i class="fa fa-eye"></i> Logbook Preview - How fields will appear to users
        </div>
    </div>
    <div class="card-body">
        <h5 class="mb-3"><?php echo htmlspecialchars($tab_name); ?></h5>
        
        <!-- Date selector similar to logbook -->
        <div class="form-group">
            <label for="preview-date">Date</label>
            <div class="input-group">
                <input type="text" class="form-control" id="preview-date" value="<?php echo date('d/m/Y'); ?>" readonly>
                <div class="input-group-append">
                    <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                </div>
            </div>
        </div>
        
        <!-- Dynamically load fields -->
        <?php
        // Fetch and render fields
        $fields_stmt = $mysqli->prepare("
            SELECT st.stid, st.str, st.single, 
                   (SELECT GROUP_CONCAT(select_val SEPARATOR '|') 
                    FROM select_gen 
                    WHERE stid = st.stid) AS options
            FROM select_types st
            WHERE st.tbid = ?
            ORDER BY st.ord
        ");
        $fields_stmt->bind_param("i", $tbid);
        $fields_stmt->execute();
        $result = $fields_stmt->get_result();
        
        while ($field = $result->fetch_assoc()) {
            $type_text = match($field['single']) {
                0 => 'Single Selection',
                1 => 'Multiple Selection',
                2 => 'Text',
                3 => 'Date',
                4 => 'Numeric (step 0.1)',
                5 => 'Numeric (step integer)',
                6 => 'Time',
                default => 'Unknown'
            };
            
            // Render field based on type
            echo '<div class="col-sm-6 col-lg-4 field-details" data-stid="' . $field['stid'] . '">';
            echo '<div class="form-group mb-4">';
            echo '<label class="form-label">' . htmlspecialchars($field['str']) . '</label>';
            
            switch($field['single']) {
                case 0: // Single Select
                    echo '<select class="form-control">';
                    $options = explode('|', $field['options'] ?? '');
                    foreach ($options as $option) {
                        echo '<option>' . htmlspecialchars($option) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 1: // Multi Select
                    echo '<select class="form-control" multiple>';
                    $options = explode('|', $field['options'] ?? '');
                    foreach ($options as $option) {
                        echo '<option>' . htmlspecialchars($option) . '</option>';
                    }
                    echo '</select>';
                    break;
                case 2: // Text
                    echo '<input type="text" class="form-control">';
                    break;
                case 3: // Date
                    echo '<input type="date" class="form-control">';
                    break;
                // Add other type renderings as needed
            }
            
            echo '</div></div>';
        }
        $fields_stmt->close();
        ?>
    </div>
</div>
<?php
// End output buffering and return HTML
$preview_html = ob_get_clean();
echo $preview_html;
?> 