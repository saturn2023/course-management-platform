# AMS Enrolment v2 – required behaviour (port from WordPress)

Reference implementation: `reference/wp-ams2.php` — a verbatim extract of the
`ams2_*` section of the live WordPress child-theme `functions.php`.
Match its behaviour, not its structure.

Where this spec and `reference/wp-ams2.php` disagree, **wp-ams2.php wins**. Flag the
difference rather than coding around it.

The full WordPress file is kept at `docs/reference/wp-functions-dump.php` for
context only. Never load it.

## Routing

- A course/plan pair uses v2 only if it has a published Enrolment Email template.
  No hardcoded course lists.
- Before creating a v2 enrolment, block it if the **secret key** already exists in the
  old flow (`ams2_in_old_flow()`). Check in route, form render and submit.
- Secret keys/links without a valid OrderID are invalid. Never treat them as a pass.
  Three separate causes collapse into one `invalid` state: a malformed key, a key with
  no order, and a key whose **order no longer resolves**. A non-zero order id is not
  enough on its own.
- `expired` is **not** `invalid`. An expired link is genuine, so the reference still
  routes it to v2 and lets the form show the expiry message. Collapsing the two would
  hand an expired link back to the old flow and show the student a wronger message.
- `course_id`, `plan_id` and `client_id` come from the link's own query string
  (`code`, `plan`, `company`), never from the browser — the reference is explicit
  ("From the link, not the browser"), because a learner can edit anything the form
  posts.
- Any course/plan **without** a published template stays on the existing
  `EnrolmentApiService::submit()` old flow, unchanged.

### The rollout switch

`AMS2_ADMIN_ONLY` gates who reaches v2: with it on, only a logged-in admin does,
and every student falls through to the old flow.

> **Deliberate divergence — the default is inverted.** In the reference
> `AMS2_ADMIN_ONLY` is **false**, so real students are already on v2 in
> production. Here it defaults to **true** until it is switched off deliberately.
> The reference's value is not a porting error to correct.

> **Deliberate divergence — the switch also gates the POST endpoints.** The
> reference checks it in `ams2_route_enrol_link()` and `ams2_enrolment_form()`
> only, not in `ams2_submit()`, `ams2_upload()` or `ams2_finalise()`. Anyone
> holding a valid secret key could therefore POST a v2 enrolment while the
> switch was on. All entry points are gated here.

Neither the switch nor the template check ever blocks a learner who **already
has a v2 row**: they are sent back to v2 before either question is asked,
because handing them to the old form would enrol them a second time. The
reference is explicit about this ordering and it must be preserved.

### The routing decision runs on GET only

The reference hooks `template_redirect` ahead of the old `/enrol/` handler, and
every outcome is either "go to v2" or "say nothing and let the old flow have
it" — never a refusal. Here that is middleware on `enrol.show`, so the v1 route
body needs no edit.

It deliberately does **not** run on the POST. A POST to the old endpoint comes
from someone who already has the old form open and filled in — *"Started on the
old form: leave it with the old flow"* — and redirecting it would throw their
answers away while protecting nothing, since no submission has been recorded
yet. The redirect is also marked uncacheable: a cached one would pin a learner
to whichever side of the decision they first saw, long after a published
template or the rollout switch moved it.

### What counts as "already in the old flow"

In the reference, *"this link has been used"* and *"this link is in the old
flow"* are the **same fact** — one `enrolment_requests` row records both.
Laravel splits them across two columns, so **either** means the link is spent:

| State | Counts? | Why |
|---|---|---|
| `enrolment_submissions` row, `api_status` pending or failed | **yes** | The answers are saved and a retry can still reach RTO Data, so v2 could enrol the learner a second time. |
| `enrolment_completed_at` set, no submission row | **yes** | Both v1 routes refuse a completed link, and nothing in the v2 path checks for a spent one. Reading only the submission row would let a used link come back to life through v2. |
| Submit died before writing anything | no | Nothing was submitted; the link is still unused. The `form_data` encoding bug did exactly this — the submission insert threw, so the completion write never ran. |
| `status` = `'completed'` but `enrolment_completed_at` null | no | `status` is a free-text field on the admin form. `enrolment_completed_at` is what both v1 routes actually test. |

Neither marker is ever written by v2, which keeps its state entirely in
`ams2_enrolments`, so this can never lock v2 out of its own rows.

A completed link with no submission row takes a direct delete to produce — a
manual edit, a partial restore, or a retention purge. The reference has
`ams_purge_personal_data_run()`; **Laravel has no equivalent yet**, so that
third cause does not exist here today.

