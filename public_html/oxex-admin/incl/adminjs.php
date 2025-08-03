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
        $tableset = $mysqli->prepare("SELECT sect_title, sect_txt FROM docs_tbl WHERE doc_section = ? AND sort_order != ? ORDER BY sort_order ASC");
        $tableset->bind_param("ii", $whichDocModal, $value0); 
        $tableset->execute();
        $tableset->store_result();
        $tableset->bind_result($sect_title, $sect_txt);
        while ($tableset->fetch()){
          echo "<h5><em>$sect_title</em></h5>";
          echo $sect_txt;
        }
        $numdocs = $tableset->num_rows;
        if ($numdocs == 0) {
          echo "<p><strong>Currently no documentation for this section</strong></p>";
        }
        $tableset->close();
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
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
<!-- =============== APP ===============-->
<script src="assets/js/app.js"></script>
<script>
$(document).ready(function(){
	// this sends messages using Ajax
	$("#msgSubmit").click(function(){
		if ($('#msgEmailAll').is(":checked")) {
			var msgEmailAll = $("#msgEmailAll").val();
		}
		var msgTxt = $("#msgTxt").val(); // the message
		var msgUsr = '<?php echo $usrkey ?>'; // the sender
		<?php 
	     // list all recipients; not developers
			$msgaddons = '';
			$tableset = $mysqli->prepare("SELECT whid FROM who_there WHERE isdev = ?");
			$tableset->bind_param("i", $value0);
			$tableset->execute();
			$tableset->store_result();
			$tableset->bind_result($whid);
			while ($tableset->fetch()){
	     	// put checkbox value in var IF it's checked
	        echo "if ($('#msgEmail$whid').is(\":checked\")) {\r";
			echo "var msgEmail$whid = $(\"#msgEmail$whid\").val();\r}\r";
			// now make up list of form params to add to dataString
	        $msgaddons .= " + '&msgEmail$whid=' + msgEmail$whid ";
	     }
	     $tableset->close();
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
				document.getElementById("messsageForm").reset();
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

<!-- At the end of the file, before closing body tag -->
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>