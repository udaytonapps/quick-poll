<?php
require_once "../config.php";

use Tsugi\Core\LTIX;

$LAUNCH = LTIX::requireData();

$p = $CFG->dbprefix;

if (!$USER->instructor) {
    header('Location: ' . addSession('student-home.php'));
    return;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $currentTime = new DateTime('now', new DateTimeZone($CFG->timezone));
    $currentTime = $currentTime->format("Y-m-d H:i:s");

    $questionText = $_POST["question"];
    $active = isset($_POST["active"]) && $_POST["active"] == 1;
    $allowchange = isset($_POST["allowchange"]) && $_POST["allowchange"] == 1 ? 1 : 0;
    $hidesummary = isset($_POST["hidesummary"]) && $_POST["hidesummary"] == 1 ? 1 : 0;
    $anonymous = isset($_POST["anonymous"]) && $_POST["anonymous"] == 1 ? 1 : 0;

    // Update the existing poll
    $updatePollStmt = $PDOX->prepare("UPDATE {$p}qp_poll SET 
                question_text = :questionText,
                allowchange = :allowchange,
                hidesummary = :hidesummary,
                anonymous = :anonymous,
                modified = :modified
            WHERE poll_id = :pollId");
    $updatePollStmt->execute(array(
        ":pollId" => $_POST["poll_id"],
        ":questionText" => $questionText,
        ":allowchange" => $allowchange,
        ":hidesummary" => $hidesummary,
        ":anonymous" => $anonymous,
        ":modified" => $currentTime
    ));
    // Update choices
    $choiceArr = $_POST["choice"] ?? false;
    if (!$choiceArr) {
        $_SESSION["error"] = "Added poll without any choices.";
    } else {
        $deleteFromIndex = 0;
        for ($i = 0; $i < count($choiceArr); $i++) {
            if ($choiceArr[$i] == '') {
                // Blank so remove from list and retry index
                array_splice($choiceArr, $i, 1);
                $i--;
                continue;
            }
            // Check if choice exists then update text otherwise insert new choice
            $existingChoiceStmt = $PDOX->prepare("SELECT * FROM {$p}qp_choice WHERE poll_id = :pollId AND choice_order = :choiceOrder");
            $existingChoiceStmt->execute(array(":pollId" => $_POST["poll_id"], ":choiceOrder" => $i));
            $existingChoice = $existingChoiceStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingChoice && $choiceArr[$i] !== '') {
                $updateChoiceStmt = $PDOX->prepare("UPDATE {$p}qp_choice SET 
                        choice_text = :choiceText,
                        choice_order = :choiceOrder,
                        modified = :modified
                    WHERE choice_id = :choiceId");
                $updateChoiceStmt->execute(array(
                    ":choiceId" => $existingChoice["choice_id"],
                    ":choiceText" => $choiceArr[$i],
                    ":choiceOrder" => $i,
                    ":modified" => $currentTime
                ));
            } else if ($choiceArr[$i] !== '') {
                // Insert new choice
                $newChoiceStmt = $PDOX->prepare("INSERT INTO {$p}qp_choice (poll_id, choice_text, choice_order, modified)
                values (:pollId, :choiceText, :choiceOrder, :modified)");
                $newChoiceStmt->execute(array(
                    ":pollId" => $_POST["poll_id"],
                    ":choiceText" => $choiceArr[$i],
                    ":choiceOrder" => $i,
                    ":modified" => $currentTime
                ));
            }
            $deleteFromIndex = $i;
        }
        // Delete any hanging choices
        $deleteChoiceStmt = $PDOX->prepare("DELETE FROM {$p}qp_choice WHERE poll_id = :pollId AND choice_order > :choiceOrder");
        $deleteChoiceStmt->execute(array(
            ":pollId" => $_POST["poll_id"],
            ":choiceOrder" => $deleteFromIndex
        ));
    }
    // Set poll id as active poll for this link if requested
    if ($active) {
        $LAUNCH->link->settingsSet("active-poll-id", $_POST["poll_id"]);
    }
    $_SESSION["success"] = "Poll updated.";
    header('Location: ' . addSession('build.php'));
    return;
}

// Check that poll selected for editing actually exists
$editPollId = $_GET["p"] ?? false;
$editPoll = false;
if ($editPollId) {
    $editPollStmt = $PDOX->prepare("SELECT * FROM {$p}qp_poll WHERE poll_id = :pollId");
    $editPollStmt->execute(array(":pollId" => $editPollId));
    $editPoll = $editPollStmt->fetch(PDO::FETCH_ASSOC);
}
if (!$editPollId || !$editPoll) {
    $_SESSION["error"] = "Unable to load poll for editing. Please try again.";
    header('Location: ' . addSession('build.php'));
    return;
}

// Editing active poll?
$active = $editPoll["poll_id"] == $LAUNCH->link->settingsGet("active-poll-id", false);

$OUTPUT->header();

include("tool-header.html");

$OUTPUT->bodyStart();

$OUTPUT->topNav(false);

echo '<h4>Edit an Existing Poll</h4>';

// Check if there are results for this poll
$hasResponsesStmt = $PDOX->prepare("SELECT count(*) as total FROM {$p}qp_response WHERE poll_id = :pollId");
$hasResponsesStmt->execute(array(":pollId" => $editPollId));
$totalResponses = $hasResponsesStmt->fetch(PDO::FETCH_ASSOC);
if ($totalResponses["total"] > 0) {
    echo '<p class="alert alert-danger text-center"><span class="fa fa-exclamation-triangle" aria-hidden="true"></span> This poll already has student responses. You must reset the results for this poll before making any edits.</p>';
    echo '<p class="text-center"><a href="build.php" class="btn btn-link"><span class="fa fa-arrow-left"></span> Back to Manage Polls</a></p>';
    echo '<hr><p class="text-center"><a href="resetResponses.php?p='.$editPollId.'&back=edit" class="text-danger" onclick="return confirm(\'Are you sure you want to reset the results for this poll? All existing student responses will be deleted and are not recoverable.\');"><span class="fa fa-trash-o" aria-hidden="true"></span> Reset All Responses</a></p>';
} else {
?>
    <form method="post" action="editPoll.php">
        <input id="poll_id" name="poll_id" value="<?=$editPollId?>" type="hidden">
        <div class="form-group">
            <label for="question">Ask a question...</label>
            <textarea class="form-control" rows="3" id="question" name="question" required><?=$editPoll["question_text"]?></textarea>
        </div>
        <div id="choices">
            <?php
            // Get choices and add options
            $editPollChoicesStmt = $PDOX->prepare("SELECT * FROM {$p}qp_choice WHERE poll_id = :pollId order by choice_order");
            $editPollChoicesStmt->execute(array(":pollId" => $editPollId));
            $editChoices = $editPollChoicesStmt->fetchAll(PDO::FETCH_ASSOC);
            $i = 1;
            foreach($editChoices as $choice) {
                ?>
                <div class="flx-cntnr" id="choice-container-<?=$i?>">
                    <div class="form-group choice flx-grow-all">
                        <label for="choice-<?=$i?>" class="sr-only">Choice <span data-choice="<?=$i?>"><?=$i?></span></label>
                        <input type="text" name="choice[]" class="form-control" id="choice-<?=$i?>" placeholder="Choice <?=$i?>" value="<?=$choice["choice_text"]?>">
                    </div>
                    <div>
                        <button type="button" class="btn btn-link remove-choice" data-remove="<?=$i?>" onclick="removeChoice(this);"><span class="fa fa-times" aria-hidden="true"></span><span class="sr-only">Remove Choice</span></button>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <p class="text-center"><a href="javascript:void(0);" id="add-choice"><span class="fa fa-plus"></span> Add Choice</a>
        </p>
        <h5>Poll Settings</h5>
        <div class="checkbox">
            <label><input type="checkbox" name="allowchange" value="1" <?=$editPoll["allowchange"] == 1 ? 'checked' : ''?>> Allow students to change their response</label>
        </div>
        <div class="checkbox">
            <label><input type="checkbox" name="hidesummary" value="1" <?=$editPoll["hidesummary"] == 1 ? 'checked' : ''?>> Hide results summary from students</label>
        </div>
        <div class="checkbox">
            <label><input type="checkbox" name="anonymous" value="1" <?=$editPoll["anonymous"] == 1 ? 'checked' : ''?>> Make poll anonymous (instructor can see results summary only)</label>
        </div>
        <h5>Activate Poll</h5>
        <div class="checkbox">
            <label><input type="checkbox" name="active"
                          value="1" <?= $active ? 'checked' : '' ?>> Set this
                poll as the active poll for this instance</label>
        </div>
        <hr>
        <div>
            <button type="submit" class="btn btn-primary">Submit</button>
            <a href="build.php" class="btn btn-link">Cancel</a>
            <a href="deletePoll.php?p=<?=$editPollId?>" onclick="return confirm('Are you sure you want to delete this poll and all of its responses? Deleted polls and results cannot be restored.');" class="text-danger"><span class="fa fa-trash-o" aria-hidden="true"></span> Delete Poll</a>
        </div>
    </form>
<?php
}
$OUTPUT->footerStart();
?>
    <script>
        $(document).ready(function () {
            $("#add-choice").off("click").on("click", function () {
                let countChoices = $("div.choice").length;
                countChoices++;
                $("#choices").append(`
                    <div class="flx-cntnr" id="choice-container-` + countChoices + `">
                        <div class="form-group choice flx-grow-all">
                            <label for="choice-` + countChoices + `" class="sr-only">Choice <span data-choice="` + countChoices + `">` + countChoices + `</span></label>
                            <input type="text" name="choice[]" class="form-control" id="choice-` + countChoices + `" placeholder="Choice ` + countChoices + `">
                        </div>
                        <div>
                            <button type="button" class="btn btn-link remove-choice" data-remove="` + countChoices + `" onclick="removeChoice(this);"><span class="fa fa-times" aria-hidden="true"></span><span class="sr-only">Remove Choice</span></button>
                        </div>
                    </div>
                `);
            });
        });
        function removeChoice(removeButton) {
            let removeIndex = $(removeButton).data("remove");
            let countChoices = $("div.choice").length;
            for (let i = removeIndex; i < countChoices; i++) {
                // Move all text up one
                let next = i + 1;
                let nextval = $("#choice-"+next).val();
                if (nextval.length) {
                    // Move text up one choice
                    $("#choice-"+i).val(nextval);
                }
            }
            // Remove last choice
            $("#choice-container-"+countChoices).remove();
        }
    </script>
<?php
$OUTPUT->footerEnd();