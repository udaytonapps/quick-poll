<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

// Reset selection for the active poll
if ($USER->instructor) {
    $_SESSION["response"] = false;
} else {
    $currentActivePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
    $deleteResponse = $PDOX->prepare("DELETE FROM {$p}qp_response WHERE poll_id = :pollId AND user_id = :userId");
    $deleteResponse->execute(array(
        ":pollId" => $currentActivePollId,
        ":userId" => $USER->id
    ));
}
$_SESSION["success"] = "Selection has been reset.";
if ($USER->instructor) {
    header('Location: ' . addSession('student-home.php?p='.$_GET["p"]));
} else {
    header('Location: ' . addSession('index.php'));
}
return;