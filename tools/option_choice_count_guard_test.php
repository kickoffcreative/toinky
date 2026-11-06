<?php
declare(strict_types=1);
// Guard test: the dynamic_condition (opportunity condition) form must
// let a writer enter an option code for OPTION_CHOICE_COUNT.
//
// The bug: the form rendered source_key only as a select of managed
// story values, and initializeTargetSelect hides/disables every option
// whose scope does not match the Source - no managed value has scope
// OPTION_CHOICE_COUNT, so with that Source the select offered only the
// blank placeholder and a blank source_key saved as NULL, failing
// submit validation ("needs a source key"). The engine
// (DynamicOpportunityService) already reads source_key as a
// comma-separated list of option codes and counts CHOSEN decisions with
// those codes, and saveDynamicCondition already skips managed-value
// validation for OPTION_CHOICE_COUNT - only the form could not express
// it. The existing OPTION_CHOICE_COUNT conditions were seeded by
// migrations, not entered through this form.
//
// The fix: in the dynamic_condition form only, OPTION_CHOICE_COUNT
// shows a free-text source_key input instead of the managed-values
// select. Both inputs share the name "source_key" with only the
// visible one enabled, so exactly one value submits. Static checks
// only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$writer = (string)file_get_contents($root . '/public/writer.php');
$authoring = (string)file_get_contents($root . '/app/StoryAuthoringService.php');
$engine = (string)file_get_contents($root . '/app/DynamicOpportunityService.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

// The dynamic_condition forms: exactly two (edit + add), each holding
// exactly one free-text source_key input and one managed select, both
// named source_key.
$marker = "writer_form_context(\$plotId,\$tab,'dynamic_condition')";
$forms = [];
$offset = 0;
while (($start = strpos($writer, $marker, $offset)) !== false) {
    $end = strpos($writer, '</form>', $start);
    if ($end === false) break;
    $forms[] = substr($writer, $start, $end - $start);
    $offset = $end + 7;
}
$check(count($forms) === 2, 'exactly two dynamic_condition forms (edit + add)');
foreach ($forms as $i => $form) {
    $n = $i + 1;
    $check(substr_count($form, 'data-source-key-text') === 1, 'dynamic_condition form ' . $n . ' has one free-text source_key input');
    $check(substr_count($form, 'name="source_key"') === 2, 'dynamic_condition form ' . $n . ' has two source_key inputs sharing one name');
    $check(substr_count($form, 'data-target-select') === 1, 'dynamic_condition form ' . $n . ' still renders the managed-values select');
}
$check(substr_count($writer, '<input name="source_key" data-source-key-text') === 2, 'free-text source_key input appears nowhere else');
$check(substr_count($writer, "querySelector('[data-source-key-text]')") === 1, 'JS selects the free-text input by its marker');

// The text input starts disabled except when the saved source is
// OPTION_CHOICE_COUNT; the select is disabled in that case instead.
$check(str_contains($writer, 'data-source-key-text value="" disabled'), 'add form renders the free-text input disabled');
$check(str_contains($writer, "((string)\$c['source_type']==='OPTION_CHOICE_COUNT')?'':' disabled'"), 'edit form disables the free-text input unless the source is OPTION_CHOICE_COUNT');
$check(str_contains($writer, "((string)\$c['source_type']==='OPTION_CHOICE_COUNT')?' disabled':''"), 'edit form disables the managed select when the source is OPTION_CHOICE_COUNT');

// The JS toggle follows the Source value on load and on change.
$check(str_contains($writer, "isOptionChoiceCount=sourceType==='OPTION_CHOICE_COUNT'"), 'JS detects OPTION_CHOICE_COUNT source');
$check(str_contains($writer, 'sourceKeyText.disabled=!isOptionChoiceCount'), 'JS enables the free-text input only for OPTION_CHOICE_COUNT');
$check(str_contains($writer, "sourceKeyTextField.classList.toggle('is-not-used',!isOptionChoiceCount)"), 'JS hides the free-text field unless the source is OPTION_CHOICE_COUNT');
$check(str_contains($writer, 'target.disabled=!sourceKeyUsed||isOptionChoiceCount'), 'JS disables the managed select for OPTION_CHOICE_COUNT');
$check(str_contains($writer, '.source-key-text-field.is-not-used{display:none}'), 'CSS hides the free-text field when marked not used');

// Count sources compare against a number, so the compare input is numeric.
$check(str_contains($writer, "['ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT'].includes(sourceType)"), 'JS treats the count sources as one family');
$check(str_contains($writer, "isCountSource?'NUMERIC'"), 'JS renders a numeric compare input for count sources');

// The round trip the form feeds: the handler stores source_key verbatim
// for OPTION_CHOICE_COUNT and the summary reads it back.
$check(str_contains($authoring, "['PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT','OPTION_CHOICE_COUNT']"), 'saveDynamicCondition skips managed-value validation for OPTION_CHOICE_COUNT');
$check(str_contains($authoring, "['LOCATION','GOAL','FEAR','PHASE','ACTION_COUNT','PLAYER_ACTION_COUNT']"), 'submit validation still requires a source key for OPTION_CHOICE_COUNT');
$check(str_contains($engine, "\$type==='OPTION_CHOICE_COUNT'"), 'engine evaluates OPTION_CHOICE_COUNT conditions');
$check(str_contains($writer, "\$c['source_type'].' · '.(\$c['source_key']?:'current value').' · '.\$c['operator']"), 'condition summary reads back source type, source key and operator');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK option choice count guard\n";
