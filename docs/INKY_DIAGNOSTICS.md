# Inky Diagnostics and Incident Record

This is the operational diagnostic runbook for Inky authoring, Continue Story,
Improve Story, and the public Inky endpoints. It is the repository mirror of
the project handoff and writer documentation. The diagnostic rule is simple:
capture the failing request stage and recorded interaction before changing
application behavior.

## Terminal-first process

Run production checks from the deployed document root. Every production
command starts with:

```bash
cd /home/toinkyadmin/public_html
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
```

1. Lint the exact deployed files involved in the request:

```bash
$PHP -n -l app/InkyGuideService.php
$PHP -n -l app/InkyCowriterService.php
$PHP -n -l public/inky-starter.php
$PHP -n -l public/inky-action.php
```

2. Check the AI connection without loading the service class twice:

```bash
$PHP -r 'require "app/bootstrap.php"; var_export(AiConnectionService::status()); echo PHP_EOL;'
```

`app/bootstrap.php` already loads `AiConnectionService.php`. Requiring that
file again in the same command causes `Cannot redeclare class
AiConnectionService`; that error diagnoses the test command, not the AI
connection.

3. Inspect recent recorded requests, including the purpose, HTTP status,
latency, and server error:

```bash
$PHP -r 'require "app/bootstrap.php"; $q=db()->query("SELECT ai_interaction_id,purpose,success,http_status,latency_ms,error_text,response_chars,request_started_at_utc,request_completed_at_utc FROM ai_reasoner_interactions WHERE purpose LIKE \"INKY_%\" ORDER BY ai_interaction_id DESC LIMIT 10"); foreach($q as $row) echo json_encode($row,JSON_UNESCAPED_SLASHES),PHP_EOL;'
```

Use the exact SQL quoting supported by the shell in use. If the query returns
no new row, the failure occurred before the AI service was called.

4. For a saved Continue Story proposal, use the CLI probe. It requires a
saved Inky draft for the selected story and reports the parsed response when
`--show-response` is supplied:

```bash
$PHP tools/inky_proposal_probe.php 5 decision_2 --show-response 2>&1
echo EXIT:$?
```

Replace `5` and `decision_2` with the target story and proposal key. A
successful AI interaction with a rejected proposal means the next place to
inspect is response normalization or schema validation, not connectivity.

5. If no interaction row is created, use a temporary request trace. This is a
diagnostic instrument only and must be removed after one reproduction:

```bash
: > /home/toinkyadmin/inky_request_trace.log
cat /home/toinkyadmin/inky_request_trace.log
```

The trace should record only endpoint, stage, request mode, user ID, method,
CSRF presence, and exception class/message. It must not record prompts,
tokens, passwords, private story payloads, or AI credentials. Interpret the
stages as follows:

- `start`: the endpoint received the request.
- `authorized`: bootstrap, session, permission, method, and CSRF checks passed.
- `success`: the application returned a successful result.
- `failure`: the exact application exception was captured.

After the reproduction, remove the trace code, deploy the cleanup, and clear
the log. Do not leave request tracing enabled as a permanent feature.

6. Run the relevant contract tests after the code is deployed:

```bash
$PHP -n -l tools/inky_guide_contract_test.php
$PHP tools/inky_guide_contract_test.php
$PHP tools/inky_source_test.php
```

`inky_source_test.php` also checks that the deployed copy of `docs/INKY.md`
exists. A missing production documentation file is a deployment/test-fixture
problem, not evidence that the AI request failed.

## Request-path classification

- Continue Story proposal buttons use `public/inky-starter.php`,
  `InkyGuideService::guide()`, and the `INKY_STORY_PROPOSAL` interaction
  purpose.
- Create With Inky conversation and guided content/edit suggestions also use
  `public/inky-starter.php`, with purposes `INKY_STORY_GUIDE`,
  `INKY_STORY_GUIDE_CONTENT`, and `INKY_STORY_GUIDE_EDITS`.
