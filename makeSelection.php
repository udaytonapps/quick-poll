<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;
use \Tsugi\UI\SettingsForm;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

if (!isset($_GET["p"]) || $_GET["p"] == '' || !isset($_GET["c"]) || $_GET["c"] == '') {
    $_SESSION["error"] = "Unable to make selection for unknown poll or choice.";
} else {
    // Make sure poll is active or instructor
    $currentActivePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
    if ($USER->instructor || $currentActivePollId == $_GET["p"]) {
        // Current time for modification
        $currentTime = new DateTime('now', new DateTimeZone($CFG->timezone));
        $currentTime = $currentTime->format("Y-m-d H:i:s");

        if ($USER->instructor) {
            $_SESSION["response"] = array(
                "poll_id" => $_GET["p"],
                "choice_id" => $_GET["c"],
                "user_id" => $USER->id,
                "sort_name" => $USER->lastname.', '.$USER->firstname,
                "modified" => $currentTime
            );
        } else {
            // Check for existing response
            $currentResponseStmt = $PDOX->prepare("SELECT * FROM {$p}qp_response WHERE poll_id = :pollId AND user_id = :userId");
            $currentResponseStmt->execute(array(":pollId" => $_GET["p"], ":userId" => $USER->id));
            $currentResponse = $currentResponseStmt->fetch(PDO::FETCH_ASSOC);
            if (!$currentResponse) {
                // New selection so insert
                $newResponseStmt = $PDOX->prepare("INSERT INTO {$p}qp_response (
                poll_id, 
                choice_id, 
                user_id,
                sort_name,
                modified
            )
            values (
             :pollId, 
             :choiceId, 
             :userId, 
             :sortName,
             :modified
            )");
                $newResponseStmt->execute(array(
                    ":pollId" => $_GET["p"],
                    ":choiceId" => $_GET["c"],
                    ":userId" => $USER->id,
                    ":sortName" => $USER->lastname.', '.$USER->firstname,
                    ":modified" => $currentTime
                ));
            } else {
                // Already made a selection so update
                $updateResponseStmt = $PDOX->prepare("UPDATE {$p}qp_response SET choice_id = :choiceId, modified = :modified WHERE poll_id = :pollId AND user_id = :userId");
                $updateResponseStmt->execute(array(
                    ":pollId" => $_GET["p"],
                    ":choiceId" => $_GET["c"],
                    ":userId" => $USER->id,
                    ":sortName" => $USER->lastname.', '.$USER->firstname,
                    ":modified" => $currentTime
                ));
            }
        }
        $_SESSION["success"] = "Response saved successfully.";
    } else {
        $_SESSION["error"] = "You may not make selection changes to an inactive poll.";
    }
}
if ($USER->instructor) {
    header('Location: ' . addSession('student-home.php?p='.$_GET["p"]));
} else {
    header('Location: ' . addSession('index.php'));
}
return;