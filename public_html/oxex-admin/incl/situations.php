<?php
$situationtxt = '';
$passtext = '';
$howmanyans = 0;
$howmanyvals = 0; // Initialize howmanyvals
$allpass = 0; // Initialize allpass
// this file useed in account.php & oxex-admin/traineedetail.php
// sets if a trainee has passed a field and sets marks

// Before including situations.php, ensure $valueb and $tothrs are defined
$valueb = 0; // Set a default value for valueb
$tothrs = 0; // Set a default value for tothrs


if ($situation == 0) {
   $situationtxt = "<span class=\"badge badge-info\">Info only</span>";
   $passtext = "<span class=\"badge badge-success ml-3\">No pass requirement</span>";
   $allpass = $allpass + 1; # count effectively passed
}

if ($situation == 1) {
   $anyflag = 0;
   $situationtxt = "<span class=\"badge badge-primary\">Any value</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   // we're looking for any values
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a value
         $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
         $anyflag = 1; # so we know just this one passed
      }
   }
   if ($anyflag == 1) {
      $allpass = $allpass + 1; # count passed
   }
}

if ($situation == 2) {
   $situationtxt = "<span class=\"badge badge-purple\">Some of each</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   // must be something for each value
   // how many values are we expecting?
   $howmanyans = 0;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   if ($howmanyvals == $howmanyans) {
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }

}

if ($situation == 5) {
   $situationtxt = "<span class=\"badge badge-green\">Good range (&gt; half)</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $howmanyans = 0;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   $halfvals = $howmanyvals / 2; # a number half the number of values
   if ($howmanyans >= $halfvals) {
      // have we answers for at least half the values
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }
}

if ($situation == 7) {
   // check total time $tothrs against $valueb
   $situationtxt = "<span class=\"badge badge-pink\">Min $valueb hours (".number_format($tothrs,3).")</span>";
   if ($tothrs >= $valueb) {
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
         $allpass = $allpass + 1; # count passed
   } else {
      $passtext = "<span class=\"badge badge-danger ml-3\">Not Passed</span>";
   }
}

// Next set: A value in at least X columns in graph
if ($situation == 3) {
   $situationtxt = "<span class=\"badge badge-purple\">At least 2 values</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $atleast2 = 2;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   if ($howmanyans >= $atleast2) {
      // have we answers for at least 2 values
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }
}
if ($situation == 10) {
   $situationtxt = "<span class=\"badge badge-purple\">At least 3 values</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $atleast3 = 3;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   if ($howmanyans >= $atleast3) {
      // have we answers for at least 3 values
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }
}
if ($situation == 12) {
   $situationtxt = "<span class=\"badge badge-purple\">At least 4 values</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $atleast4 = 4;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   if ($howmanyans >= $atleast4) {
      // have we answers for at least 4 values
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }
}
if ($situation == 8) {
   $situationtxt = "<span class=\"badge badge-purple\">At least 6 values</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $atleast6 = 6;
   foreach ($ansarr as $ans) {
      if ($ans > 0) {
         // there is a non-zero answer
         $howmanyans++;
      }
   }
   if ($howmanyans >= $atleast6) {
      // have we answers for at least 6 values
      $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
      $allpass = $allpass + 1; # count passed
   }
}


// Next set: more than X results in total
if ($situation == 11) {
   $situationtxt = "<span class=\"badge badge-pink\">&ge; 4 results</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $howmanyans = 0;
   foreach ($ansarr as $ans) {
      if ($ans > 4) {
         // there are more than four results for a value
         $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
         $allpass = $allpass + 1; # count passed
      }
   }
}


if ($situation == 6) {
   $situationtxt = "<span class=\"badge badge-pink\">&ge; 6 results</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $howmanyans = 0;
   foreach ($ansarr as $ans) {
      if ($ans > 6) {
         // there are more than six results for a value
         $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
         $allpass = $allpass + 1; # count passed
      }
   }
}

if ($situation == 9) {
   $situationtxt = "<span class=\"badge badge-pink\">&ge; 8 results</span>";
   $passtext = "<span class=\"badge badge-danger ml-3\">Not passed</span>";
   $howmanyans = 0;
   foreach ($ansarr as $ans) {
      if ($ans > 8) {
         // there are more than eight results for a value
         $passtext = "<span class=\"badge badge-success ml-3\">Passed</span>";
         $allpass = $allpass + 1; # count passed
      }
   }
}
?>