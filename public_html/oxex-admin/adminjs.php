<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>OXEX Admin Panel</title>
    
    <!-- Load jQuery first before other scripts that depend on it -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.24/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.7/css/responsive.dataTables.min.css">
    
    <!-- Other Bootstrap JS components after jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <!-- DataTables JS after jQuery -->
    <script type="text/javascript" src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.7/js/dataTables.responsive.min.js"></script>
    
    <!-- Session timeout handler -->
    <script>
    $(document).ready(function() {
        // Check for session timeout errors
        function checkSessionTimeout() {
            if (window.location.href.indexOf('error=session_incomplete') > -1) {
                window.location.href = '../../public_html/login.php';
            }
        }
        
        // Run on page load
        checkSessionTimeout();
        
        // Add global AJAX error handler for 403 status (session expired)
        $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            if (jqXHR.status === 403) {
                window.location.href = '../../public_html/login.php';
            }
        });
    });
    </script>
    
    <style>
        /* Custom styles */
        .doc-section {
            display: none;
            padding: 15px;
        }
        .doc-section.active {
            display: block;
        }
        .doc-nav {
            background-color: #f8f9fa;
            padding: 15px;
        }
        .doc-nav .list-group-item {
            cursor: pointer;
        }
        .doc-nav .list-group-item.active {
            background-color: #007bff;
            border-color: #007bff;
        }
    </style>
</head>
<body>
// ... existing code ... 