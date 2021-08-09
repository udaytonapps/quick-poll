<?php
require_once "../config.php";

use \Tsugi\Core\LTIX;
use \Tsugi\UI\SettingsForm;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
    return;
}

if (SettingsForm::isSettingsPost()) {
    SettingsForm::handleSettingsPost();
    $_SESSION["success"] = __('All settings saved.');
    header('Location: '.addSession('index.php'));
    return;
}

SettingsForm::start();
SettingsForm::end();

include("menu.php");

$OUTPUT->header();

include("tool-header.html");

$OUTPUT->bodyStart();

$OUTPUT->topNav($menu);

// Get active poll
$activePollId = $LAUNCH->link->settingsGet("active-poll-id", false);
    if (!$activePollId) {
        echo '<p class="text-center"><em>No active poll for this instance.</em><br><a href="addPoll.php?active=1" class="btn btn-link"><span class="fa fa-plus" aria-hidden="true"></span> Add Active Poll</a><br><em>or activate a poll from this site\'s poll library.</em></p>';
    } else {
        $activePollStmt = $PDOX->prepare("SELECT * FROM {$p}qp_poll WHERE poll_id = :pollId");
        $activePollStmt->execute(array(":pollId" => $activePollId));
        $activePoll = $activePollStmt->fetch(PDO::FETCH_ASSOC);
        if (!$activePoll) {
            echo '<p><em>Error: Unable to locate active poll. Please contact your instructor.</em></p>';
        } else {
            // Get choices for active poll
            $activePollChoicesStmt = $PDOX->prepare("SELECT * FROM {$p}qp_choice WHERE poll_id = :pollId order by choice_order");
            $activePollChoicesStmt->execute(array(":pollId" => $activePollId));
            $activeChoices = $activePollChoicesStmt->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <h5 class="text-muted" style="margin-bottom:0;">Active Poll</h5>
            <div class="h4" style="font-weight:400;margin-top:0;">
                <div class="pull-right" style="z-index:1;">
                    <a href="editPoll.php?p=<?=$activePoll["poll_id"]?>" class="btn btn-default"><span class="fa fa-pencil" aria-hidden="true"></span><span class="sr-only">Edit Poll</span></a>
                    <a href="<?=$activePoll["anonymous"] == 1 ? 'javascript:void(0);" title="Anonymous Poll' : 'results.php?p='.$activePoll["poll_id"]?>" class="btn btn-default <?=$activePoll["anonymous"] == 1 ? 'disabled' : ''?>">
                        <span class="fa fa-bar-chart-o" aria-hidden="true"></span> <span class="sr-only">Results</span>
                    </a>
                </div>
                <?=$activePoll["question_text"]?>
            </div>
            <?php
            $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId");
            $totalStmt->execute(array(":pollId" => $activePollId));
            $pollStats = $totalStmt->fetch(PDO::FETCH_ASSOC);
            if ($activeChoices && count($activeChoices) > 0) {
                echo '<div class="list-group" style="margin-bottom:1rem;">';
                foreach($activeChoices as $choice) {
                    // Get total responses
                    $totalStmt = $PDOX->prepare("SELECT COUNT(*) AS total FROM {$p}qp_response WHERE poll_id = :pollId AND choice_id = :choiceId");
                    $totalStmt->execute(array(":pollId" => $activePollId, ":choiceId" => $choice["choice_id"]));
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
                    </div>
                    <?php
                }
                echo '</div>';
            }
            echo '<p class="text-right"><strong>'.$pollStats["total"].'</strong> total responses</p>';
        }
    }
$allPollsStmt = $PDOX->prepare("SELECT * FROM {$p}qp_poll WHERE context_id = :contextId");
$allPollsStmt->execute(array(":contextId" => $CONTEXT->id));
$allPolls = $allPollsStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
 <div class="panel-group">
  <div class="panel panel-default">
    <div class="panel-heading">
      <h4 class="panel-title">
          <a data-toggle="collapse" href="#poll-library"><span class="fa fa-chevron-right poll-library-icon" aria-hidden="true"></span> Poll Library <small><?=$CONTEXT->title?></small></a>
      </h4>
    </div>
    <div id="poll-library" class="panel-collapse collapse">
      <div class="panel-body">
        <p class="text-center"><a href="addPoll.php" class="btn btn-link"><span class="fa fa-plus" aria-hidden="true"></span> Add Poll to Library</a>   </p>
<?php
if (!$allPolls || count($allPolls) == 0) {
    echo '<p style="margin-top:1rem;" class="text-center"><em>There have been no polls added to this site\'s library.</em></p>';
}
foreach($allPolls as $poll) {
    ?>
    <div class="h4 flx-cntnr" style="font-weight:400;align-items:top;">
        <div>
        <?php
        if ($poll["poll_id"] == $activePollId) {
            ?>
            <a href="toggleActivePoll.php?p=<?=$poll["poll_id"]?>" class="btn btn-link" style="margin-right:1rem;font-size:1.5rem;padding: 0;"><span class="fa fa-toggle-on" aria-hidden="true"></span><span class="sr-only">Active</span></a>
            <?php
        } else {
            ?>
            <a href="toggleActivePoll.php?p=<?=$poll["poll_id"]?>" class="btn btn-link" style="margin-right:1rem;font-size:1.5rem;padding: 0;"><span class="fa fa-toggle-off text-muted" aria-hidden="true"></span><span class="sr-only">Inactive</span></a>
            <?php
        }
        ?>
        </div>
        <div class="flx-grow-all<?=($poll["poll_id"] == $activePollId) ? '' : ' text-muted'?>">
            <?=$poll["question_text"]?>
        </div>
        <div style="padding-left:4px;">
            <a href="student-home.php?p=<?=$poll["poll_id"]?>" class="btn btn-default"><span class="fas fa-user-graduate" aria-hidden="true"></span><span class="sr-only">Preview Poll</span></a>
        </div>
        <div style="padding-left:4px;">
            <a href="editPoll.php?p=<?=$poll["poll_id"]?>" class="btn btn-default"><span class="fa fa-pencil" aria-hidden="true"></span><span class="sr-only">Edit Poll</span></a>
        </div>
        <div style="padding-left:4px;">
            <a href="<?=$poll["anonymous"] == 1 ? 'javascript:void(0);" title="Anonymous Poll' : 'results.php?p='.$poll["poll_id"]?>" class="btn btn-default <?=$poll["anonymous"] == 1 ? 'disabled' : ''?>">
                <span class="fa fa-bar-chart-o" aria-hidden="true"></span><span class="sr-only">Results</span>
            </a>
        </div>
    </div>
    <?php
}

echo '</div>
    </div>
  </div>
</div> ';// end poll library panel

$OUTPUT->footerStart();
?>
<script>
    $(document).ready(function(){
        const libraryIcon = $(".poll-library-icon");
        $("#poll-library").on('show.bs.collapse', function() {
            libraryIcon.addClass('fa-chevron-down').removeClass('fa-chevron-right');
        }).on('hide.bs.collapse', function() {
            libraryIcon.addClass('fa-chevron-right').removeClass('fa-chevron-down');
        });
    });
</script>
<?php
$OUTPUT->footerEnd();