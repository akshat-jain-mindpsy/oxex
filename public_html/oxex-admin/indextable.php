<?PHP
include '../OXEXfolder/config.php';
include '../OXEXfolder/u_functions.php';
include 'incl/sess.php';
include __DIR__ . '/incl/admin_vars.php';
// new PHP mailer
//use PHPMailer\PHPMailer\PHPMailer;
//use PHPMailer\PHPMailer\SMTP;
//use PHPMailer\PHPMailer\Exception;
//require '../vendor/autoload.php';
$mailhost = 'xx';
$mailuser = 'xx';
$mailpw = 'xx';
$mailfromemail = 'xx';
$mailfromname = 'xx';

$pagetitle = "Dashboard";
$subtitle = "";

// Set variables needed by adminjs.php
setAdminVars(0); // Dashboard section

if(login_check($pdo) == true && ($admintype == 'AT' || $admintype == 'AO' || $admintype == 'AE' || $admintype == 'SO' || $admintype == 'SE' || $admintype == 'DV')) {
   /*
   This is the home page but also processes updates to the recording date
   */
?><!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
   <meta name="description" content="Bootstrap Admin App">
   <meta name="keywords" content="app, responsive, jquery, bootstrap, dashboard, admin">
   <link rel="icon" type="image/x-icon" href="favicon.ico">
   <title><?php echo $pagetitle ?> - <?php echo $adminname ?></title>
   <?php include 'incl/admincss.php' ?>
   <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
   <!-- Add search box styling -->
   <style>
     .global-search-container {
       position: relative;
       margin-bottom: 20px;
     }
     .global-search-input {
       width: 100%;
       padding: 10px 15px;
       border-radius: 20px;
       border: 1px solid #ddd;
       padding-right: 45px;
       font-size: 16px;
     }
     .global-search-btn {
       position: absolute;
       right: 10px;
       top: 7px;
       background: none;
       border: none;
       color: #666;
       font-size: 18px;
     }
     .results-table th {
       background-color: #f8f9fa;
     }
     .results-table tr:hover {
       background-color: #f1f1f1;
       cursor: pointer;
     }
     .pagination {
       margin-top: 15px;
     }
     .search-no-results {
       padding: 15px;
       text-align: center;
       color: #666;
     }
     .results-type-badge {
       display: inline-block;
       padding: 3px 8px;
       border-radius: 12px;
       font-size: 12px;
       font-weight: bold;
       color: white;
     }
     .badge-table { background-color: #007bff; }
     .badge-field { background-color: #28a745; }
     .badge-section { background-color: #fd7e14; }
     .badge-documentation { background-color: #6f42c1; }
   </style>
</head>

<body>
   <div class="wrapper">
      <!-- top, left side and right navbars -->
      <?php include 'incl/topbar.php' ?>
      <?php include 'incl/sidebar.php' ?>
      <?php include 'incl/offsidebar.php' ?>

      <!-- Main section-->
      <section class="section-container">
         <!-- Page content-->
         <div class="content-wrapper">
            <div class="content-header">
               <div class="content-title"><?php echo $pagetitle ?><small><?php echo $subtitle ?></small></div>
            </div>

            <div class="row">
               <div class="col-xl-12">
                  <!-- START card-->
                  <div class="card border-info">
                     <div class="card-header bg-info">
                        <div class="card-title">Notes</div>
                     </div>
                     <div class="card-body">
                        <p>Documentation for a page in a section can be viewed on any page using the 'Docs' button in the top strip along the page</p>
                        <p>New documentation is added in the Admin section <a href="docs.php">'Admin Documentation'</a> page</p>
                     </div>
                     <div class="card-footer">
</div><!-- END card-->
</div>
            
            <!-- Global Search Section -->
            <div class="row mt-4">
               <div class="col-xl-12">
                  <!-- START card-->
                  <div class="card">
                     <div class="card-header bg-primary text-white">
                        <div class="card-title">Global Search</div>
                     </div>
                     <div class="card-body">
                        <p>Search for sheets, categories, sections, documentation, trainees, trainee groups, and admin users across the system:</p>
                        
                        <!-- Global search container -->
                        <div class="global-search-container">
                           <input type="text" class="global-search-input" placeholder="Type at least 3 characters to search..." id="globalSearchInput">
                           <button class="global-search-btn" id="globalSearchBtn">
                              <i class="fa fa-search"></i>
                           </button>
                        </div>
                        
                        <!-- Results container -->
                        <div id="searchResultsContainer" style="display: none;">
                           <h4 class="mb-3">Search Results <small id="resultsCount"></small></h4>
                           
                           <div class="table-responsive">
                              <table class="table table-striped results-table" id="resultsTable">
                                 <thead>
                                    <tr>
                                       <th>Type</th>
                                       <th>Name</th>
                                       <th>Description</th>
                                    </tr>
                                 </thead>
                                 <tbody id="resultsTableBody">
                                    <!-- Results will be populated here -->
                                 </tbody>
                              </table>
                           </div>
                           
                           <!-- Pagination -->
                           <nav aria-label="Search results pagination">
                              <ul class="pagination justify-content-center" id="resultsPagination">
                                 <!-- Pagination will be populated here -->
                              </ul>
                           </nav>
                        </div>
                        
                        <!-- No results message -->
                        <div id="noResultsMessage" class="search-no-results" style="display: none;">
                           No results found for your search query.
                        </div>
                        
                        <!-- Loading indicator -->
                        <div id="searchLoadingIndicator" class="text-center" style="display: none;">
                           <div class="spinner-border text-primary" role="status">
                              <span class="sr-only">Loading...</span>
                           </div>
                           <p class="mt-2">Searching...</p>
</div>
                  </div><!-- END card-->
</div>
            
            <div class="row">
               <div class="col-xl-12">
                  <h3>&nbsp;</h3>
                  <div class="table-responsive">
</div>
</div>
      </section>
   </div>
   <?php include 'incl/adminjs.php' ?>
   <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
   <style>
   .badge {
     font-size: 0.8em;
     padding: 0.4em 0.6em;
     font-weight: 500;
   }
   
   .results-table .badge {
     min-width: 80px;
     text-align: center;
     display: inline-block;
   }
   
   .results-table tbody tr {
     cursor: pointer;
   }
   
   .results-table tbody tr:hover {
     background-color: #f8f9fa;
   }
   </style>
  <script>
  
  jQuery(document).ready(function($) {
    $( function() {
      $( "#datepicker" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
    });
    $( function() {
      $( "#datepicker2" ).datepicker({
        dateFormat: "dd-mm-yy"
      });
    });
    
    // Global search functionality
    var allResults = [];
    var resultsPerPage = 10;
    var currentPage = 1;
    
    $('#globalSearchInput').on('keyup', function(e) {
      // If Enter key is pressed, trigger search
      if (e.keyCode === 13) {
        $('#globalSearchBtn').click();
      }
      
      // Auto-search after 500ms of typing
      clearTimeout($.data(this, 'timer'));
      var search_string = $(this).val();
      if (search_string.length >= 3) {
        $.data(this, 'timer', setTimeout(function() {
          performSearch(search_string);
        }, 500));
      } else {
        // Hide results containers
        $('#searchResultsContainer, #noResultsMessage').hide();
      }
    });
    
    $('#globalSearchBtn').on('click', function() {
      var query = $('#globalSearchInput').val();
      if (query.length >= 3) {
        performSearch(query);
      }
    });
    
    // Function to perform search
    function performSearch(query) {
      // Show loading indicator
      $('#searchResultsContainer, #noResultsMessage').hide();
      $('#searchLoadingIndicator').show();
      
      $.ajax({
        url: 'ajax/global_search.php',
        type: 'POST',
        data: {
          query: query
        },
        dataType: 'json',
        success: function(response) {
          // Hide loading indicator
          $('#searchLoadingIndicator').hide();
          
          if (response.status === 'success' && response.results.length > 0) {
            // Store all results
            allResults = response.results;
            
            // Reset to first page
            currentPage = 1;
            
            // Display results
            $('#resultsCount').text('(' + response.results.length + ' found)');
            displayResultsPage(currentPage);
            $('#searchResultsContainer').show();
            
          } else {
            // Show no results message
            $('#noResultsMessage').show();
          }
        },
        error: function(xhr, status, error) {
          // Hide loading indicator
          $('#searchLoadingIndicator').hide();
          
          // Show error message
          $('#noResultsMessage').text('An error occurred while searching. Please try again.').show();
        }
      });
    }
    
    // Function to display a page of results
    function displayResultsPage(page) {
      // Calculate start and end indexes
      var startIndex = (page - 1) * resultsPerPage;
      var endIndex = Math.min(startIndex + resultsPerPage, allResults.length);
      
      // Clear results table
      $('#resultsTableBody').empty();
      
      // Add results to table
      for (var i = startIndex; i < endIndex; i++) {
        var result = allResults[i];
        
        // Define specific colors for each type
        var typeColors = {
          'Table': 'badge-primary',
          'Category': 'badge-success', 
          'Category Value': 'badge-info',
          'Section': 'badge-warning',
          'Documentation': 'badge-secondary',
          'Trainee': 'badge-dark',
          'Trainee Group': 'badge-danger',
          'Admin User': 'badge-primary',
          'Blog Post': 'badge-light'
        };
        
        var typeBadgeClass = typeColors[result.type] || 'badge-secondary';
        
        var row = `
          <tr data-url="${result.url}">
            <td><span class="badge ${typeBadgeClass}">${result.type}</span></td>
            <td>${result.title}</td>
            <td>${result.excerpt}</td>
          </tr>
        `;
        
        $('#resultsTableBody').append(row);
      }
      
      // Build pagination
      buildPagination(page, Math.ceil(allResults.length / resultsPerPage));
      
      // Add click handler to rows
      $('#resultsTableBody tr').on('click', function() {
        var url = $(this).data('url');
        window.location.href = url;
      });
    }
    
    // Function to build pagination
    function buildPagination(currentPage, totalPages) {
      var $pagination = $('#resultsPagination');
      $pagination.empty();
      
      // Don't show pagination if there's only one page
      if (totalPages <= 1) {
        return;
      }
      
      // Previous button
      $pagination.append(`
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
          <a class="page-link" href="#" data-page="${currentPage - 1}" aria-label="Previous">
            <span aria-hidden="true">&laquo;</span>
          </a>
        </li>
      `);
      
      // Page numbers
      var startPage = Math.max(1, currentPage - 2);
      var endPage = Math.min(totalPages, startPage + 4);
      
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }
      
      for (var i = startPage; i <= endPage; i++) {
        $pagination.append(`
          <li class="page-item ${i === currentPage ? 'active' : ''}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
          </li>
        `);
      }
      
      // Next button
      $pagination.append(`
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
          <a class="page-link" href="#" data-page="${currentPage + 1}" aria-label="Next">
            <span aria-hidden="true">&raquo;</span>
          </a>
        </li>
      `);
      
      // Add click handler to pagination items
      $('.page-link').on('click', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'));
        
        // Only change page if it's a valid page number
        if (!isNaN(page) && page >= 1 && page <= totalPages) {
          currentPage = page;
          displayResultsPage(currentPage);
        }
      });
    }
  });
  </script>
  
  <!-- DEBUGGING SCRIPT FOR SIDEBAR -->
  <script>
  console.log('=== SIDEBAR DEBUGGING ===');
  console.log('jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
  console.log('Bootstrap version:', typeof $.fn.collapse !== 'undefined' ? 'Bootstrap loaded' : 'Bootstrap collapse not available');

  // Check if collapse elements exist
  $(document).ready(function() {
      console.log('Document ready - checking sidebar elements');
      
      // Check for collapse elements
      var collapseElements = $('[data-toggle="collapse"]');
      console.log('Found collapse elements:', collapseElements.length);
      
      collapseElements.each(function(index) {
          var $this = $(this);
          var target = $this.attr('data-target');
          var targetElement = $(target);
          console.log('Element ' + index + ':', {
              text: $this.text().trim(),
              target: target,
              targetExists: targetElement.length > 0,
              targetClasses: targetElement.attr('class')
          });
      });
      
      // Test manual collapse
      console.log('Testing manual collapse...');
      console.log('Data element before show:', $('#data').attr('class'));
      console.log('Data element visibility:', $('#data').is(':visible'));
      console.log('Data element display:', $('#data').css('display'));
      $('#data').collapse('show');
      console.log('Data element after show:', $('#data').attr('class'));
      console.log('Data element visibility after:', $('#data').is(':visible'));
      console.log('Data element display after:', $('#data').css('display'));
      
      // Test users element
      console.log('Users element classes:', $('#users').attr('class'));
      console.log('Users element visibility:', $('#users').is(':visible'));
      console.log('Users element display:', $('#users').css('display'));
      
      // Test all collapse elements
      $('.collapse').each(function() {
          console.log('Collapse element:', this.id, 'Classes:', this.className);
      });
      
      // Add click handlers for debugging
      $('[data-toggle="collapse"]').on('click', function(e) {
          console.log('Collapse clicked:', $(this).text().trim());
          console.log('Event:', e);
          console.log('Target:', $(this).attr('data-target'));
      });
      
      // Listen for collapse events
      $('.collapse').on('show.bs.collapse', function() {
          console.log('Collapse showing:', this.id);
      });
      
      $('.collapse').on('shown.bs.collapse', function() {
          console.log('Collapse shown:', this.id);
      });
      
      $('.collapse').on('hide.bs.collapse', function() {
          console.log('Collapse hiding:', this.id);
      });
      
      $('.collapse').on('hidden.bs.collapse', function() {
          console.log('Collapse hidden:', this.id);
      });
  });
  </script>
</body>
</html>
<?PHP
} else {
   echo "Not authorised";
}
?>