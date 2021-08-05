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
    $_SESSION["error"] = "Unable to reset responses for unknown poll.";
} else {
    $deleteResponsesStmt = $PDOX->prepare("DELETE FROM {$p}qp_response WHERE poll_id = :pollId");
    $deleteResponsesStmt->execute(array(
        ":pollId" => $_GET["p"]
    ));
}
$_SESSION["success"] = "Poll responses successfully reset.";
if (isset($_GET["back"]) && $_GET["back"] == 'edit') {
    header('Location: ' . addSession('editPoll.php?p='.$_GET["p"]));
} else {
    header('Location: ' . addSession('results.php?p='.$_GET["p"]));
}
return;