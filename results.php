<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
    return;
}

$p = $CFG->dbprefix;

$OUTPUT->header();

include("tool-header.html");

$OUTPUT->bodyStart();

$resultsPollId = $_GET["p"] ?? false;
if (!$resultsPollId) {
    $_SESSION["error"] = "Unable to load results for unknown poll.";
    header('Location: ' . addSession('build.php'));
    return;
} else {
    $activePollStmt = $PDOX->prepare("SELECT * FROM {$p}qp_poll WHERE poll_id = :pollId");
    $activePollStmt->execute(array(":pollId" => $resultsPollId));
    $activePoll = $activePollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$activePoll) {
        echo '<p><em>Error: Unable to locate active poll. Please contact your instructor.</em></p>';
    } else {
        // Get choices for active poll
        $activePollChoicesStmt = $PDOX->prepare("SELECT * FROM {$p}qp_choice WHERE poll_id = :pollId order by choice_order");
        $activePollChoicesStmt->execute(array(":pollId" => $resultsPollId));
        $activeChoices = $activePollChoicesStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <a href="build.php" class="btn btn-link pull-right" style="clear:both;">Exit Results View <span class="fas fa-sign-out-alt" aria-hidden="true"></span></a>
        <h3 class="text-muted" style="margin-top:0.5rem;margin-bottom:0.5rem;font-weight:300">Quick Poll Results</h3>
        <div class="h4" style="font-weight:400;">
            <?=$activePoll["question_text"]?>
        </div>
        <?php
        $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId");
        $totalStmt->execute(array(":pollId" => $resultsPollId));
        $pollStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
        if ($activeChoices && count($activeChoices) > 0) {
            echo '<div class="list-group" style="margin-bottom:1rem;">';
            foreach($activeChoices as $choice) {
                // Get total responses
                $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId AND choice_id = :choiceId");
                $totalStmt->execute(array(":pollId" => $resultsPollId, ":choiceId" => $choice["choice_id"]));
                $responseStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
                $percentOfTotal = $pollStats["total"] == 0 ? 0 : 100.0 * $responseStats["total"] / $pollStats["total"];
                ?>
                <div class="list-group-item">
                    <?=$choice["choice_text"]?>
                    <span class="badge"><?=$responseStats["total"]?></span>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" aria-valuenow="<?=$percentOfTotal?>" aria-valuemin="0" aria-valuemax="100" style="width:<?=$percentOfTotal?>%">
                        </div>
                    </div>
                        <?php
                        $studentStmt = $PDOX->prepare("SELECT * FROM {$p}qp_response WHERE poll_id = :pollId AND choice_id = :choiceId ORDER BY sort_name");
                        $studentStmt->execute(array(":pollId" => $resultsPollId, ":choiceId" => $choice["choice_id"]));
                        $studentList = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
                        if ($studentList && count($studentList) > 0) {
                            echo '<ul class="list-group">';
                            foreach($studentList as $student) {
                                $responseDate = new DateTime($student["modified"]);
                                $formattedResponseDate = $responseDate->format("m/d/y")." at ".$responseDate->format("h:i A");
                                echo '<li class="list-group-item list-group-item-info"><span class="pull-right text-muted">'.$formattedResponseDate.'</span>'.$student["sort_name"].'</li>';
                            }
                            echo '</ul>';
                        }
                        ?>
                </div>
                <?php
            }
            echo '</div>';
        }
        echo '<p class="text-right">'.$pollStats["total"].' total responses</p>';
        echo '<hr><p class="text-center"><a href="resetResponses.php?p='.$resultsPollId.'&back=results" class="text-danger" onclick="return confirm(\'Are you sure you want to reset the results for this poll? All existing student responses will be deleted and are not recoverable.\');"><span class="fa fa-trash-o" aria-hidden="true"></span> Reset All Responses</a></p>';
    }
}

$OUTPUT->footerStart();

$OUTPUT->footerEnd();
