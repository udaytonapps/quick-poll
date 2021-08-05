<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

// Get main

if ( $USER->instructor ) {

    header( 'Location: '.addSession('build.php') ) ;
} else { // student
    header( 'Location: '.addSession('student-home.php') ) ;
}
return;