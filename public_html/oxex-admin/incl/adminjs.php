<!-- Modal for admin documentation-->
<?php
// get title for this section
$whichDocModal = isset($whichDocModal) ? $whichDocModal : 0;
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
  default:
  $section = 'Dashboard';
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
        $value0 = isset($value0) ? $value0 : 0;
        $numdocs = 0;
        
        // Check if database connection is available
        if (isset($supabase_pdo) && $supabase_pdo instanceof PDO) {
          try {
            $tableset = $supabase_pdo->prepare("SELECT sect_title, sect_txt FROM docs_tbl WHERE doc_section = ? AND sort_order != ? ORDER BY sort_order ASC");
            $tableset->execute([$whichDocModal, $value0]);
            
            while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
              $sect_title = $row['sect_title'];
              $sect_txt = $row['sect_txt'];
              echo "<h5><em>$sect_title</em></h5>";
              echo $sect_txt;
              $numdocs++;
            }
          } catch (Exception $e) {
            echo "<p><strong>Error loading documentation: " . htmlspecialchars($e->getMessage()) . "</strong></p>";
            error_log("Modal documentation error: " . $e->getMessage());
          }
        } else {
          echo "<p><strong>Database connection not available</strong></p>";
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
<!-- include summernote css/js -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<!-- Flot (required by app.js chart demos) -->
<script src="assets/vendor/flot/jquery.flot.js"></script>
<script src="assets/vendor/jquery.flot.tooltip/js/jquery.flot.tooltip.js"></script>
<!-- =============== APP ===============-->
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function(){
	// Debug dropdown functionality
	console.log('Admin JS loaded');
	console.log('jQuery version:', $.fn.jquery);
	console.log('Bootstrap dropdown available:', typeof $.fn.dropdown !== 'undefined');
	
	// Check if dropdown elements exist
	var userDropdownToggle = $('#userDropdownToggle');
	var userDropdownMenu = $('#userDropdownMenu');
	var logoutLink = $('#logoutLink');
	
	console.log('User dropdown toggle found:', userDropdownToggle.length);
	console.log('User dropdown menu found:', userDropdownMenu.length);
	console.log('Logout link found:', logoutLink.length);
	
	// Manual dropdown toggle for debugging
	userDropdownToggle.on('click', function(e) {
		console.log('User dropdown clicked');
		e.preventDefault();
		e.stopPropagation();
		
		// Toggle dropdown manually
		userDropdownMenu.toggleClass('show');
	});
	
	// Test logout link
	logoutLink.on('click', function(e) {
		console.log('Logout link clicked');
		// Let the default action proceed
	});
	
	// Close dropdown when clicking outside
	$(document).on('click', function(e) {
		if (!$(e.target).closest('.dropdown').length) {
			userDropdownMenu.removeClass('show');
		}
	});
	
	// Ensure all modals are direct children of <body> to avoid clipping by transformed/overflowed parents
	try {
		$('.modal').each(function(){
			if (!$(this).parent().is('body')) {
				$(this).appendTo('body');
			}
		});
	} catch (e) { /* noop */ }
	// this sends messages using Ajax
	$("#msgSubmit").click(function(){
		var msgEmailAll = '';
		if ($('#msgEmailAll').is(":checked")) {
			msgEmailAll = $("#msgEmailAll").val();
		}
		var msgTxt = $("#msgTxt").val(); // the message
		var msgUsr = '<?php echo $usrkey ?>'; // the sender
		<?php 
	     // list all recipients; not developers
			$msgaddons = '';
			if (isset($supabase_pdo) && $supabase_pdo instanceof PDO) {
				try {
					$tableset = $supabase_pdo->prepare("SELECT whid FROM who_there WHERE isdev = ?");
					$tableset->execute([$value0]);
					while ($row = $tableset->fetch(PDO::FETCH_ASSOC)){
						$whid = $row['whid'];
			     	// put checkbox value in var IF it's checked
			        echo "if ($('#msgEmail$whid').is(\":checked\")) {\r";
					echo "var msgEmail$whid = $(\"#msgEmail$whid\").val();\r}\r";
					// now make up list of form params to add to dataString
			        $msgaddons .= " + '&msgEmail$whid=' + msgEmail$whid ";
			     }
				} catch (Exception $e) {
					error_log("Modal messaging error: " . $e->getMessage());
				}
			}
	     ?>
		var dataString = 'msgEmailAll='+ msgEmailAll <?php echo $msgaddons ?> + '&msgTxt='+ msgTxt + '&msgUsr='+ msgUsr;
		if(msgTxt == '')
		{
			alert("Please select a recipient and type a message");
		}
		else
		{
			// AJAX Code To Submit Form.
			$.ajax({
			type: "POST",
			url: "messagehandler.php",
			data: dataString,
			cache: false,
			success: function(result){
				document.getElementById("messageMsg").innerHTML = result;
				document.getElementById("messageForm").reset();
			}
			});
		}
		return false;
	});
});
/* https://github.com/DiemenDesign/summernote-cleaner */
(function (factory) {
  if (typeof define === 'function' && define.amd) {
    define(['jquery'], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory(require('jquery'));
  } else {
    factory(window.jQuery);
  }
}
(function ($) {
  $.extend(true, $.summernote.lang, {
    'en-US': {
      cleaner: {
        tooltip: 'Cleaner',
        not: 'Text has been Cleaned!!!',
        limitText: 'Text',
        limitHTML: 'HTML'
      }
    }
  });
  $.extend($.summernote.options, {
    cleaner: {
      action: 'both', // both|button|paste 'button' only cleans via toolbar button, 'paste' only clean when pasting content, both does both options.
      newline: '<br>', // Summernote's default is to use '<p><br></p>'
      notStyle: 'position:absolute;top:0;left:0;right:0',
      icon: '<i class="note-icon"><svg xmlns="http://www.w3.org/2000/svg" id="libre-paintbrush" viewBox="0 0 14 14" width="14" height="14"><path d="m 11.821425,1 q 0.46875,0 0.82031,0.311384 0.35157,0.311384 0.35157,0.780134 0,0.421875 -0.30134,1.01116 -2.22322,4.212054 -3.11384,5.035715 -0.64956,0.609375 -1.45982,0.609375 -0.84375,0 -1.44978,-0.61942 -0.60603,-0.61942 -0.60603,-1.469866 0,-0.857143 0.61608,-1.419643 l 4.27232,-3.877232 Q 11.345985,1 11.821425,1 z m -6.08705,6.924107 q 0.26116,0.508928 0.71317,0.870536 0.45201,0.361607 1.00781,0.508928 l 0.007,0.475447 q 0.0268,1.426339 -0.86719,2.32366 Q 5.700895,13 4.261155,13 q -0.82366,0 -1.45982,-0.311384 -0.63616,-0.311384 -1.0212,-0.853795 -0.38505,-0.54241 -0.57924,-1.225446 -0.1942,-0.683036 -0.1942,-1.473214 0.0469,0.03348 0.27455,0.200893 0.22768,0.16741 0.41518,0.29799 0.1875,0.130581 0.39509,0.24442 0.20759,0.113839 0.30804,0.113839 0.27455,0 0.3683,-0.247767 0.16741,-0.441965 0.38505,-0.753349 0.21763,-0.311383 0.4654,-0.508928 0.24776,-0.197545 0.58928,-0.31808 0.34152,-0.120536 0.68974,-0.170759 0.34821,-0.05022 0.83705,-0.07031 z"/></svg></i>',
      keepHtml: true, //Remove all Html formats
      keepOnlyTags: [], // If keepHtml is true, remove all tags except these
      keepClasses: false, //Remove Classes
      badTags: ['style', 'script', 'applet', 'embed', 'noframes', 'noscript', 'html'], //Remove full tags with contents
      badAttributes: ['style', 'start'], //Remove attributes from remaining tags
      limitChars: 0, // 0|# 0 disables option
      limitDisplay: 'both', // none|text|html|both
      limitStop: false // true/false
    }
  });
  $.extend($.summernote.plugins, {
    'cleaner': function (context) {
      var self = this,
            ui = $.summernote.ui,
         $note = context.layoutInfo.note,
       $editor = context.layoutInfo.editor,
       options = context.options,
          lang = options.langInfo;
      var cleanText = function (txt, nlO) {
        var out = txt;
        if (!options.cleaner.keepClasses) {
          var sS = /(\n|\r| class=(")?Mso[a-zA-Z]+(")?)/g;
             out = txt.replace(sS, ' ');
        }
        var nL = /(\n)+/g;
           out = out.replace(nL, nlO);
        if (options.cleaner.keepHtml) {
          var cS = new RegExp('<!--(.*?)-->', 'gi');
             out = out.replace(cS, '');
          var tS = new RegExp('<(/)*(meta|link|\\?xml:|st1:|o:|font)(.*?)>', 'gi');
             out = out.replace(tS, '');
          var bT = options.cleaner.badTags;
          for (var i = 0; i < bT.length; i++) {
            tS = new RegExp('<' + bT[i] + '\\b.*>.*</' + bT[i] + '>', 'gi');
            out = out.replace(tS, '');
          }
          var allowedTags = options.cleaner.keepOnlyTags;
          if (typeof(allowedTags) == "undefined") allowedTags = [];
          if (allowedTags.length > 0) {
            allowedTags = (((allowedTags||'') + '').toLowerCase().match(/<[a-z][a-z0-9]*>/g) || []).join('');
               var tags = /<\/?([a-z][a-z0-9]*)\b[^>]*>/gi;
                    out = out.replace(tags, function($0, $1) {
              return allowedTags.indexOf('<' + $1.toLowerCase() + '>') > -1 ? $0 : ''
            });
          }
          var bA = options.cleaner.badAttributes;
          for (var ii = 0; ii < bA.length; ii++ ) {
            //var aS=new RegExp(' ('+bA[ii]+'="(.*?)")|('+bA[ii]+'=\'(.*?)\')', 'gi');
            var aS = new RegExp(' ' + bA[ii] + '=[\'|"](.*?)[\'|"]', 'gi');
               out = out.replace(aS, '');
               aS = new RegExp(' ' + bA[ii] + '[=0-9a-z]', 'gi');
               out = out.replace(aS, '');
          }
        }
        return out;
      };
      if (options.cleaner.action == 'both' || options.cleaner.action == 'button') {
        context.memo('button.cleaner', function () {
          var button = ui.button({
            contents: options.cleaner.icon,
            tooltip: lang.cleaner.tooltip,
            container: 'body',
            click: function () {
              if ($note.summernote('createRange').toString())
                $note.summernote('pasteHTML', $note.summernote('createRange').toString());
              else
                $note.summernote('code', cleanText($note.summernote('code')));
              if ($editor.find('.note-status-output').length > 0)
                $editor.find('.note-status-output').html('<div class="alert alert-success">' + lang.cleaner.not + '</div>');
            }
          });
          return button.render();
        });
      }
      this.events = {
        'summernote.init': function () {
          if ($.summernote.interface === 'lite') {
            $("head").append('<style>.note-statusbar .pull-right{float:right!important}.note-status-output .text-muted{color:#777}.note-status-output .text-primary{color:#286090}.note-status-output .text-success{color:#3c763d}.note-status-output .text-info{color:#31708f}.note-status-output .text-warning{color:#8a6d3b}.note-status-output .text-danger{color:#a94442}.alert{margin:-7px 0 0 0;padding:7px 10px;border:1px solid transparent;border-radius:0}.alert .note-icon{margin-right:5px}.alert-success{color:#3c763d!important;background-color: #dff0d8 !important;border-color:#d6e9c6}.alert-info{color:#31708f;background-color:#d9edf7;border-color:#bce8f1}.alert-warning{color:#8a6d3b;background-color:#fcf8e3;border-color:#faebcc}.alert-danger{color:#a94442;background-color:#f2dede;border-color:#ebccd1}</style>');
          }
          if (options.cleaner.limitChars != 0 || options.cleaner.limitDisplay != 'none') {
            var textLength = $editor.find(".note-editable").text().replace(/(<([^>]+)>)/ig, "").replace(/( )/, " ");
            var codeLength = $editor.find('.note-editable').html();
            var lengthStatus = '';
            if (textLength.length > options.cleaner.limitChars && options.cleaner.limitChars > 0)
              lengthStatus += 'text-danger">';
            else
              lengthStatus += '">';
            if (options.cleaner.limitDisplay == 'text' || options.cleaner.limitDisplay == 'both') lengthStatus += lang.cleaner.limitText + ': ' + textLength.length;
            if (options.cleaner.limitDisplay == 'both') lengthStatus += ' / ';
            if (options.cleaner.limitDisplay == 'html' || options.cleaner.limitDisplay == 'both') lengthStatus += lang.cleaner.limitHTML + ': ' + codeLength.length;
            $editor.find('.note-status-output').html('<small class="pull-right ' + lengthStatus + '&nbsp;</small>');
          }
        },
        'summernote.keydown': function (we, e) {
          if (options.cleaner.limitChars != 0 || options.cleaner.limitDisplay != 'none') {
            var textLength =  $editor.find(".note-editable").text().replace(/(<([^>]+)>)/ig, "").replace(/( )/, " ");
            var codeLength =  $editor.find('.note-editable').html();
            var lengthStatus = '';
            if (options.cleaner.limitStop == true && textLength.length >= options.cleaner.limitChars) {
              var key = e.keyCode;
              allowed_keys = [8, 37, 38, 39, 40, 46]
              if ($.inArray(key, allowed_keys) != -1) {
                $editor.find('.cleanerLimit').removeClass('text-danger');
                return true;
              } else {
                $editor.find('.cleanerLimit').addClass('text-danger');
                e.preventDefault();
                e.stopPropagation();
              }
            } else {
              if (textLength.length > options.cleaner.limitChars && options.cleaner.limitChars > 0)
                lengthStatus += 'text-danger">';
              else
                lengthStatus += '">';
              if (options.cleaner.limitDisplay == 'text' || options.cleaner.limitDisplay == 'both')
                lengthStatus += lang.cleaner.limitText + ': ' + textLength.length;
              if (options.cleaner.limitDisplay == 'both')
                lengthStatus += ' / ';
              if (options.cleaner.limitDisplay == 'html' || options.cleaner.limitDisplay == 'both')
                lengthStatus += lang.cleaner.limitHTML + ': ' + codeLength.length;
              $editor.find('.note-status-output').html('<small class="cleanerLimit pull-right ' + lengthStatus + '&nbsp;</small>');
            }
          }
        },
        'summernote.paste': function (we, e) {
          if (options.cleaner.action == 'both' || options.cleaner.action == 'paste') {
            e.preventDefault();
            var ua   = window.navigator.userAgent;
            var msie = ua.indexOf("MSIE ");
                msie = msie > 0 || !!navigator.userAgent.match(/Trident.*rv\:11\./);
            var ffox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;
            if (msie)
              var text = window.clipboardData.getData("Text");
            else
              var text = e.originalEvent.clipboardData.getData(options.cleaner.keepHtml ? 'text/html' : 'text/plain');
            if (text) {
              if (msie || ffox)
                setTimeout(function () {
                  $note.summernote('pasteHTML', cleanText(text, options.cleaner.newline));
                }, 1);
              else
                $note.summernote('pasteHTML', cleanText(text, options.cleaner.newline));
              if ($editor.find('.note-status-output').length > 0)
                $editor.find('.note-status-output').html('<div class="summernote-cleanerAlert alert alert-success">' + lang.cleaner.not + '</div>');
            }
          }
        }
      }
    }
  });
}));
</script>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- Modal Fix CSS -->
<style>
/* Ensure modal is visible above any app chrome */
.modal {
    z-index: 10050 !important;
}

.modal-backdrop {
    z-index: 10040 !important;
}

/* While a modal is open, drop nav/sidebars below it */
.modal-open .topnavbar,
.modal-open .wrapper .aside-container,
.modal-open .offsidebar {
    z-index: 10 !important;
}

/* Fix dropdowns inside modals (Bootstrap dropdown & Select2) */
.modal .dropdown-menu {
    z-index: 10060 !important;
}

.select2-container--open {
    z-index: 10060 !important;
}

.modal { /* avoid clipping dropdowns */
    overflow: visible !important;
}

/* Fix for modal positioning */
.modal-dialog {
    margin: 1.75rem auto;
}

/* Ensure modal content is visible */
.modal-content {
    position: relative;
    z-index: 1051;
}

/* Debug: Make sure modal is not hidden */
#docsModal {
    display: none !important; /* Bootstrap default - will be overridden by JS */
}

