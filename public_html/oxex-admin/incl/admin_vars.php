<?php
/**
 * Admin Variables Setup
 * Sets variables needed by adminjs.php for documentation modal
 */

function setAdminVars($docModal = 0) {
    global $whichDocModal, $value0;
    
    // Set the documentation modal section
    $whichDocModal = $docModal;
    
    // Set default value for value0 (used in database queries)
    $value0 = isset($value0) ? $value0 : 0;
}

// Initialize default values if not already set
if (!isset($whichDocModal)) {
    $whichDocModal = 0;
}
if (!isset($value0)) {
    $value0 = 0;
}
?>
