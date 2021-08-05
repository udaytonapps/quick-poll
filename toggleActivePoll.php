<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
    return;
}

if (!isset($_GET["p"]) || $_GET["p"] == '') {
    $_SESSION["error"] = "Unable to activate unknown poll.";
} else {
    $currentActivePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
    if ($currentActivePollId == $_GET["p"]) {
        // Poll was already the active one so set to inactive
        $LAUNCH->link->settingsSet("active-poll-id", false);
    } else {
        $LAUNCH->link->settingsSet("active-poll-id", $_GET["p"]);
    }
}
header('Location: ' . addSession('build.php'));
return;