#docsModal.show {
    display: block !important;
}

/* Ensure dropdown is visible when show class is added */
.dropdown-menu.show {
    display: block !important;
}

/* Debug: Make dropdown more visible */
#userDropdownMenu {
    background-color: #fff;
    border: 1px solid #ccc;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
}

#userDropdownMenu .dropdown-item {
    padding: 8px 16px;
    color: #333;
}

#userDropdownMenu .dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<!-- At the end of the file, before closing body tag -->
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- Modal Debug Script -->
<script>
$(document).ready(function() {
    // Debug modal functionality
    console.log('Modal debug script loaded');
    console.log('Docs modal element:', $('#docsModal').length);
    console.log('Docs button element:', $('[data-target="#docsModal"]').length);
    
    // Test modal show functionality
    $('[data-target="#docsModal"]').on('click', function(e) {
        console.log('Docs button clicked!');
        e.preventDefault();
        
        // Check if modal exists
        if ($('#docsModal').length > 0) {
            console.log('Modal found, attempting to show...');
            $('#docsModal').modal('show');
        } else {
            console.error('Modal not found!');
        }
    });
    
    // Listen for modal events
    $('#docsModal').on('show.bs.modal', function () {
        console.log('Modal is about to show');
    });
    
    $('#docsModal').on('shown.bs.modal', function () {
        console.log('Modal is now shown');
    });
    
    $('#docsModal').on('hide.bs.modal', function () {
        console.log('Modal is about to hide');
    });
    
    $('#docsModal').on('hidden.bs.modal', function () {
        console.log('Modal is now hidden');
    });
});
</script>