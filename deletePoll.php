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
    $_SESSION["error"] = "Unable to delete unknown poll.";
} else {
    $currentActivePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
    if ($currentActivePollId == $_GET["p"]) {
        // Poll was already the active one so set to inactive
        $LAUNCH->link->settingsSet("active-poll-id", false);
    }
    $deletePollStmt = $PDOX->prepare("DELETE FROM {$p}qp_poll WHERE poll_id = :pollId");
    $deletePollStmt->execute(array(
        ":pollId" => $_GET["p"]
    ));
}
$_SESSION["success"] = "Poll successfully deleted.";
header('Location: ' . addSession('build.php'));
return;