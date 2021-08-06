<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

include("menu.php");

$OUTPUT->header();

include("tool-header.html");

$OUTPUT->bodyStart();

$previewPoll = $_GET["p"] ?? false;

// Get active poll
$activePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
if (!$previewPoll && !$activePollId) {
    if ($USER->instructor) {
        echo '<p class="text-right" style="padding:0.5rem;"><a href="build.php" class="h4 pull-right" style="color:#fff;">Exit Student View <span class="fas fa-sign-out-alt" aria-hidden="true"></span></a></p>';
    }
    $OUTPUT->splashPage("Quick Poll", "There is no active poll at this time.");
} else {
    if ($USER->instructor) {
        echo '<a href="build.php" class="btn btn-link pull-right" style="clear:both;">Exit Student View <span class="fas fa-sign-out-alt" aria-hidden="true"></span></a>';
    }
    // If preview and instructor set active poll to req. param.
    if ($USER->instructor && $previewPoll) {
        $activePollId = $previewPoll;
    }
    $activePollStmt = $PDOX->prepare("SELECT * FROM {$p}qp_poll WHERE poll_id = :pollId");
    $activePollStmt->execute(array(":pollId" => $activePollId));
    $activePoll = $activePollStmt->fetch(PDO::FETCH_ASSOC);
    if (!$activePoll) {
        echo '<p><em>Error: Unable to locate active poll. Please contact your instructor.</em></p>';
    } else {
        if ($activePoll["anonymous"] == 1) {
            ?>
            <div class="pull-right"><h5 class="text-muted"><span class="fa fa-eye-slash" aria-hidden="true"></span> Anonymous</h5></div>
            <?php
        }
        ?>
        <h3 class="text-muted" style="margin-top:0.5rem;margin-bottom:0.5rem;font-weight:300">Quick Poll</h3>
        <?php
        if ($USER->instructor) {
            // Add warning that response is for preview only
            echo '<p class="alert alert-warning"><strong>Preview Mode:</strong> Your response will not be saved after your session ends and does not count towards the actual results.</p>';
        }
        ?>
        <div class="h4" style="font-weight:400;">
            <?=$activePoll["question_text"]?>
        </div>
        <?php
        // Get choices for active poll
        $activePollChoicesStmt = $PDOX->prepare("SELECT * FROM {$p}qp_choice WHERE poll_id = :pollId order by choice_order");
        $activePollChoicesStmt->execute(array(":pollId" => $activePollId));
        $activeChoices = $activePollChoicesStmt->fetchAll(PDO::FETCH_ASSOC);
        // Get current user's selection
        // If instructor get from session otherwise get from database
        if ($USER->instructor) {
            $response = $_SESSION["response"] ?? false;
            // If response in session is for wrong poll remove
            if ($response && $response["poll_id"] != $activePollId) {
                $_SESSION["response"] = false;
                $response = false;
            }
        } else {
            $responseStmt = $PDOX->prepare("SELECT * FROM {$p}qp_response WHERE poll_id = :pollId AND user_id = :userId");
            $responseStmt->execute(array(":pollId" => $activePollId, ":userId" => $USER->id));
            $response = $responseStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$response) {
            // Current user has not responded to the poll yet.
            if ($activeChoices && count($activeChoices) > 0) {
                echo '<div class="list-group" style="margin-bottom:1rem;">';
                foreach($activeChoices as $choice) {
                    echo '<a href="makeSelection.php?p='.$activePollId.'&c='.$choice["choice_id"].'" class="list-group-item flx-cntnr h5" style="font-weight:400"><div class="flx-grow-all">'.$choice["choice_text"].'</div></a>';
                }
                echo '</div>';
            }
        } else {
            // Student already responded. Show results if allowed
            if ($activePoll["hidesummary"] == 1) {
                if ($activeChoices && count($activeChoices) > 0) {
                    echo '<div class="list-group" style="margin-bottom:1rem;">';
                    foreach ($activeChoices as $choice) {
                        if ($choice["choice_id"] == $response["choice_id"]) {
                            // Make selected
                            ?>
                            <div class="list-group-item list-group-item-info">
                                <strong><?=$choice["choice_text"]?></strong>
                                <div class="text-center"><span class="fa fa-check-circle-o" aria-hidden="true"></span> Selected</div>
                            </div>
                            <?php
                            echo '';
                        } else {
                            ?>
                            <div class="list-group-item">
                                <?=$choice["choice_text"]?>
                            </div>
                            <?php
                        }
                    }
                    echo '</div>';
                }
            } else {
                $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId");
                $totalStmt->execute(array(":pollId" => $activePollId));
                $pollStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
                if ($USER->instructor) {
                    // Add one to response total
                    $pollStats["total"]++;
                }
                if ($activeChoices && count($activeChoices) > 0) {
                    echo '<div class="list-group" style="margin-bottom:1rem;">';
                    foreach ($activeChoices as $choice) {
                        // Get total responses
                        $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId AND choice_id = :choiceId");
                        $totalStmt->execute(array(":pollId" => $activePollId, ":choiceId" => $choice["choice_id"]));
                        $responseStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
                        if ($USER->instructor && $choice["choice_id"] == $response["choice_id"]) {
                            // Add one to total because instructor response is not in the db
                            $responseStats["total"]++;
                        }
                        $percentOfTotal = $pollStats["total"] == 0 ? 0 : 100.0 * $responseStats["total"] / $pollStats["total"];
                        if ($choice["choice_id"] == $response["choice_id"]) {
                            // Make selected
                            ?>
                            <div class="list-group-item list-group-item-info">
                                <strong><?=$choice["choice_text"]?></strong>
                                <span class="badge"><?=$responseStats["total"]?></span>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" aria-valuenow="<?=$percentOfTotal?>"
                                         aria-valuemin="0" aria-valuemax="100" style="width:<?=$percentOfTotal?>%">
                                    </div>
                                </div>
                                <div class="text-center"><span class="fa fa-check-circle-o" aria-hidden="true"></span> Selected</div>
                            </div>
                            <?php
                            echo '';
                        } else {
                            ?>
                            <div class="list-group-item">
                                <?=$choice["choice_text"]?>
                                <span class="badge"><?=$responseStats["total"]?></span>
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" aria-valuenow="<?=$percentOfTotal?>"
                                         aria-valuemin="0" aria-valuemax="100" style="width:<?=$percentOfTotal?>%">
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    echo '</div>';
                }
            }
            if ($activePoll["allowchange"] == 1) {
                // Allowed to change response so add reset link
                echo '<p class="text-right"><a href="resetSelection.php?p='.$activePollId.'">Reset Selection</a></p>';
            }
        }
    }
}

$OUTPUT->footerStart();

$OUTPUT->footerEnd();