- Improve Story and character co-writer actions use `public/inky-action.php`
  and `InkyCowriterService`.
- Scenario Map is deterministic and does not call the AI service.

Use the path that produced the user-visible error. Do not diagnose a Continue
Story failure from an unrelated review interaction.

## Incident record

### Missing `proposalResponse()` method

**Symptom:** Continue Story returned a generic conversation failure and no
new AI interaction row was created.

**Root cause:** Commit `40b94a8` introduced the `proposalResponse()` wrapper.
Commit `7a0a375` renamed the implementation to
`completeJsonWithRepair()` but left both calls in `proposal()` pointing at
`self::proposalResponse()`. The endpoint reached authorization and failed
before contacting the AI service.

**How it was found:** A temporary endpoint trace showed `start`, then
`authorized`, then `failure` with `Call to undefined method
InkyGuideService::proposalResponse()`; the interaction table confirmed there
was no corresponding AI request.

**Solution:** Restore the small `proposalResponse()` wrapper and keep the
generic JSON repair helper underneath it. Add a contract/probe check before
deployment and inspect the latest interaction after deployment.

### Proposal field-name mismatch

**Symptom:** Some Continue Story buttons reported missing event headlines,
missing location names, or missing contrasting decision options while other
proposal types worked.

**Evidence and inference:** The validator accepted only canonical keys such as
`headline`, `label_text`, `text`, and `options`. The recorded AI response must
be inspected with `inky_proposal_probe.php --show-response` before declaring
the exact alias. Common equivalent keys are now normalized at the boundary,
but missing content is still rejected and two options are still required. A
later probe also captured a full story package returned in place of the
requested proposal. The requested key is now selected from that package when
present; unrelated package data is never guessed into the requested item. If
the requested key is absent, validation fails and the normal correction retry
is used instead of silently borrowing a sibling record.

**Solution:** Normalize documented response aliases such as `title` or
`event_headline` to `headline`, `name` or `location_name` to `label_text`, and
`choices` or `outcomes` to `options`, including common option label/result
aliases. Validate only after normalization. The contract test covers these
cases without spending an AI request.

### Session `gc_divisor` warning

**Symptom:** cPanel error logs repeatedly reported `session.gc_divisor must
be greater than 0`.

**Finding:** The warning came from PHP startup/configuration and persisted
independently of Inky. Direct AI status, guide completion, save-progress, and
proposal interactions could succeed while the warning remained. A per-site
`php.ini`/`.user.ini` value and an application fallback were speculative
mitigations; they did not explain or fix the Continue Story failure.

**Disposition:** Remove the application-level `ini_set()` workaround. Manage
the hosting-level PHP setting through cPanel, and remove the per-site INI
entries if they were added only for this investigation after confirming no
other application requires them. Never use a PHP startup warning by itself as
the reason for an Inky code change.

### Removed guide handler assumption

**Symptom:** An inline guide handler was removed under the assumption that it
duplicated the sidebar guide script.

**Finding:** The inline handler belonged to the separate Story Creation form
in `writer.php`; the sidebar workflow is handled by
`public/assets/toinky-inky-guide.js`. The scope was not proven before the
change.

**Disposition:** Restore the separate handler. Future duplicate-handler
changes require tracing the rendered form and script selector, then a focused
UI/endpoint test.

## Change gate

Before pushing an Inky fix:

1. Reproduce or classify the failure with the endpoint and interaction log.
2. Identify whether the failure is transport, authorization, response parsing,
   response validation, persistence, or presentation.
3. Add a deterministic contract test for the identified boundary.
4. Run `git diff --check`, inspect the diff, and run available local checks.
5. Deploy only the focused change, then run the production lint, contract test,
   and one terminal probe for the affected path.
6. Remove temporary diagnostics and record the root cause, solution, and test
   method in this document.

Generic UI messages are not sufficient evidence for a code correction.