### Link expiry

> **Deliberate divergence — both rules apply.** The reference expires a link on
> the **order's** age alone: created + 125 days. Laravel already issues links
> with their own `enrolment_token_expires_at` (30 days), which the old flow
> honours today, so a link is expired when **either** has passed. Porting only
> the reference's rule would quietly stretch every existing link from 30 days
> to 125.

### `client_id` is always empty

> `CreateEnrolmentJob` builds `secret_base_url` with `code` and `plan` only — no
> `company` — so the `client_id` the reference reads from the link resolves to
> `''` and reaches the enrol payload empty. The parse is ported faithfully and
> will pick the value up if links ever carry it. **Open question with AMS:**
> whether purchase-order enrolments should link to an employer in RTO Data.

### Scheduling latency

> **Deliberate divergence.** `ams2_schedule()` is not ported (decided at stage
> 2), so a newly submitted row waits for the per-minute scheduled run rather
> than the reference's `time() + 5`. A row therefore starts up to ~60 seconds
> later than it would in WordPress. No behaviour differs; nothing in the flow
> depends on the delay. Worth knowing before reading a slow first enrol as a
> stuck processor.

## The form (stage 6)

Three requests, not one multipart POST:

```
POST /enrolment-v2/submit      fields only, returns the row id
POST /enrolment-v2/documents   one file per request, up to 4 attempts each
POST /enrolment-v2/finalise    sets docs_final
GET  /enrolment-v2/token       a fresh CSRF token
```

That shape is the reference's and it is load-bearing: one flaky upload can be
retried without resubmitting the enrolment, and no single request has to carry
every document at once — which matters because PHP discards an oversized
request before any application code runs.

**Required fields are only `first_name`, `last_name`, `date_of_birth` and
`email`.** Everything else on the form is optional, and failure is one generic
message rather than per-field errors.

**A resubmit of a key that already has a row is a SUCCESS** returning the same
id, checked *before* the required-field checks. That is what makes the script's
"You will not be enrolled twice" true, and it means a bare retry after a dropped
connection does not read as a validation failure.

**`course_id`, `plan_id` and `client_id` are read off the link every time** and
stripped from anything posted. The v2 form renders no such inputs at all, unlike
the reference's shared markup, but a crafted POST can send anything.

**`docs_expected` records what the BROWSER says it is holding**, not what has
arrived, and `docs_final` stays false until the browser confirms it has
finished. With no documents expected the row is final immediately and the
documents step never waits.

**The document type is decided from the file's contents.** A real JPEG saved as
`.pdf` is accepted and stored as `.jpg`; a zip named `.pdf` is refused.
`original_name` keeps whatever the learner called it, because RTO Data must
receive that.

### The upload limit is read at runtime

> **Deliberate divergence.** The reference offers `AMS_DOC_MAX_BYTES` (10MB)
> unconditionally, which is safe on its own host. Hosting here is cPanel, where
> `upload_max_filesize` is routinely 2M–8M, and a file above it is discarded by
> PHP before any code runs — the request arrives with an empty `$_FILES` and no
> useful error. So the form offers the **smallest** of the configured ceiling,
> `upload_max_filesize` and `post_max_size`, and the label rounds **down** so it
> never names a size the server would reject.

### The prefilled name has its accents stripped, but is not ASCII

> Ported from the reference's `clean()`: decompose to NFD, drop the combining
> marks. This is **not** a conversion to ASCII, in the reference either — only
> decomposable characters change. `José` → `Jose`, but `Ł`, `Đ`, `ø` and `ß` pass
> straight through, so `Müller-Łódź` → `Muller-Łodz` and `Søren` is unchanged.
> If RTO Data or the USI registry needs plain ASCII, those names still fail on
> enrol. Transliterating further was considered and rejected: it risks
> submitting a name matching neither the learner's ID nor the registry.

### Departures in the browser script

> - WordPress nonces become Laravel's CSRF token, refreshed from
>   `/enrolment-v2/token` and retried once on the `nonce` code — the same dance.
>   A stale token is mapped to that code by matching the **419**, not
>   `TokenMismatchException`: the handler's `prepareException()` has already
>   converted it by the time render callbacks run.
> - `finalise` is sent with `fetch(keepalive)` rather than `sendBeacon`, so the
>   CSRF token still travels as the page unloads. The reference's equivalent
>   skips its nonce check for exactly this reason.
> - The reference beacons client-side diagnostics to an `ams_log_client` action.
>   There is no such endpoint here, so those calls are dropped rather than
>   pointed at something that would 404 on every file a learner rejects.

