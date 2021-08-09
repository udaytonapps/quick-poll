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

    // Save the poll
    $newPollStmt = $PDOX->prepare("INSERT INTO {$p}qp_poll (
                user_id, 
                context_id, 
                question_text,
                allowchange,
                hidesummary,
                anonymous,
                modified
            )
            values (
             :userId, 
             :contextId, 
             :questionText,
             :allowchange,
             :hidesummary,
             :anonymous,
             :modified
            )");
    $newPollStmt->execute(array(
        ":userId" => $USER->id,
        ":contextId" => $CONTEXT->id,
        ":questionText" => $questionText,
        ":allowchange" => $allowchange,
        ":hidesummary" => $hidesummary,
        ":anonymous" => $anonymous,
        ":modified" => $currentTime
    ));
    $poll_id = $PDOX->lastInsertId();
    // Insert choices
    $choiceArr = $_POST["choice"] ?? false;
    if (!$choiceArr) {
        $_SESSION["error"] = "Added poll without any choices.";
    } else {
        for ($i = 0; $i < count($choiceArr); $i++) {
            if ($choiceArr[$i] == '') {
                // Blank so remove from list and retry index
                array_splice($choiceArr, $i, 1);
                $i--;
                continue;
            }
            $newChoiceStmt = $PDOX->prepare("INSERT INTO {$p}qp_choice (poll_id, choice_text, choice_order, modified)
                values (:pollId, :choiceText, :choiceOrder, :modified)");
            $newChoiceStmt->execute(array(
                ":pollId" => $poll_id,
                ":choiceText" => $choiceArr[$i],
                ":choiceOrder" => $i,
                ":modified" => $currentTime
            ));
        }
    }
    // Set poll id as active poll for this link if requested
    if ($active) {
        $LAUNCH->link->settingsSet("active-poll-id", $poll_id);
    }
    $_SESSION["success"] = "Poll saved.";
    header('Location: ' . addSession('build.php'));
    return;
}

$OUTPUT->header();

include("tool-header.html");

$OUTPUT->bodyStart();

$OUTPUT->topNav(false);

?>
    <h4>Add A New Poll</h4>
    <form method="post" action="addPoll.php">
        <div class="form-group">
            <label for="question">Ask a question...</label>
            <textarea class="form-control" rows="3" id="question" name="question" required></textarea>
        </div>
        <div id="choices">
            <div class="flx-cntnr" id="choice-container-1">
                <div class="form-group choice flx-grow-all">
                    <label for="choice-1" class="sr-only">Choice <span data-choice="1">1</span></label>
                    <input type="text" name="choice[]" class="form-control" id="choice-1" placeholder="Choice 1">
                </div>
                <div>
                    <button type="button" class="btn btn-link remove-choice" data-remove="1" onclick="removeChoice(this);"><span class="fa fa-times" aria-hidden="true"></span><span class="sr-only">Remove Choice</span></button>
                </div>
            </div>
            <div class="flx-cntnr" id="choice-container-2">
                <div class="form-group choice flx-grow-all">
                    <label for="choice-2" class="sr-only">Choice <span data-choice="2">2</span></label>
                    <input type="text" name="choice[]" class="form-control" id="choice-2" placeholder="Choice 2">
                </div>
                <div>
                    <button type="button" class="btn btn-link remove-choice" data-remove="2" onclick="removeChoice(this);"><span class="fa fa-times" aria-hidden="true"></span><span class="sr-only">Remove Choice</span></button>
                </div>
            </div>
        </div>
        <p class="text-center"><a href="javascript:void(0);" id="add-choice"><span class="fa fa-plus"></span> Add Choice</a>
        </p>
        <h5>Poll Settings</h5>
        <div class="checkbox">
            <label><input type="checkbox" name="allowchange" value="1"> Allow students to change their response</label>
        </div>
        <div class="checkbox">
            <label><input type="checkbox" name="hidesummary" value="1"> Hide results summary from students</label>
        </div>
        <div class="checkbox">
            <label><input type="checkbox" name="anonymous" value="1"> Make poll anonymous (instructor can see results summary only)</label>
        </div>
        <h5>Activate Poll</h5>
        <div class="checkbox">
            <label><input type="checkbox" name="active"
                          value="1" <?= isset($_GET["active"]) && $_GET["active"] == 1 ? 'checked' : '' ?>> Set this
                poll as the active poll for this instance</label>
        </div>
        <hr>
        <div>
            <button type="submit" class="btn btn-primary">Submit</button>
            <a href="build.php" class="btn btn-link">Cancel</a>
        </div>
    </form>
<?php
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