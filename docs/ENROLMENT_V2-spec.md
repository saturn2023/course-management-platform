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

### Scheduling latency

> **Deliberate divergence.** `ams2_schedule()` is not ported (decided at stage
> 2), so a newly submitted row waits for the per-minute scheduled run rather
> than the reference's `time() + 5`. A row therefore starts up to ~60 seconds
> later than it would in WordPress. No behaviour differs; nothing in the flow
> depends on the delay. Worth knowing before reading a slow first enrol as a
> stuck processor.

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

   > **Stage 6 (form) must set `docs_final = 1` when the browser finishes
   > uploading.** Without it every row with documents sits for the full 15
   > minutes before this step will touch it. Nothing fails; enrolments are just
   > silently 15 minutes slower, which is easy to misread as the processor
   > being broken.

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