## Status machine

These are the exact statuses `reference/wp-ams2.php` uses. Every `-ing` status is a
claimed, in-flight state; every other status is a resting state a run can begin from.

| From | Claim (in-flight) | To |
|---|---|---|
| `received` | `enrolling` | `enrolled` |
| `enrolled` | `sending_documents` | `documents_done` |
| `documents_done` | `updating` | `updated` |
| `updated` | `emailing` | `complete` |
| `verifying` | `checking` | (resolved by `ams2_step_verify()`) |

Plus `needs_review`, which nothing transitions out of except the admin Resume action.

## Processing order (per enrolment row)

1. `received`: form submitted, documents stored, row saved.
2. Claim `received → enrolling` atomically
   (`UPDATE ... WHERE id = ? AND status = 'received'`, must affect exactly 1 row).
   Enrol in RTO Data v2 with the student's REAL name, DOB and details, but a
   placeholder in `email` and `other_email` (same value in both).
3. `enrolled`: store the RTO Data enrolment ID on the row immediately.
4. Claim `enrolled → sending_documents`. Upload documents to RTO Data, sending the
   ORIGINAL uploaded filename (`original_name`), not the stored/randomised filename.
   Then `documents_done`.

   **Readiness gate.** The step waits until `docs_final` is set, or the row is 15
   minutes old, whichever comes first — so it never sends a half-uploaded set.

   > **Done in stage 6.** `POST /enrolment-v2/finalise` sets `docs_final` when
   > the browser finishes uploading. Without it every row with documents would
   > sit for the full 15 minutes before this step touched it — nothing fails,
   > enrolments are just silently slower, which is easy to misread as the
   > processor being broken. The gate stays as the backstop for a learner whose
   > browser never reports in, since `finalise` is sent as the page unloads and
   > may not arrive.

   **A document that cannot be sent does not stop the enrolment.** The learner was
   never shown an error and is already in RTO Data, so the row advances to
   `documents_done` regardless and an alert names what is missing. A document is
   retried until 5 attempts, then marked `failed` keeping its local file; a sent
   document has its local file deleted. Only `pending` and `retry` documents are
   ever selected, so a retry can never re-upload one already `sent`.

   RTO Data's answer is judged by searching the raw response for the substring
   `"success":1` — not by decoding the JSON. This is brittle and is reproduced
   deliberately; a body that decodes with a truthy `success` but lacks that exact
   substring is logged as `ams2_document_success_mismatch` without changing the
   outcome.
5. Claim `documents_done → updating`. Update ONLY the email fields to the real
   address. Then `updated`.
6. Claim `updated → emailing`. Send the per-course confirmation email from the
   template. Then `complete`. Do NOT send the old confirmation email in this flow.

   **Recipients and sender.** To the learner's `real_email` only — the reference
   sets no CC, no BCC, no copy to info@ or sales@, and no Reply-To. From is
   `AMS Training <info@amstraining.com.au>`, hardcoded there and configurable
   here as `AMS2_MAIL_FROM` / `AMS2_MAIL_FROM_NAME`. Deliberately NOT
   `MAIL_FROM_ADDRESS`: that is whatever the environment sends its own mail as,
   which on a dev machine is usually the agency's address.

   **No published template at send time** → `needs_review` with an alert naming
   the course and plan, and no send. Retrying cannot conjure a template.

   **Never send twice.** A row found at `emailing` with `email_sent_at` already
   set is moved to `complete` without resending. This is checked *before* the
   claim, because such a row sits at `emailing` and could never be claimed from
   `updated`.

   > This is a deliberate improvement on the reference, which has no duplicate
   > guard at all: `email_sent_at` is written but never read back, so a crash
   > between `wp_mail` returning and the status write leaves the row at
   > `emailing`, the 30-minute sweep parks it, and Resume sends the
   > confirmation a second time.
   >
   > `email_sent_at` and `complete` are written in a single update immediately
   > after the send returns, making that window as small as it can be made.
   > **It cannot be closed from inside the process**: a crash between the SMTP
   > server accepting the message and that update will still cause one resend.
   > Closing it properly would need the send and the state change to share a
   > transaction, which SMTP cannot join.

   **Subject fallback — a deliberate divergence.** The subject is
   `'Enrolment confirmation ' . $title`. The reference takes `$title` from the
   row alone, so a row whose `course_title` RTO Data left empty gets a bare
   `"Enrolment confirmation"`. Here the row's title is tried first, then the
   local `Course` matching the same course_id/plan_id, and only then the bare
   subject. A learner-visible subject with no course name in it is worse than
   matching the reference exactly.

### Placeholder email

Exactly:

```
processing{id}hold.invalid
```

Built in `ams2_enrol_args()` as `'processing' . (int) $row->id . 'hold.invalid'`.

This is **not a valid email address** — it has no `@`. That is deliberate and must be
reproduced exactly. No email validation rule may be applied to this field.

## RTO Data payload rules (enforce in the API client, not at call sites)

- Never send `contact_email` on enrol or update. It is the emergency contact's email
  and gets overwritten or blanked.
- Update payloads: strip empty strings and nulls so nothing is blanked by accident.
- Placeholder names were tested and ruled out: update changes the enrolment name but
  not the learner profile, which breaks AVETMISS/USI. Real names only.

## Concurrency

- Only one processor run at a time (global lock).
- Every status transition is an atomic claim. Never select-then-update.
- If enrol times out, check RTO Data for an existing enrolment before retrying
  (`ams2_step_verify()`, via the `verifying → checking` claim).
  Never blind-retry an enrol call.

## Failure handling

- Rows stuck for 30 minutes go to `needs_review` and trigger an alert.
- Alert must include learner ID and the real email to restore, because a returning
  learner's RTO Data email may still be the placeholder.
- Alerts go via the Two Moons SMTP2GO account (API, `TM_SMTP2GO_API_KEY`), never the
  client's mail setup.
- Admin Resume action continues from the current status, no duplicate enrol.

## Production hygiene

- All keys (RTO Data v1/v2, SMTP2GO, Twilio) in `.env` only.
- No test/debug routes that can hit live RTO Data, Twilio or email in production.

---

## Enrolment Email template

Port of the WordPress `ams_enrol_email` post type. A **published** template for a
course/plan pair is the v2 gate.

- Keyed on RTO Data's `course_id` + `plan_id`, stored as plain integers — not a
  foreign key to `courses.id`, because the lookup compares the enrolment row's own
  IDs and the local `Course.ams_enrolment_code` / `ams_plan_id` are nullable.
- **One published template per course/plan pair.** Enforced in the model's `saving`
  event (so it holds on every write path, not just the admin form) rather than as a
  unique index, because MySQL cannot express "unique among published rows only".
  Unpublished duplicates are drafts and are allowed.
- Attachments keep their original filenames and live under
  `enrolment-templates/{storage_key}/` on the **private** disk — per-template
  directories, so two templates uploading the same filename cannot overwrite each
  other. A file missing at send time is skipped, and logged as a warning.
- Body is edited as raw HTML in a plain textarea, never a rich editor.

---

## Resolved discrepancies — spec vs wp-ams2.php

All six were decided in favour of `wp-ams2.php`, with one deliberate exception.

| # | Finding | Resolution |
|---|---|---|
| 1 | No subject field exists | **Accept WP.** No subject is stored. Composed at send time as `'Enrolment confirmation ' . $title`, trimmed |
| 2 | Names are UPPERCASED, not capitalised | **Accept WP.** `mb_strtoupper()` then escaped, so `JOHN SMITH` |
| 3 | A third placeholder, `{course_title}`, exists | **Accept WP.** All three supported: `{first_name}` `{last_name}` `{course_title}` |
| 4 | "Up to 3" attachments, not 3 | **Accept WP.** All optional; a template with none still sends |
| 5 | `plan_id` is not required in ACF | **Diverge, deliberately.** `plan_id` is required. Every lookup matches on both IDs, so a template saved without one could never be found |
| 6 | `course_title` meta is read but never writable | **Omit.** No override field. The enrolment row's own course title is always used, which is what WP does in practice |

### `{site_url}` — a Laravel addition

A fourth body placeholder, `{site_url}`, with **no WordPress equivalent**. The
reference hardcodes `amstraining.com.au` into its template bodies, which breaks
on staging and behind a tunnel.

It resolves to `config('app.url')` with any trailing slash removed, so a template
writes `{site_url}/courses` and gets one slash. Substituted in the **body only** —
the subject is composed from the course title alone, exactly as in the reference.

Because it reads `APP_URL`, a template sent while `APP_URL` is wrong will carry
wrong links. See the deployment note about `APP_URL` and tunnels.
