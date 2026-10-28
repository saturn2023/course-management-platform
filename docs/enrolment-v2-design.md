# Enrolment submission v2: design

Status: decisions Q1–Q13 and the risk responses (2026-10-16) are applied. No code, migrations or tests have been written.
Inputs: [enrolment-audit.md](enrolment-audit.md) (problem numbers P1–P27 refer to it), the step 2 brief, and the decisions recorded in §10.
Stack: Laravel 12, Filament 4, PHP 8.2. MySQL in production, SQLite in memory for tests (`phpunit.xml:37-38`). Hosting: cPanel shared hosting, no root (§9.2).

---

## 0. Scope and shape

**Rebuilt:** everything from `GET /enrol/{token}` (v2 path only) to the RTO Data calls and the new confirmation email.

**Kept as-is, with small fixes:**
- Checkout and order processing: store the RTO course/plan/schedule IDs.
- Xero: no change.
- `CreateEnrolmentJob`: copy the RTO IDs onto the enrolment, and replace `env()` with config.
- The link email: no change.
- Admin resend: skip completed enrolments.
- SMS job: read from config.

**Coexistence:** the v1 closure routes, `EnrolmentSubmission`, `SubmitEnrolmentToApiJob` and `EnrolmentApiService` stay untouched, apart from the `env()` fix and the `public_key` fix listed in §9.3. A one-off migration scrubs existing v1 API data (§1.4). v1 continues to serve every enrolment that isn't eligible for v2 (§9.1). v2 uses its own tables, so a v2 bug cannot corrupt v1 rows and v1 can be removed cleanly after cutover.

```
Browser                         Server (web)                        Queue / scheduler
───────                         ────────────                        ─────────────────
GET /enrol/{token} ───────────▶ render v2 form
pick files → read into memory,
  check ext/size/magic bytes
confirm name + DOB (modal)
POST …/submission (fields) ───▶ validate, lock enrolment row,
                                insert submission (received),
                                write students.date_of_birth,
                                mark link used
                       ◀─────── {submission_id, upload_token}   ──▶ AdvanceSubmissionJob: enrol
POST …/documents (1 per file,
  sequential, 3 network retries) ─▶ verify, WEBP→JPEG, store, doc row (pending)
sendBeacon …/finalise ────────▶ finalised_at = now               ──▶ documents → update → email
redirect /enrolment-completed                                        sweep every minute: due rows,
                                                                     stuck rows, late documents
                                                                     purge daily: idle/failed documents
```

---

## 1. Migrations

All new tables use `id()` bigint primary keys, `timestamps()` and InnoDB/utf8mb4. "FK" means `foreignId()->constrained()` with the stated delete rule.

### 1.1 Small fixes to existing tables

**`2026_10_20_000001_add_rto_ids_to_checkout_sessions_table`**

| Column | Type | Notes |
|---|---|---|
| `rto_course_id` | `string(50)` nullable, after `course_code` | The raw `code` query parameter passed to `/checkout/load`. Today it is lost: `course_code` is overwritten with RTO Data's `course_code` string (`CheckoutLoadController.php:85`, `RtoDataCourseService.php:88-92`). `plan_id` and `schedule_id` already exist. |

**`2026_10_20_000002_add_rto_ids_to_order_items_table`**

| Column | Type | Notes |
|---|---|---|
| `rto_course_id` | `string(50)` nullable | From `checkout_sessions.rto_course_id` |
| `rto_plan_id` | `string(50)` nullable | From `checkout_sessions.plan_id` (P11) |
| `rto_schedule_id` | `string(50)` nullable | From `checkout_sessions.schedule_id` |

**`2026_10_20_000003_add_rto_ids_to_enrolments_table`**

| Column | Type | Notes |
|---|---|---|
| `rto_course_id`, `rto_plan_id`, `rto_schedule_id` | `string(50)` nullable | Copied from the order item by `CreateEnrolmentJob`. `rto_course_id` and `rto_plan_id` are the **only** source for the enrol call's `course_id` and `plan_id` (P18). |

The columns are strings because RTO Data IDs arrive as query strings. They are cast to `int` only when building the enrol args.

`rto_schedule_id` is stored for the record but **never sent to RTO Data** (Q3). Every v2 course is an online refresher, so a non-empty `rto_schedule_id` means the enrolment is not eligible for v2 (§9.1).

No backfill: rows created before this deploy have nulls, which makes them ineligible for v2 (§9.1).

### 1.2 New tables

**`enrolment_v2_submissions`**: one row per completed v2 form.

| Column | Type | Index / constraint | Purpose |
|---|---|---|---|
| `id` | bigint | PK | |
| `public_id` | `uuid` | **unique** | Used in public URLs instead of `id` |
| `enrolment_id` | FK `enrolments`, restrictOnDelete | **unique** | One submission per enrolment |
| `order_id` | FK `orders` nullable, nullOnDelete | index | |
| `student_id` | FK `students` nullable, nullOnDelete | index | |
| `course_id` | FK `courses` nullable, nullOnDelete | | Local course, for display only |
| `rto_course_id` | `string(50)` | | Snapshot from the enrolment at submit time |
| `rto_plan_id` | `string(50)` | | Same |
| `client_submission_id` | `uuid` | **unique** | Generated by the browser on page load; makes submit idempotent |
| `form_data` | `text` (cast `encrypted:array`) | | Validated form fields, plus an `identity` snapshot (`first_name`, `last_name`, `email`) copied from `Student` at submit. These are the values the student confirmed, and the only source for the name and email sent to RTO Data. Encrypted at rest. |
| `usi_provided` | `boolean` | index | False when the optional USI was left blank (Q10). Stored outside `form_data` so Filament can filter on it. |
| `expected_documents` | `json` | | Document types the browser said it would upload, e.g. `["id_document","vet_transcript"]` |
| `upload_token_hash` | `char(64)` | index | sha256 of the upload token. The plain token is never stored. |
| `upload_token_expires_at` | `timestamp` | | `submitted_at` + 24 h |
| `submitted_at` | `timestamp` | | |
| `finalised_at` | `timestamp` nullable | | Set by the beacon |
| `status` | `string(30)` | index | §4 |
| `resume_status` | `string(30)` nullable | | Set when entering `needs_review` |
| `status_changed_at` | `timestamp` | | |
| `claimed_at` | `timestamp` nullable | | Set when entering an in-progress status. Used for stuck detection. |
| `step_attempts` | `unsignedSmallInteger` default 0 | | Attempts at the current step; reset on each forward transition |
| `next_attempt_at` | `timestamp` nullable | | Backoff gate |
| `enrol_calls` | `unsignedSmallInteger` default 0 | | Total `/enrol` HTTP calls ever made for this row (audit and Resume warning) |
| `hold_email` | `string(100)` | | `processing{id}@hold.invalid`. Sent on enrol as `email` and `other_email` only. |
| `rtodata_enrolment_id` | `unsignedBigInteger` nullable | **unique** | Saved in the same UPDATE that sets `enrolled` |
| `rtodata_learner_id` | `unsignedBigInteger` nullable | index | Same |
| `rtodata_course_code` | `string(50)` nullable | | Same |
| `rtodata_course_title` | `string(255)` nullable | | Same |
| `enrolled_at` | `timestamp` nullable | | |
| `documents_done_at` | `timestamp` nullable | | |
| `contact_updated_at` | `timestamp` nullable | | |
| `email_template_id` | FK `enrolment_email_templates` nullable, nullOnDelete | | Template actually used |
| `confirmation_sent_at` | `timestamp` nullable | | |
| `completed_at` | `timestamp` nullable | | |
| `last_error` | `string(1000)` nullable | | Summary only: no bodies, no PII, no credentials |
| `last_error_at` | `timestamp` nullable | | |
| `review_reason` | `string(50)` nullable | | Machine code: `enrol_ambiguous`, `enrol_refused`, `mapping_error`, `no_email_template`, `stuck`, `step_exhausted` |
| `review_alerted_at` | `timestamp` nullable | | Alert is sent once per entry into `needs_review` |
| `timestamps` | | | |

Composite indexes: `(status, next_attempt_at)` for the sweep's due query, and `(status, claimed_at)` for stuck detection.

**`enrolment_v2_documents`**: one row per uploaded file.

| Column | Type | Index | Purpose |
|---|---|---|---|
| `id` | bigint | PK | |
| `submission_id` | FK `enrolment_v2_submissions`, cascadeOnDelete | | |
| `type` | `string(30)` | **unique (`submission_id`, `type`)** | `id_document` or `vet_transcript` |
| `original_name` | `string(255)` | | Shown in admin only. Sanitised. |
| `extension` | `string(10)` | | Of the **stored** file, from the detected type (`jpg` after a WEBP conversion), not from the client name |
| `mime` | `string(100)` | | Of the stored file, from magic bytes |
| `converted_from` | `string(10)` nullable | | `webp` when the upload was converted to JPEG (Risk 6). Null otherwise. |
| `size` | `unsignedInteger` | | Bytes of the stored file |
| `sha256` | `char(64)` | | sha256 of the bytes **as uploaded** (before any conversion). Detects an identical re-upload (idempotent 200). |
| `path` | `string(255)` nullable | | `local` disk, `enrolment-v2-documents/{submission public_id}/{type}.{ext}`. Null after deletion. |
| `status` | `string(20)` | index | `pending`, `sending`, `sent`, `retry`, `failed`, `purged` |
| `attempts` | `unsignedTinyInteger` default 0 | | |
| `next_attempt_at` | `timestamp` nullable | | |
| `claimed_at` | `timestamp` nullable | | |
| `last_error` | `string(1000)` nullable | | |
| `sent_at` | `timestamp` nullable | | |
| `failed_at` | `timestamp` nullable | | Set on entering `failed`. Starts the retention clock (§9.4). |
| `file_deleted_at` | `timestamp` nullable | | Set when the file is deleted after sending or by the purge |
| `timestamps` | | | |

**`enrolment_v2_events`**: append-only audit trail. Contains no PII.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `submission_id` | FK, cascadeOnDelete, index | |
| `document_id` | FK nullable, nullOnDelete | |
| `from_status`, `to_status` | `string(30)` nullable | |
| `step` | `string(30)` | `submit`, `enrol`, `documents`, `update`, `email`, `sweep`, `retention`, `admin` |
| `outcome` | `string(20)` | `success`, `refused`, `ambiguous`, `transient`, `exhausted`, `stuck`, `resumed`, `purged`, `info` |
| `attempt` | `unsignedSmallInteger` nullable | |
| `http_status` | `unsignedSmallInteger` nullable | |
| `message` | `string(1000)` nullable | Error summary, e.g. "HTTP 500, non-JSON body (text/html, 4 312 bytes)" |
| `actor` | `string(50)` | `web`, `job`, `sweep`, `retention`, `admin:{user_id}` |
| `created_at` | `timestamp` index | |

**`enrolment_client_events`**: browser-side failure log.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `enrolment_id` | FK nullable, nullOnDelete, index | Resolved from the enrolment token |
| `submission_id` | FK nullable, nullOnDelete, index | When known |
| `event` | `string(40)` | `file_unreadable`, `file_rejected`, `upload_failed`, `upload_retry`, `submit_failed`, `finalise_failed` |
| `document_type` | `string(30)` nullable | |
| `file_size` | `unsignedInteger` nullable | |
| `file_extension` | `string(10)` nullable | |
| `reason` | `string(255)` nullable | e.g. `magic_mismatch`, `too_large`, `NotReadableError`, `HTTP 413` |
| `online` | `boolean` nullable | `navigator.onLine` |
| `visibility` | `string(20)` nullable | `document.visibilityState` |
| `attempt` | `unsignedTinyInteger` nullable | |
| `user_agent` | `string(255)` nullable | |
| `created_at` | `timestamp` index | |

No file names are logged: they can contain the student's name.

**`enrolment_email_templates`**

| Column | Type | Index | Notes |
|---|---|---|---|
| `id` | bigint | PK | |
| `rto_course_id` | `string(50)` | **unique (`rto_course_id`, `rto_plan_id`)** | Keyed on RTO Data IDs, the same values sent to enrol. There is no default template (Q7). |
| `rto_plan_id` | `string(50)` | | |
| `course_id` | FK `courses` nullable, nullOnDelete | | Optional link, to help admins pick |
| `course_title` | `string(255)` | | Fills `{course_title}` |
| `subject` | `string(255)` | | Placeholders allowed |
| `body_html` | `longText` | | Filament RichEditor. Placeholders `{first_name}` `{last_name}` `{course_title}`. |
| `attachments` | `json` nullable | | Up to 3 paths on the `local` disk under `enrolment-email-attachments/` |
| `attachment_names` | `json` nullable | | Original names (Filament `storeFileNamesIn`) |
| `is_active` | `boolean` default true | | An inactive template counts as missing |
| `timestamps` | | | |

**`enrolment_settings`**: single row, same pattern as `HomepageSetting::instance()` (`app/Models/HomepageSetting.php:93-102`).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `alert_emails` | `json` nullable | List of addresses. Falls back to `config('enrolment.alerts.emails')` when empty. |
| `last_sweep_at` | `timestamp` nullable | Heartbeat written by the sweep. Shown on the settings page and read by `GET /health/enrolments` (§3). |
| `timestamps` | | |

### 1.3 How "one submission per enrolment" is guaranteed

- **Database:** `enrolment_v2_submissions.enrolment_id` is **unique**, and so is `client_submission_id`.
- **Across flows:** the submit transaction locks the `enrolments` row (`lockForUpdate`) and checks `enrolment_completed_at IS NULL` before inserting. v1 sets the same column, so whichever flow commits first wins. The v2 controller also refuses if a v1 `enrolment_submissions` row exists.

### 1.4 One-off data migration: scrub v1 API data (Q9)

**`2026_10_20_000010_scrub_v1_enrolment_api_data`**. Data only, no schema change. `down()` is a no-op, since the removed data can't be restored. Rows are processed in chunks of 200 with the query builder and `json_decode` in PHP, not database JSON functions, so the migration runs on both MySQL and SQLite.

**`enrolment_submissions`:**
- `api_response_payload`: remove the `request_body` key, which is where v1 stored `public_key` (`EnrolmentApiService.php:87, 169`). Also remove any other `public_key` key at any depth.
- `api_request_payload`: remove any `public_key` key at any depth. Today this column holds `buildPayload()`, which has no `public_key`, so this is a defensive no-op. See §10.
- Rows that don't parse as JSON are left unchanged and counted in the migration output.

**`integration_logs`** where `service = 'enrolment_api'`:

| Column | After the scrub |
|---|---|
| `request_payload` | Only these keys are kept: `submission_id`, `attempt`, `api_enabled`, `api_status`, `external_reference`. Everything else is dropped, including the form data, document paths and `public_key`. If nothing is left, the value becomes `{"scrubbed":true}`. |
| `response_payload` | Only `message`, `submission_id` and `external_reference` are kept. The RTO Data `response`, which can echo learner data, is dropped. |
| `error_message` | Cut at the first `: {` or `: [`, so `RTO enrol API returned failure: {…json…}` becomes `RTO enrol API returned failure`. |

The migration writes one summary line to the console with the row counts per table. It logs no row contents.

---

## 2. Classes

### HTTP

| Class | Responsibility |
|---|---|
| `App\Http\Controllers\Enrolment\ShowEnrolmentFormController` | Replaces the `GET /enrol/{token}` closure. Validates the token as today, asks `EnrolmentFlowResolver` for the flow, and renders the v1 view (same data as today) or the v2 view. |
| `…\Enrolment\StoreSubmissionController` | Submits v2 fields (JSON). In one transaction: locks the enrolment, inserts the submission with the identity snapshot, writes the validated DOB to `students.date_of_birth`, and marks the link used. Handles idempotency, returns `public_id` and the upload token, and dispatches the job after commit. |
| `…\Enrolment\StoreDocumentController` | Receives one document, authenticated by the upload token. Re-verifies the file, converts WEBP to JPEG (`ImageNormaliser`), and stores it as a `pending` document row. |
| `…\Enrolment\FinaliseSubmissionController` | Beacon target. Sets `finalised_at` and dispatches the job. Always returns 204. |
| `…\Enrolment\StoreClientEventController` | Records a browser failure event. |
| `App\Http\Controllers\Health\EnrolmentHealthController` | `GET /health/enrolments`, for an external uptime monitor (Risk 18). Returns 200 when `last_sweep_at` is within `enrolment.health.max_sweep_age_minutes`, otherwise 503 (also when it is null). Body: `{"status":"ok"\|"stale","sweep_age_seconds":N}`. Nothing else is exposed. |
| `App\Http\Requests\Enrolment\StoreSubmissionRequest` | All form rules (§5.2), with conditional rules and an `after()` hook for cross-field checks. |
| `…\StoreDocumentRequest` | `type`, `file` (mimes, size), `sha256`. Magic-byte check in `after()`. |
| `…\FinaliseSubmissionRequest` | Upload token plus the list of document types the browser actually uploaded. |
| `…\StoreClientEventRequest` | Enum and length rules for the event payload. |
| `App\Http\Middleware\AuthenticateUploadToken` | Resolves `{submission:public_id}`, compares `hash('sha256', token)` with `upload_token_hash` using `hash_equals`, and checks expiry. Returns 403 or 410. |
| `App\Rules\Usi` | USI format (`^[2-9A-HJ-NP-Z]{10}$`) plus the official check-character algorithm. Applied only when a USI is entered (Q10). |

### Domain and services

| Class | Responsibility |
|---|---|
| `App\Enrolment\EnrolmentFlowResolver` | Decides v1 or v2 for an enrolment: global switch, admin-only switch, course/plan allow-list, RTO IDs present, and no schedule (§9.1). Logs a warning when it refuses v2 because `rto_schedule_id` is set. |
| `App\Enrolment\SubmissionStatus` (enum) | The statuses, plus `isResting()`, `isInProgress()`, `next()` and `previousResting()`. |
| `App\Enrolment\DocumentStatus`, `DocumentType` (enums) | Document lifecycle and allowed types with their permitted formats. |
| `App\Enrolment\SubmissionStateMachine` | The only writer of `status`. Methods: `claim(from, to)` (atomic conditional UPDATE), `advance()`, `releaseForRetry()`, `toNeedsReview(reason, resumeStatus)`, `resume(adminUser)`. Writes `enrolment_v2_events`. |
| `App\Enrolment\Steps\EnrolStep` | One `/enrol` call. Classifies the outcome and saves the IDs in the same UPDATE that sets `enrolled`. |
| `App\Enrolment\Steps\SendDocumentsStep` | Sends **one** due document per run, so each run makes at most one HTTP call. Moves to `documents_done` when no document is pending or retrying. Raises missing-document alerts. |
| `App\Enrolment\Steps\UpdateContactStep` | `/update` with exactly `email` and `other_email` (§6). `contact_email` is never sent. |
| `App\Enrolment\Steps\SendConfirmationStep` | Resolves the template, renders it, sends the email. |
| `App\Enrolment\DocumentInspector` | Magic-byte detection for PDF, JPEG, PNG and WEBP, a zero-byte check and a size check. Shared by the request and the step's pre-send check. |
| `App\Enrolment\ImageNormaliser` | Converts an accepted WEBP to JPEG with GD before storage (Risk 6): reads the dimensions with `getimagesize()` first and rejects anything over `enrolment.v2.webp_max_megapixels`, refuses animated WEBP, flattens transparency onto white, and writes `imagejpeg` at quality 85. Other types pass through unchanged. |
| `App\Enrolment\DocumentPurger` | Applies the retention rule (§9.4): deletes the file, sets `path = null`, `file_deleted_at` and status `purged`, and writes one `enrolment_v2_events` row per document. |
| `App\Services\RtoData\RtoDataClient` | The only class that talks to RTO Data for v2. It holds the credentials, builds the bodies (`values` as an `http_build_query` string), applies timeouts, and returns an `RtoDataResult`. Never logs or returns credentials. |
| `App\Services\RtoData\RtoDataResult` | Value object: `kind` (`success`, `refused`, `ambiguous`), `httpStatus`, the parsed `data` array, and a short `summary` with no body. |
| `App\Services\RtoData\EnrolArgsMapper` | Pure function from a submission to the enrol args array (§6). No I/O, so it is unit-testable. |
| `App\Enrolment\ConfirmationEmailRenderer` | Replaces placeholders in the subject and body with **HTML-escaped** values. Rejects unknown placeholders when a template is saved. |
| `App\Enrolment\EnrolmentAlerter` | Sends `EnrolmentAlertMail` to the configured list. Deduplicates per submission and reason (`review_alerted_at`), and also handles document and queue-health alerts. |
| `App\Enrolment\IntegrationLogger` | Writes `integration_logs` rows for v2 with IDs, status, step and summary only (P21). |

### Jobs and scheduling

| Class | Responsibility |
|---|---|
| `App\Jobs\AdvanceEnrolmentSubmissionJob` | Runs **one** step for one submission, then re-dispatches itself if more work is due immediately. `$tries = 1`, `$timeout = 60`, `$failOnTimeout = true`, `ShouldBeUnique` (`uniqueId` = submission ID, `uniqueFor` = 120), middleware `WithoutOverlapping(id)->dontRelease()->expireAfter(120)`, and a `failed()` handler (§4.5). Queue `enrolments`. |
| `App\Console\Commands\SweepEnrolmentSubmissions` (`enrolments:sweep`) | Scheduled every minute with `withoutOverlapping(5)`. Dispatches due rows, detects stuck rows, picks up late documents, writes the `last_sweep_at` heartbeat, and raises a queue-health alert if the oldest `enrolments` job is waiting for more than 10 minutes. |
| `App\Console\Commands\PurgeEnrolmentDocuments` (`enrolments:purge-documents`) | Runs `DocumentPurger` once a day (§9.4). `--dry-run` prints counts only. |
| `routes/console.php` | The sweep, the daily purge and the cron-driven queue worker (§9.2). |

### Mail

| Class | Responsibility |
|---|---|
| `App\Mail\EnrolmentConfirmationMail` | Uses the rendered template HTML in a minimal branded layout (reuses `EmbedsAmsLogo`) and attaches up to 3 template files. |
| `App\Mail\EnrolmentAlertMail` | Plain alert: submission link, reason, step, last error summary. No PII apart from the student's name and order number, which admins already see. |

### Models

`EnrolmentV2Submission`, `EnrolmentV2Document`, `EnrolmentV2Event`, `EnrolmentClientEvent`, `EnrolmentEmailTemplate`, `EnrolmentSetting`. The submission model's `$hidden` includes `form_data` and `upload_token_hash`.

### Filament

| Class | Responsibility |
|---|---|
| `EnrolmentV2SubmissionResource` | List with filters by status, `needs_review`, date and **Missing USI** (`usi_provided = false`, Q10). Badge columns: status, step attempts, RTO enrolment and learner IDs, document summary (e.g. "2/2 sent"), a "Missing USI" badge, last error. View page with infolist sections for overview, RTO Data, documents (relation table with per-document status, attempts and error), timeline (events), and form data (decrypted, behind a "Show personal data" toggle). No create, edit or delete. |
| ↳ Action **Resume** | Visible on `needs_review`. Confirmation modal. When `resume_status` is `received` and `enrol_calls > 0`, it shows a red warning: "An enrol call was already made and may have created an enrolment in RTO Data. Check RTO Data for {first} {last}, DOB {dob} before continuing." The admin must type `ENROL AGAIN`. |
| ↳ Action **Link existing RTO Data enrolment** | Visible on `needs_review` with `resume_status = received`. Takes an enrolment ID and a learner ID, sets `enrolled` without calling `/enrol`. This is the safe way out of an ambiguous enrol (an addition to the brief; see §10). |
| ↳ Action **Retry document** | On a `failed` document whose file still exists (not yet purged). Resets attempts, clears `failed_at`, sets `retry`, dispatches the job. |
| ↳ Action **Resend confirmation** | On `complete`. Sends the email again and logs the event. |
| `EnrolmentEmailTemplateResource` | CRUD for templates. Course and plan pickers with free text for RTO IDs, RichEditor, `FileUpload::multiple()->maxFiles(3)->disk('local')->directory('enrolment-email-attachments')->maxSize(5120)`, and a placeholder hint. A "Send test to me" action renders with dummy data. The list page shows a warning listing **allow-listed pairs without an active template** (Q7). |
| `EnrolmentSettings` page | Alert email list (repeater of `email` fields), last sweep heartbeat, queue backlog age, read-only view of the v2 config (switches, allow-list, retention days). |
| `StudentForm` (existing) | Q2: `first_name` and `last_name` are `->disabled()` with a helper text ("Locked: this name has been submitted for enrolment") once any `enrolment_v2_submissions` row exists for an enrolment of that student. Disabled fields are not saved by Filament. `StudentResource` also refuses a name change on save in that case, so the lock does not depend on the UI alone. Email and phone are unchanged. |
| `OrdersTable` (existing) | P8: the resend and retry actions filter enrolments with `whereNull('enrolment_completed_at')`. The notification text no longer claims SMS is queued when it isn't (audit §6). |

---

## 3. Routes

| Method / URI | Name | Controller | Middleware | Throttle |
|---|---|---|---|---|
| `GET /enrol/{token}` | `enrol.show` | `ShowEnrolmentFormController` | `web` | `throttle:enrol-view` (30/min per IP) |
| `POST /enrol/{token}` | `enrol.submit` | v1 closure, **unchanged** | `web` | none (unchanged) |
| `POST /enrol/{token}/submission` | `enrol.v2.submit` | `StoreSubmissionController` | `web` | `throttle:enrol-submit` (6/min per token, 20/min per IP) |
| `POST /enrolment-submissions/{submission:public_id}/documents` | `enrol.v2.documents` | `StoreDocumentController` | `web`, `AuthenticateUploadToken` | `throttle:enrol-upload` (20/min per submission) |
| `POST /enrolment-submissions/{submission:public_id}/finalise` | `enrol.v2.finalise` | `FinaliseSubmissionController` | `web`, `AuthenticateUploadToken` | `throttle:enrol-upload` |
| `POST /enrolment-client-events` | `enrol.v2.client-events` | `StoreClientEventController` | `web` | `throttle:enrol-events` (30/min per IP) |
| `GET /enrolment-completed`, `GET /enrolment-not-successful` | existing | existing | — | — |
| `GET /health/enrolments` | `health.enrolments` | `EnrolmentHealthController` | none (no session, no auth) | `throttle:60,1` |

- The rate limiters are defined in `AppServiceProvider::boot()` with `RateLimiter::for(...)`.
- **CSRF:** the three v2 POST endpoints are excluded from CSRF (`$middleware->validateCsrfTokens(except: [...])` in `bootstrap/app.php`). Each is already authenticated by a secret in the URL or header (enrolment token or upload token). A CSRF token adds no protection here, and it would cause 419 errors when a student leaves the long form open past `SESSION_LIFETIME`. `sendBeacon` also cannot set headers. Accepted (Risk 8).
- **Health:** `/health/enrolments` sits beside Laravel's existing `/up` (`bootstrap/app.php:12`). `/up` only proves PHP boots; this one proves the scheduler is running.
- The upload token is sent in the header `X-Upload-Token`. The beacon cannot send headers, so it sends the token in the form body instead.
- `submit` and `documents` responses are JSON. Errors use the standard 422 validation shape, plus 403 (bad token), 409 (already submitted by another client, or document already sent) and 410 (link or upload window expired).

---

## 4. State machine

### 4.1 Statuses

| Status | Kind | Meaning |
|---|---|---|
| `received` | resting | Saved. Waiting for the enrol step. |
| `enrolling` | in progress | `/enrol` call in flight. **Never auto-resumed.** |
| `enrolled` | resting | RTO enrolment exists and IDs are saved. Waiting for documents. |
| `sending_documents` | in progress | One `/receive` call in flight |
| `documents_done` | resting | All expected documents are sent, failed or missing |
| `updating` | in progress | `/update` call in flight |
| `updated` | resting | Real email set in RTO Data |
| `emailing` | in progress | Confirmation email being sent |
| `complete` | terminal | Done |
| `needs_review` | parked | Requires an admin. `resume_status` holds the resting status to restart from. |

Every move into an in-progress status uses a conditional update, and the step proceeds only when exactly one row is affected:

```sql
UPDATE enrolment_v2_submissions
SET status = :to, claimed_at = NOW(), status_changed_at = NOW()
WHERE id = :id AND status = :from AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
```

This conditional update is what makes the process correct. `ShouldBeUnique` and `WithoutOverlapping` only avoid wasted work.

### 4.2 Failure classification (`RtoDataClient`)

| Kind | Condition |
|---|---|
| `success` | HTTP 2xx, body parses as a JSON object, and `success` is `1` or `true`. For enrol, it also requires a numeric `enrolment_id` and `enrolment_data.learner_id`; if either is missing, the result is `ambiguous`. For receive, the success test stays the raw-body `"success":1` match, as proven. |
| `refused` | HTTP 2xx or 4xx, body is a JSON object, and `success` is falsy. This is a clean, parsed refusal. |
| `ambiguous` | Anything else: `ConnectionException` (connect or read timeout), HTTP 5xx, a non-JSON body (including the HTML 500 page), JSON that isn't an object, or a redirect left unresolved. |

For enrol, `ambiguous` means "the enrolment may exist". For the other steps, `ambiguous` and `refused` are both handled as retryable failures.

### 4.3 Transitions

Backoff schedule for `step_attempts` 1–5: **1, 2, 5, 15, 30 minutes**. "Max" means 5 attempts. The enrol step is the exception: a clean refusal is retried only `enrolment.v2.enrol_refusal_retries` times (default **1**, Risk 4), and an ambiguous result is never retried.

| # | From → To | Performed by | Trigger | On success | On failure |
|---|---|---|---|---|---|
| T1 | *(none)* → `received` | `StoreSubmissionController` | Valid submit | Commit, then dispatch the job | Validation 422; duplicate 200 (same client) or 409 (other client) |
| T2 | `received` → `enrolling` | Job (claim) | Due | Call `/enrol` | Claim lost → no-op |
| T3 | `enrolling` → `enrolled` | `EnrolStep` | `success` | **One UPDATE** sets status, `rtodata_enrolment_id`, `rtodata_learner_id`, `rtodata_course_code`, `rtodata_course_title`, `enrolled_at`, and resets `step_attempts` | — |
| T4 | `enrolling` → `received` | `EnrolStep` | `refused`, and refusals so far ≤ `enrol_refusal_retries` (default 1) | — | `step_attempts++`, `next_attempt_at` = backoff (1 min for the first retry), `last_error` = RTO message (truncated) |
| T5 | `enrolling` → `needs_review` (resume `received`) | `EnrolStep` | `ambiguous` (any attempt), or a refusal once the retries are used up (by default the 2nd refusal) | — | `review_reason` = `enrol_ambiguous` or `enrol_refused`; alert. **Never retried automatically.** |
| T6 | `enrolled` → `sending_documents` | Job (claim) | `finalised_at` set **or** `submitted_at` + 15 min ≤ now, **and** at least one document is due | Send one document | — |
| T6a | `enrolled` → `documents_done` | Job | Same wait condition, and no document is due (none uploaded, or all `sent`/`failed`/`purged`) | Raise a missing-document alert for each type in `expected_documents` with no row. No document is mandatory (Q13), so a submission with no documents at all proceeds without an alert unless the browser declared one. | — |
| T7 | `sending_documents` → `enrolled` | `SendDocumentsStep` | One document processed | Document → `sent`, delete the file, set `file_deleted_at`. Re-dispatch immediately if another document is due. | Document `attempts++`. Below 5 → document `retry` with backoff. At 5 → document `failed` (sets `failed_at`) plus alert, and the enrolment continues. A `/receive` that timed out may have been stored by RTO Data and will be sent again; a duplicate document is accepted (Risk 5). |
| T8 | `documents_done` → `updating` | Job (claim) | Due | Call `/update` | — |
| T9 | `updating` → `updated` | `UpdateContactStep` | `success` | Set `contact_updated_at`, reset attempts | — |
| T10 | `updating` → `documents_done` | `UpdateContactStep` | `refused` or `ambiguous`, attempts < 5 | — | Backoff (safe to repeat) |
| T11 | `updating` → `needs_review` (resume `documents_done`) | `UpdateContactStep` | Attempt 5 fails | — | `step_exhausted`; alert |
| T12 | `updated` → `emailing` | Job (claim) | Due | Resolve the template | — |
| T13 | `emailing` → `needs_review` (resume `updated`) | `SendConfirmationStep` | No active template for (`rto_course_id`, `rto_plan_id`) | — | `no_email_template`; alert. Never skipped. |
| T14 | `emailing` → `complete` | `SendConfirmationStep` | `Mail::send` returns | Set `confirmation_sent_at`, `completed_at` | — |
| T15 | `emailing` → `updated` | `SendConfirmationStep` | Mail exception, attempts < 5 | — | Backoff |
| T16 | `emailing` → `needs_review` (resume `updated`) | `SendConfirmationStep` | Attempt 5 fails | — | `step_exhausted`; alert |
| T17 | any in-progress → `needs_review` | Sweep | `claimed_at` older than 30 min | — | `review_reason = stuck`, resume = the preceding resting status (for `enrolling` that is `received`, which carries the Resume warning); alert |
| T18 | `needs_review` → `resume_status` | Admin **Resume** | Confirmed | Resets `step_attempts`, clears `next_attempt_at`, `review_*` and `last_error`; dispatches | — |
| T19 | `needs_review` (resume `received`) → `enrolled` | Admin **Link existing** | IDs entered | Saves the IDs; no `/enrol` call | — |
| T20 | late document | Sweep / `StoreDocumentController` | A document is uploaded while the submission is past `enrolled` | Document rows with status `pending`/`retry` whose submission has `rtodata_enrolment_id` are sent in any non-`enrolling` status, including `complete` and `needs_review`. The document step claims **the document row** (`UPDATE … SET status='sending' WHERE id=? AND status IN ('pending','retry')`). The submission status does not change. | As T7 |

Document sends in T20 do not move the submission status. This avoids reopening a `complete` row.

### 4.4 The job

`AdvanceEnrolmentSubmissionJob::handle()`:

1. Reload the submission.
2. Pick the step for its resting status. If a pre-condition is unmet (backoff, or the document wait), return.
3. Claim the row (or the document row).
4. Run the step, which makes **at most one** external call: HTTP timeout 30 s plus 10 s connect, or one SMTP send.
5. Apply the transition.
6. If the new resting status is due now, dispatch itself again.

**Timeouts:** the longest run is about 40 s of HTTP plus a few database writes. `$timeout = 60`. Set `DB_QUEUE_RETRY_AFTER` to **120**, which is above the job timeout; the default of 90 would also work, but 120 leaves headroom. Set the worker to `--timeout=75`.

### 4.5 Failure inside the job itself

| Situation | Handling |
|---|---|
| Exception thrown by our own code mid-step, e.g. a DB error after a successful `/enrol` | The step catches `Throwable` *after* the external call and treats it as **ambiguous for enrol** (T5) or transient for the other steps. The IDs-save UPDATE is the first statement after a successful enrol response. |
| Worker killed by the timeout or the process dying | `failed()` runs (`$failOnTimeout = true`). If the row is still in an in-progress status, `enrolling` → `needs_review` (`enrol_ambiguous`) and any other in-progress status → its resting status with backoff. If `failed()` itself never runs (server reboot), T17 catches it after 30 minutes. |
| Queue worker not running | Rows stay `received` or resting. The sweep keeps dispatching (unique jobs stop pile-up). After 10 minutes of backlog the sweep raises a queue-health alert, at most one per hour. |
| Scheduler not running | The app cannot alert on this itself, because the alert would come from the scheduler. `GET /health/enrolments` returns 503 once `last_sweep_at` is more than 5 minutes old, so an external uptime monitor can alert (Risk 18). The Filament settings page also shows `last_sweep_at`. |

### 4.6 What is stored where

**Personal data** lives only in:
- `enrolment_v2_submissions.form_data` (encrypted);
- the uploaded files, until they are accepted or purged (§9.4);
- `students` (the v2 submit writes `date_of_birth`).

**Not stored anywhere:** credentials, request bodies, response bodies.
- `last_error` and event `message` hold summaries such as "HTTP 500, non-JSON (text/html, 4 312 bytes)".
- For a `refused` result, RTO Data's `message` field is stored, capped at 500 characters. It may echo a field name, but it never contains the request.
- `integration_logs` rows for v2 contain `submission_id`, `step`, `outcome`, `http_status` and `rtodata_enrolment_id` only (P20, P21).

---

## 5. Browser and validation

### 5.1 Browser behaviour (v2 view)

- **Page load:** generate `client_submission_id` with `crypto.randomUUID()` and keep it in `sessionStorage`, so a reload reuses it.
- **Identity (Q2):** first name, last name and email are shown as read-only text from `Student`. They are not form inputs, and the server ignores them if sent. Date of birth is a required input (`<input type="date">`, `max` = yesterday, `min` = `1900-01-02`).
- **File chosen:**
  1. `await file.arrayBuffer()`. On failure (`NotReadableError` from a OneDrive or iCloud placeholder, or a permissions error), show on the field: "We couldn't read this file. Save it to your Desktop or Downloads folder and choose it again." Then log `file_unreadable`.
  2. Check the extension: `pdf`, `jpg`, `jpeg`, `png`, `webp`. A `heic` or `heif` file gets its own message: "iPhone HEIC photos can't be accepted. Choose the photo from your Photos app, or take a screenshot of it and upload that." It is logged as `file_rejected` with reason `heic`.
  3. Check the size: more than 0 and at most 8 MB.
  4. Check the magic bytes: PDF `25 50 44 46 2D`; JPEG `FF D8 FF`; PNG `89 50 4E 47 0D 0A 1A 0A`; WEBP `52 49 46 46 ?? ?? ?? ?? 57 45 42 50`.
  5. On any failure, show an inline error on that field and log `file_rejected` with the reason.
  6. On success, keep `{type, name, bytes: ArrayBuffer, mime, sha256}` in memory. `sha256` comes from `crypto.subtle.digest`.
- **Submit:**
  1. Run HTML5 validation.
  2. Show a confirmation modal with the locked first and last name and the entered DOB (formatted `d MMMM yyyy`, so a day/month swap is visible): "Your name and date of birth will appear on your certificate exactly as shown. They can't be changed after you submit." The student chooses "Go back" or "Confirm and submit". The modal also says: "If your name is wrong, don't submit. Contact us and we'll correct it first." Staff can still edit the name at that point, because no v2 submission exists yet. Nothing is sent until the student confirms.
  3. POST the fields as JSON, with `expected_documents` set to the types held in memory.
  4. On a network error, retry 3 times (1 s, 3 s, 6 s). This is safe because the submit is idempotent through `client_submission_id`.
  5. On 422, show the field errors and stop.
  6. Then, for each document in sequence, POST `multipart/form-data` with `file = new Blob([bytes], {type: mime})`, the original name, `type` and `sha256`. Retry network errors and 5xx responses up to 3 times (2 s, 5 s, 10 s). Never retry a 4xx. Show progress.
  7. A document that still fails is logged as `upload_failed`. The student sees "We couldn't upload your {document}. Your enrolment has been received; our team will contact you."
  8. Then `navigator.sendBeacon(finaliseUrl, FormData{upload_token, uploaded:[types]})`. If `sendBeacon` returns false, fall back to `fetch(..., {keepalive: true})`.
  9. Redirect to `/enrolment-completed`.
- The original `File` object is never uploaded.

### 5.2 `StoreSubmissionRequest` rules (fixes P15 and P17)

Only `validated()` is used. Identity fields, `code`, `plan` and any other keys are ignored. First name, last name and email always come from `Student`. They are snapshotted into `form_data.identity` inside the submit transaction, and the validated `date_of_birth` is written to `students.date_of_birth` in the same transaction (Q2).

| Field | Rules |
|---|---|
| `client_submission_id` | `required`, `uuid` |
| `title` | `nullable`, `in:Mr,Ms,Mrs,Miss` |
| `date_of_birth` | `required`, `date_format:Y-m-d`, `before:today`, `after:1900-01-01`. No minimum age (Q11). |
| `gender` | `required`, `in:F,M,X,@` |
| `city_of_birth` | `nullable`, `string`, `max:50` |
| `country_of_birth` | `required`, `in:` keys of `config('enrolment_countries')` |
| `usi` | `nullable`; trimmed, spaces removed and uppercased in `prepareForValidation` (blank becomes `null`); `new Usi` when present (Q10). Sets `usi_provided`. |
| `building_name` | `nullable`, `string`, `max:50` |
| `unit_number` | `nullable`, `string`, `max:30` |
| `street_number` | `required`, `string`, `max:15` |
| `street_name` | `required`, `string`, `max:70` |
| `suburb` | `required`, `string`, `max:50` |
| `state` | `required`, `in:NSW,VIC,QLD,SA,WA,TAS,NT,ACT` |
| `postcode` | `required`, `regex:/^\d{4}$/` |
| `postal_address_type` | `required`, `in:same,different` |
| `postal_box` | `nullable`, `max:22`; `required_without:postal_street_name` when `different` |
| `postal_building_property` | `nullable`, `max:50` |
| `postal_unit_flat` | `nullable`, `max:30` |
| `postal_street_number` | `nullable`, `max:15` |
| `postal_street_name` | `nullable`, `max:70`; `required_without:postal_box` when `different` |
| `postal_suburb` | `required_if:postal_address_type,different`, `max:50` |
| `postal_state` | `required_if:postal_address_type,different`, `in:01,02,03,04,05,06,07,08,09,99` |
| `postal_postcode` | `required_if:postal_address_type,different`, `regex:/^\d{4}$/` |
| `mobile_phone` | `required`, `regex:/^04\d{8}$/` |
| `main_language_home` | `required`, `in:` keys of `config('enrolment_languages')` |
| `school_level` | `required`, `in:02,08,09,10,11,12,@@` |
| `currently_at_school` | `required`, `in:Y,N,@` |
| `other_qualifications` | `required`, `in:Yes,No` |
| `qualification_level` | `required_if:other_qualifications,Yes`, `array`, `min:1`; each `in:008,410,420,511,514,521,524,990`; `distinct` |
| `study_reason` | `required`, `in:01,02,03,04,05,06,07,08,11,12,13,@@` |
| `labour_force` | `required`, `in:01,02,03,04,05,06,07,08,@@` |
| `atsi_status` | `required`, `in:1,2,3,4,@` |
| `disability` | `required`, `in:Y,N` |
| `disability_type` | `required_if:disability,Y`, `array`, `min:1`; each `in:11,…,19,99`; `after()`: `99` must be the only selection |
| `individual_needs` | `required`, `in:Yes,No` |
| `individual_needs_specify` | `required_if:individual_needs,Yes`, `string`, `max:2000` |
| `agree_handbook`, `agree_terms`, `agree_privacy`, `agree_data_provision`, `agree_record_access`, `agree_declaration` | `accepted` |
| `expected_documents` | `present`, `array`; each `in:id_document,vet_transcript`; `distinct` |

**Form value changes** (the v2 view only):
- Checkbox and option values become the raw codes. Qualification values change from `prior_achievement[all][008]` to `008`, and ATSI options change from labels to `1`–`4` and `@`.
- New options: ATSI "Yes, both Aboriginal and Torres Strait Islander" (`3`) and "Not stated" (`@`); labour force uses the full AVETMISS list (§6); study reason gains "Not stated" (`@@`).

### 5.3 `StoreDocumentRequest` (fixes P16)

| Field | Rules |
|---|---|
| `type` | `required`, `in:id_document,vet_transcript` |
| `file` | `required`, `file`, `min:1` (KB; rejects empty files), `max:8192`, `mimes:pdf,jpg,jpeg,png,webp`, `mimetypes:application/pdf,image/jpeg,image/png,image/webp`. HEIC/HEIF gets a dedicated 422 message matching the browser's. |
| `sha256` | `required`, `regex:/^[a-f0-9]{64}$/` |
| `after()` | `DocumentInspector` magic-byte check must agree with the declared type; the server-computed sha256 must match the client's (detects truncation) |

**Controller behaviour:**
- **WEBP (Risk 6):** after validation, `ImageNormaliser` converts the file to JPEG before it is stored. The stored row has `extension = jpg`, `mime = image/jpeg` and `converted_from = webp`, so `/receive` only ever sees PDF, JPEG or PNG. The `sha256` column keeps the hash of the uploaded WEBP, so an identical replay is still recognised. If the conversion fails (too many pixels, animated, or a corrupt file), the response is 422: "We couldn't process this image. Please upload it as a JPG, PNG or PDF."
- **Window:** the upload window has expired (`upload_token_expires_at`) → 410.
- **Existing row for (`submission`, `type`)**, checked in this order:
  - Same sha256 → 200 (idempotent replay).
  - Existing row `pending` or `retry` → replace the file and reset attempts.
  - Existing row `sending` or `sent` → 409.
  - Existing row `failed` or `purged` → replace the file, reset to `pending` (clearing `failed_at`), and alert "replacement received".
- **Storage:** `local` disk, under the path in §1.2.

---

## 6. AVETMISS mapping after the fixes (`EnrolArgsMapper`)

Values are sent inside `values` (an `http_build_query` string). `null` values are removed before encoding, so nothing is silently dropped by PHP. Required-arg checks throw `MappingException`, which the step treats as a deterministic failure: `needs_review` with `review_reason = mapping_error`, and no HTTP call is made.

| Form field | Form values | RTO Data field | Transformation | Change from v1 |
|---|---|---|---|---|
| (identity snapshot) | — | `first_name` | `trim(form_data.identity.first_name)`, the name the student confirmed | Locked on the form (Q2) |
| (identity snapshot) | — | `last_name` | `trim(form_data.identity.last_name)` | Locked on the form (Q2) |
| `date_of_birth` | `Y-m-d` | `date_of_birth` | As validated. Entered by the student, not collected at checkout. | No `strtotime` |
| (enrolment) | — | `course_id` | `(int) enrolments.rto_course_id`; required | **Server-side only** (P11, P18) |
| (enrolment) | — | `plan_id` | `(int) enrolments.rto_plan_id`; required | **Server-side only** |
| `title` | Mr/Ms/Mrs/Miss | `salutation` | As-is; omitted if blank | Same |
| `gender` | F/M/X/@ | `gender` | As-is; required | **No default `M`** |
| `city_of_birth` | text | `town_of_birth` | As-is | Same |
| `country_of_birth` | SACC 4-digit | `country_of_birth` | As-is | Same |
| `building_name` | text | `building_property` | Trimmed | **New**; confirmed live (Q4) |
| `unit_number` | text | `unit_flat` | Trimmed | **New**; confirmed live (Q4) |
| `street_number` | text | `street_number` | Trimmed | Same |
| `street_name` | text | `street_name` | Trimmed | Same |
| `suburb` | text | `suburb` | Trimmed | Same |
| `state` | NSW…ACT | `state` | NSW `01`, VIC `02`, QLD `03`, SA `04`, WA `05`, TAS `06`, NT `07`, ACT `08` | Validation now guarantees a value |
| `postcode` | 4 digits | `postcode` | As-is | Validated |
| — | — | `country` | `1101` constant (the form only accepts Australian residential addresses) | Same; `address_country` input removed from the v2 view |
| (hold) | — | `email`, `other_email` | Both = `processing{submission_id}@hold.invalid` | **New** (Q3a) |
| — | — | `contact_email` | **Never sent**, on enrol or update, so an existing learner's emergency contact email is never overwritten or erased | **Removed** (Q3a) |
| `mobile_phone` | `04xxxxxxxx` | `mobile_phone` | As-is | Validated |
| `usi` | 10 chars or blank | `usi_number` | Uppercased; omitted when blank | Validated when present; optional (Q10) |
| `main_language_home` | ASCL 4-digit | `main_language` | As-is | No default |
| `school_level` | `02`,`08`–`12`,`@@` | `highest_school_level` | **String as-is** (`02`, not `2`) | **Fixed**; confirmed live (Q4) |
| `currently_at_school` | Y/N/@ | `at_school_flag` | As-is | Same |
| `other_qualifications` + `qualification_level[]` | Yes/No + codes | `prior_achievement_flag`, `prior_achievement[all][{code}]=1`, `prior_achievement[vic][{code}]=A` | Flag `Y` with entries when Yes, else `N` and no entries. The `[vic]` block is kept (Q5). | Same output; simpler input |
| `study_reason` | `01`–`08`,`11`,`12`,`13`,`@@` | `study_reason` | As-is | **New**; confirmed live (Q4) |
| `labour_force` | `01` Full-time employee · `02` Part-time employee · `03` Self-employed, not employing others · `04` Employer · `05` Employed, unpaid worker in a family business · `06` Unemployed, seeking full-time work · `07` Unemployed, seeking part-time work · `08` Not employed, not seeking employment · `@@` Not stated | `labour_force` | As-is | **Fixed list.** v1 labelled `06` "seeking work" and `07` "not seeking", which mis-codes `07`. |
| `atsi_status` | `1` Aboriginal · `2` Torres Strait Islander · `3` Both · `4` No · `@` Not stated | `indigenous_status` | As-is | **Options added** |
| `disability` | Y/N | `disability_flag` | As-is | Same |
| `disability_type[]` | `11`–`19`,`99` | `disability_type[11..99]` | All 10 keys as `1`/`0`, all zero when the flag is `N` | Same as v1 (Q6) |
| `individual_needs` + `_specify` | Yes/No + text | `individual_needs` | Text when Yes, else omitted | Empty string no longer sent |
| `postal_address_type` | same/different | `postal_flag` | `1`/`0` | Same |
| `postal_box` … `postal_postcode` | text / codes | `postal_box`, `postal_building_property`, `postal_unit_flat`, `postal_street_number`, `postal_street_name`, `postal_suburb`, `postal_state`, `postal_postcode` | Trimmed; only when `different` | Validated |
| — | — | `client_id` | **Not sent** | Removed (it was always empty) |
| Declarations | accepted | — | Stored in `form_data` only | Now required |
| (enrolment) | — | *(schedule)* | **Not sent** (Q3). v2 never serves an enrolment with a non-empty `rto_schedule_id` (§9.1). | Q3 |

**Update body** (`UpdateContactStep`):

| Key | Value |
|---|---|
| `type` | `"enrolment"` |
| `enrolment_id` | `(int) rtodata_enrolment_id` |
| `values` | `http_build_query(['email' => <real>, 'other_email' => <real>])`, i.e. `email=<real>&other_email=<real>` |

`<real>` is `form_data.identity.email`, lower-cased. `RtoDataClient` adds the credentials (`public_key`, `subdomain`), as on every call. No other keys are ever sent. In particular `contact_email` is absent, so the learner's existing contact email is left as it was (Q3a).

**Existing learners (Q3b):** when enrol returns a `learner_id` that RTO Data already had, this is treated as a normal success. There is no alert and no check against other submissions.

**Receive body:** unchanged from the proven shape. `name` = `{type}.{ext}`, e.g. `id_document.pdf`, so the RTO Data filename is meaningful and contains no PII. `ext` is the stored extension, so a converted WEBP arrives as `id_document.jpg`. `uri` = `data:{mime};base64,{…}`.

---

## 7. Configuration

**New file `config/enrolment.php`.** `.env` names are in brackets.

| Key | `.env` | Default | Purpose |
|---|---|---|---|
| `enrolment.v2.enabled` | `ENROLMENT_V2_ENABLED` | `false` | Global switch |
| `enrolment.v2.admin_only` | `ENROLMENT_V2_ADMIN_ONLY` | `true` | When true, v2 is served only to a logged-in Filament admin (Q1) |
| `enrolment.v2.pairs` | `ENROLMENT_V2_PAIRS` | `''` | Comma list of `rto_course_id:rto_plan_id`, e.g. `32:38,32:41`. `*` means all. |
| `enrolment.v2.document_wait_minutes` | `ENROLMENT_V2_DOCUMENT_WAIT_MINUTES` | `15` | |
| `enrolment.v2.stuck_minutes` | `ENROLMENT_V2_STUCK_MINUTES` | `30` | |
| `enrolment.v2.max_attempts` | — | `5` | All steps except enrol refusals |
| `enrolment.v2.enrol_refusal_retries` | `ENROLMENT_V2_ENROL_REFUSAL_RETRIES` | `1` | Retries after a clean enrol refusal before `needs_review` (Risk 4) |
| `enrolment.v2.backoff_minutes` | — | `[1,2,5,15,30]` | |
| `enrolment.v2.upload_window_hours` | `ENROLMENT_V2_UPLOAD_WINDOW_HOURS` | `24` | |
| `enrolment.v2.max_document_kb` | — | `8192` | Shared by the JS (rendered into the view) and the rules |
| `enrolment.v2.webp_max_megapixels` | — | `25` | Larger WEBP images are rejected before GD decodes them, to stay inside `memory_limit` (§10) |
| `enrolment.v2.document_retention_days` | `ENROLMENT_V2_DOCUMENT_RETENTION_DAYS` | `7` | Purge age for failed and idle documents (Q8, §9.4) |
| `enrolment.v2.hold_email_domain` | `ENROLMENT_V2_HOLD_EMAIL_DOMAIN` | `hold.invalid` | |
| `enrolment.v2.queue` | `ENROLMENT_V2_QUEUE` | `enrolments` | |
| `enrolment.health.max_sweep_age_minutes` | — | `5` | `/health/enrolments` returns 503 beyond this (Risk 18) |
| `enrolment.link.expires_days` | `ENROLMENT_LINK_EXPIRES_DAYS` | `30` | Replaces `CreateEnrolmentJob.php:187` |
| `enrolment.link.form_url` | `AMS_ENROLMENT_FORM_URL` | `null` | Replaces `env()` at `CreateEnrolmentJob.php:308` (P26) |
| `enrolment.alerts.emails` | `ENROLMENT_ALERT_EMAILS` | `''` | Comma list; fallback when the settings row is empty |
| `enrolment.alerts.queue_backlog_minutes` | — | `10` | |

**`config/services.php` additions:**

| Key | `.env` |
|---|---|
| `services.twilio.enabled` | `TWILIO_ENABLED` |
| `services.twilio.account_sid` | `TWILIO_ACCOUNT_SID` |
| `services.twilio.auth_token` | `TWILIO_AUTH_TOKEN` |
| `services.twilio.from` | `TWILIO_FROM_NUMBER` |
| `services.enrolment_api.*` | existing `ENROLMENT_API_*`, reused by `RtoDataClient` |

**Other changes:**
- `services.enrolment_api.origin` default: change `config('app.url')` to `env('APP_URL')` (P27).
- Remove the duplicate `ENROLMENT_API_ORIGIN` line from `.env`. This is a deploy note, not code.
- `config/queue.php` is unchanged; `DB_QUEUE_RETRY_AFTER=120` is set in `.env`.
- `env()` disappears from `SendEnrolmentSmsJob`, `CreateEnrolmentJob` and `SubmitEnrolmentToApiJob:66` (P26). The last is a one-line change to v1, made because P26 asks for no `env()` outside config.

---

## 8. Test plan

Conventions:
- SQLite in memory (existing `phpunit.xml`).
- `Http::preventStrayRequests()` in every RTO test.
- `Queue::fake()` and `Mail::fake()` where noted.
- `Storage::fake('local')`.
- `$this->travel()` for time.
- Factories are added for `Enrolment`, `Student`, `Order`, `EnrolmentV2Submission` and `EnrolmentEmailTemplate`.

### 8.1 Unit

| Test | Covers |
|---|---|
| `EnrolArgsMapperTest` | One case per row of §6: state codes; `highest_school_level` `02` stays `"02"`; `@@` passes through; `study_reason`, `building_property` and `unit_flat` present; gender has no default (`MappingException` if missing); ATSI `3` and `@`; disability flags all 10 keys, all zero for `N`, `99` exclusive; prior achievement `Y`/`N` shapes including the `[vic]` block; postal block only when `different`; hold emails use the submission ID and appear in `email` and `other_email` only; **`contact_email` absent**; `usi_number` absent when the USI is blank; name taken from `form_data.identity` even after `Student` is changed; no schedule key; `course_id`/`plan_id` come from the enrolment even when `form_data` contains `code`/`plan`; `client_id` absent; `null` values stripped; snapshot of the full encoded `values` string |
| `UsiRuleTest` | Valid USIs; invalid characters (`0`, `1`, `I`, `O`); wrong length; bad check character |
| `DocumentInspectorTest` | Each magic signature; a PDF renamed `.jpg` rejected; `.exe` renamed `.pdf` rejected; zero bytes; over 8 MB; WEBP `RIFF` without `WEBP` rejected |
| `ImageNormaliserTest` | WEBP → valid JPEG (magic bytes `FF D8 FF`); transparent WEBP flattened; over `webp_max_megapixels` rejected without decoding; animated WEBP rejected; PDF, JPEG and PNG returned unchanged. Skipped with a clear message when GD has no WEBP support. |
| `RtoDataClientTest` (Http::fake) | Classification: JSON success; JSON `success:0` → `refused`; HTTP 500 HTML → `ambiguous`; HTTP 200 HTML → `ambiguous`; `ConnectionException` → `ambiguous`; success without `enrolment_id` → `ambiguous`; enrol URL ends in `enrol/`; `values` is a string; credentials never appear in `RtoDataResult::summary` |
| `ConfirmationEmailRendererTest` | Placeholders replaced; values HTML-escaped (`<script>` in a last name); unknown placeholder rejected at save |
| `EnrolmentFlowResolverTest` | Disabled → v1; admin-only with a guest → v1; admin-only with an admin → v2; pair not listed → v1; `*`; missing RTO IDs → v1; **non-empty `rto_schedule_id` → v1 with a warning logged** (`Log::spy`), even when the pair is allow-listed; empty-string schedule → v2 |
| `SubmissionStateMachineTest` | `claim()` returns false when the status changed underneath; backoff times; `needs_review` stores `resume_status`; event rows written |

### 8.2 Feature (routes)

| Test | Covers |
|---|---|
| `ShowEnrolmentFormTest` | Unknown, used or expired token → redirect (unchanged); v1 eligible renders the v1 view; v2 eligible renders the v2 view with the config-driven max size, the name and email as read-only text (no inputs), and a required DOB input |
| `StoreSubmissionTest` | Happy path → 201 with `public_id` and token, row `received`, enrolment marked used, job pushed after commit; **`students.date_of_birth` updated to the submitted DOB**, and `form_data.identity` matches `Student`; every required rule; conditional rules (`disability=Y`, `other_qualifications=Yes`, `individual_needs=Yes`, postal `different`); declarations must be `accepted`; mobile, postcode formats; DOB required, `Y-m-d`, today rejected, `1900-01-01` rejected, no minimum age; **USI blank accepted (`usi_provided=false`)**, valid USI accepted (`true`), malformed USI or bad check character 422; identity fields and `code`/`plan` in the payload are ignored; enrolment with a schedule → 409 |
| **Duplicate submit** | Same `client_submission_id` twice → second is 200 with the same `public_id` and a rotated token, one row, one job; different `client_submission_id` → 409; existing v1 `enrolment_submissions` row → 409; already-completed enrolment → 410; unique index violation path (a row pre-inserted to simulate the race) → 409, not 500 |
| `StoreDocumentTest` | Valid PDF, JPG, PNG; **WEBP stored as `.jpg` with `mime=image/jpeg` and `converted_from=webp`, and the same WEBP replayed → 200**; HEIC → 422 with the HEIC message; magic mismatch 422; zero bytes 422; over 8 MB 422; sha mismatch 422; bad or expired token 403/410; same sha replay 200; replace while `pending`; 409 after `sent`; `failed` or `purged` replacement resets and alerts; stored path contains no original filename |
| `FinaliseSubmissionTest` | Sets `finalised_at` once, dispatches, 204; bad token 403; accepts form-body token (beacon shape) |
| `StoreClientEventTest` | Stores allowed fields; throttle returns 429; rejects unknown events |
| `EnrolmentHealthTest` | `last_sweep_at` 1 min ago → 200 `ok`; 6 min ago → 503 `stale`; null → 503; the body contains only `status` and `sweep_age_seconds`; no session cookie is set |
| `PurgeEnrolmentDocumentsTest` | `failed` document 8 days after `failed_at` → file deleted, `purged`, event row (`retention`/`purged`); 6 days → kept; `pending` document on a submission idle for 8 days → purged; `pending` on an active submission → kept; `sent` documents untouched; `--dry-run` changes nothing; Retry document is hidden once purged |
| `StudentNameLockTest` | Filament edit: name editable with no v2 submission; disabled once one exists for any of the student's enrolments; a forged save with a changed name is refused; email and phone still editable |
| `MissingUsiFilterTest` | The Filament filter lists only `usi_provided=false` rows; the badge shows on those rows only |
| `ScrubV1EnrolmentApiDataTest` | Seeded v1 rows: `request_body`/`public_key` removed from `api_response_payload`; `integration_logs` (`enrolment_api`) keep only the allow-listed keys, `error_message` is cut before the JSON; non-`enrolment_api` logs and non-JSON rows unchanged; running it twice gives the same result |
| `EnrolmentApiServiceNoKeyTest` | v1 `submit()` result (success and skipped paths) contains no `request_body` and no `public_key` anywhere; after a full v1 run, neither `enrolment_submissions.api_*` nor `integration_logs` contains the configured key |
| `CheckoutStoresRtoIdsTest` | `/checkout/load` stores `rto_course_id`; card and PO order creation copy course/plan/schedule to `order_items`; `CreateEnrolmentJob` copies them to `enrolments` |
| `OrdersResendSkipsCompletedTest` | P8: resend email/SMS and Retry Order skip enrolments with `enrolment_completed_at` |

### 8.3 Job tests (Http::fake)

| Test | Scenario | Assertions |
|---|---|---|
| **Enrol timeout** | `enrol/` throws `ConnectionException` | `needs_review`, `review_reason=enrol_ambiguous`, `resume_status=received`, `enrol_calls=1`; one alert mailed; `Http::assertSentCount(1)`; running the job and the sweep again (with time travel past every backoff) sends **no** further requests |
| Enrol HTML 500 | 500 `text/html` | Same as the timeout |
| Enrol success without `enrolment_id` | JSON `success:1` with no ID | `needs_review`, no retry |
| Enrol clean refusal | `success:0` JSON | Back to `received`, `step_attempts=1`, `next_attempt_at` = +1 min; the 2nd refusal → `needs_review` (`enrol_refused`), `Http::assertSentCount(2)`; with `enrol_refusal_retries=0` the 1st refusal goes straight to `needs_review` |
| Enrol success | Proven response shape | One UPDATE stores the IDs and title; status `enrolled`; request body has `values` as a string with the hold address in `email` and `other_email`, **no `contact_email`**, and the confirmed name and DOB |
| Existing learner | Enrol returns a `learner_id` already stored on another submission | Normal `enrolled`; no alert mailed (Q3b) |
| Claim race | Status changed to `enrolling` before the claim | No HTTP call |
| Document wait | Enrolled, not finalised, 10 min elapsed | No `receive` call; at 15 min → sent |
| **Partial document failure** | `id_document` → `"success":1`; `vet_transcript` → 500 | ID doc `sent` and file deleted; VET doc `retry`; re-run after backoff resends **only** the VET doc (assert the request body `name`); after 5 failures the VET doc is `failed` with `failed_at` set, alert sent, submission proceeds to `documents_done` then `updating`; the failed file is kept until the purge |
| Converted WEBP sent | Stored converted document | `/receive` body has `name = id_document.jpg` and `uri` starting `data:image/jpeg;base64,` |
| Missing expected document | `expected_documents` has both, one uploaded, finalised | Alert for the missing type; proceeds |
| Late document | Submission `complete`; new document uploaded | Sweep sends it; status stays `complete` |
| **Update 500** | `update` → 500 HTML | Back to `documents_done` with backoff; retries; the 5th failure → `needs_review` (`resume_status=documents_done`); each body is exactly `type` = `enrolment`, `enrolment_id` (int), `values` = `email=…&other_email=…` (plus the credentials); `values` does not contain `contact_email` |
| Update success | JSON success | `updated`; the `values` string decodes (`parse_str`) to exactly `['email' => real, 'other_email' => real]` |
| No template | No row for the pair | `needs_review` (`no_email_template`, resume `updated`), alert, no mail |
| Confirmation sent | Template with 2 attachments | `Mail::assertSent` to the real email with placeholders filled and 2 attachments; `complete` |
| Stuck | Row `updating` with `claimed_at` -31 min | Sweep → `needs_review` (`stuck`, resume `documents_done`); `enrolling` stuck → resume `received` |
| `failed()` handler | Simulated timeout during `enrolling` | `needs_review`, `enrol_ambiguous` |
| Resume | Admin resumes `documents_done` | Status restored, attempts reset, job dispatched; Resume from `received` with `enrol_calls>0` requires the typed confirmation |
| Link existing | Admin enters IDs | `enrolled` with no HTTP call |
| No secrets or PII persisted | After a full happy run and a failure run | No column in `enrolment_v2_*` or `integration_logs` contains the public key, DOB, USI or last name (raw DB query, excluding the encrypted `form_data`) |
| Uniqueness | Dispatch twice | `Queue::assertPushed(…, 1)` (`ShouldBeUnique`) |

---

## 9. Rollout and operations

### 9.1 Flow selection (`EnrolmentFlowResolver`)

v2 is used for an enrolment only when **all** of these hold:
1. `enrolment.v2.enabled` is true.
2. Either `admin_only` is false, or the current request has a logged-in Filament user.
3. `enrolments.rto_course_id` and `rto_plan_id` are present.
4. The `rto_course_id:rto_plan_id` pair is in `enrolment.v2.pairs`, or `pairs` is `*`.
5. `enrolments.rto_schedule_id` is empty (null or `''`). Every v2 course is an online refresher (Q3), so a schedule means a face-to-face course was allow-listed by mistake. The resolver then returns v1 and logs a warning with the enrolment ID and the three RTO IDs only (no PII), so the course can't be enrolled through v2 without its session.

The decision is made on each `GET /enrol/{token}`. The v2 submit endpoint re-checks it server-side and returns 409 if the enrolment is not v2-eligible, so a student cannot force v2. Links already emailed keep working under whichever flow applies when they are opened.

**Cutover sequence:**
1. Deploy with `enabled=false`.
2. Create email templates.
3. `enabled=true`, `admin_only=true`, one pair. Staff complete real links end to end.
4. `admin_only=false` for that pair.
5. Widen the pairs.
6. `*`.
7. Remove v1 in a later release.

### 9.2 Scheduler and queue worker on cPanel

The host is cPanel shared hosting with no root (Q12), so Supervisor and systemd are not available. The queue worker is **option B: a cron-driven worker started by the scheduler.**

The scheduler currently has nothing to run. Add to `routes/console.php`:

```php
Schedule::command('enrolments:sweep')->everyMinute()->withoutOverlapping(5);

Schedule::command('enrolments:purge-documents')->dailyAt('03:00')->withoutOverlapping(30);

Schedule::command('queue:work database --queue=enrolments,default --stop-when-empty --max-time=55 --timeout=75')
    ->everyMinute()->withoutOverlapping(3)->runInBackground();
```

**Cron** (cPanel → Advanced → Cron Jobs → Add New Cron Job, "Once Per Minute"). Replace `<cpanel-user>` and `<app-path>` with the account name and the directory that contains `artisan`:

```
* * * * * cd /home/<cpanel-user>/<app-path> && /usr/local/bin/ea-php82 artisan schedule:run >> /dev/null 2>&1
```

- The scheduler starts `queue:work` with the same PHP binary that runs `schedule:run` (`ea-php82`), so the worker needs no path of its own.
- The worker's default `--tries` is 1, which applies to jobs without `$tries`. Existing jobs declare their own `$tries`, so their behaviour is unchanged.
- Each worker exits after about 55 seconds plus its current job, and the next minute's worker loads the new code. No `queue:restart` is needed on deploy.
- Latency is up to about 1 minute per step. With the 15-minute document wait this is acceptable.
- A dead worker is caught by the sweep's queue-backlog alert (§4.5). A dead cron is caught by `/health/enrolments` and the external monitor (§3).

**MultiPHP INI Editor** (cPanel → Software → MultiPHP INI Editor → the domain, PHP 8.2):

| Directive | Value | Why |
|---|---|---|
| `upload_max_filesize` | `10M` | One document of up to 8 MB per request |
| `post_max_size` | `12M` | The document plus the multipart overhead |
| `memory_limit` | `256M` | The WEBP → JPEG conversion runs in the upload request; the `webp_max_megapixels` cap keeps GD inside this limit |
| `max_execution_time` | `60` | Upload, conversion and storage |

These are the values for web requests. The cron worker runs the `ea-php82` CLI, which may read a different `php.ini` (§10). The worker needs little memory: an 8 MB file is about 11 MB as base64, inside the CLI's usual 128M default.

### 9.3 Small changes outside v2, listed for review

| Change | Files |
|---|---|
| Store `rto_course_id` on the checkout session | `CheckoutLoadController.php:84-101` |
| Copy RTO IDs to the order item. The duplicated `resolveCourseId` is left as is. | `CheckoutOrderCreator.php:101-108`, `PurchaseOrderCheckoutController.php:195-…` |
| Copy RTO IDs to the enrolment; `env()` → config; expiry → config | `CreateEnrolmentJob.php:178-202, 187, 308` |
| Twilio settings via `config('services.twilio.*')` | `SendEnrolmentSmsJob.php:61, 98-100` |
| `env()` → config | `SubmitEnrolmentToApiJob.php:66` |
| Q9: stop v1 storing `public_key`. Delete the line `'request_body' => $requestBody,` from both return arrays of `submit()`. That key is the only route by which `public_key` reaches `api_response_payload`, and nothing reads it (checked with grep). The request sent to RTO Data is unchanged. | `EnrolmentApiService.php:87, 169` |
| Q2: lock the student name once a v2 submission exists | `Students/Schemas/StudentForm.php:15-18`, `Students/StudentResource.php` |
| P8: skip completed enrolments; fix the notification text | `OrdersTable.php:332, 381-388, 497-502, 519-524` |
| Route `GET /enrol/{token}` to the new controller (the v1 render is identical) | `routes/web.php:90-137` |
| Add `GET /health/enrolments` | `routes/web.php` |
| Sweep, daily purge and cron worker | `routes/console.php` |

Existing v1 rows holding `public_key` (P20) and PII in `integration_logs` (P21) are scrubbed by the one-off migration in §1.4.

### 9.4 Document retention (Q8)

`enrolments:purge-documents` runs daily at 03:00 (§9.2). It uses `enrolment.v2.document_retention_days` (default **7**) and purges the file of every document that matches either rule:

| Rule | Condition |
|---|---|
| Failed | `status = failed` and `failed_at` ≤ now − 7 days |
| Idle | `status` in (`pending`, `retry`), and both the submission's `status_changed_at` and the document's `updated_at` are ≤ now − 7 days |

For each document, `DocumentPurger` does the following:
- deletes the file (a file that is already missing is not an error);
- sets `path = null`, `file_deleted_at = now` and `status = purged`;
- writes one `enrolment_v2_events` row: `step = retention`, `outcome = purged`, `actor = retention`, the `document_id`, and a message `failed for 7 days` or `idle for 7 days`.

`sending` documents are never purged; the stuck rule handles them. `sent` documents have already lost their file. A `purged` document counts as finished for T6a, so it never blocks a submission. Template attachments and v1's `enrolment-documents/` files are not touched.

---

## 10. Decisions, risks and conflicts

### 10.1 Decisions (2026-10-16)

Every open question is answered. Nothing in the design is pending a decision. §10.3 lists conflicts with the codebase and new risks that the decisions create.

| # | Decision | Applied in |
|---|---|---|
| Q1 | While `enrolment.v2.admin_only` is true, v2 is served only to logged-in Filament admins. | §7, §9.1 (unchanged) |
| Q2 | First name, last name and email come from checkout and are locked on the v2 form. There is no `name_mismatch` path. Staff can edit the name in Filament only until a v2 submission exists. DOB is entered on the v2 form, is required, and is written to `students.date_of_birth` on submit. A confirmation step shows the name and DOB before the final submit. | §1.2, §2, §5.1, §5.2, §6, §8 |
| Q3 | No schedule is sent. `rto_schedule_id` is still stored. The resolver refuses v2, and logs a warning, for any enrolment with a schedule. | §1.1, §1.2, §6, §9.1, §8 |
| Q3a | Enrol sends the hold address in `email` and `other_email` only. `contact_email` is never sent. The update body is `type`, `enrolment_id` and `values = email=…&other_email=…`. | §2, §6, §8 |
| Q3b | A reused `learner_id` is normal. No alert. | §6, §8 |
| Q4 | `highest_school_level` (string), `study_reason`, `building_property` and `unit_flat` are confirmed live. | §6 |
| Q5 | Keep `prior_achievement[vic]`. | §6 |
| Q6 | Keep all 10 `disability_type` flags, all `0` when `disability = N`. | §6 (unchanged) |
| Q7 | Templates are keyed on `rto_course_id` + `rto_plan_id`. No default template. Keep the "allow-listed pairs without a template" warning. | §1.2, §2 |
| Q8 | Purge failed documents, and pending documents on idle submissions, after `document_retention_days` (7), daily, with an event row per purge. | §1.2, §2, §7, §9.2, §9.4, §8 |
| Q9 | A one-off migration scrubs `public_key` and enrolment-API PII from existing v1 rows. v1 stops writing `public_key`, via the smallest change to `EnrolmentApiService::submit()`. | §1.4, §9.3, §8 |
| Q10 | USI is optional, validated with `Usi` when present, plus a "Missing USI" filter and badge. | §1.2, §2, §5.2, §6, §8 |
| Q11 | No minimum age. DOB: `required`, `date_format:Y-m-d`, `before:today`, `after:1900-01-01`. | §5.2 |
| Q12 | cPanel shared hosting, worker option B, cron through `/usr/local/bin/ea-php82`, plus the MultiPHP INI values. | §9.2 |
| Q13 | No document is mandatory. Alerts stay limited to declared documents that never arrive. No `required_documents`. | §4.3 (T6a, unchanged behaviour) |

### 10.2 Risks from the brief: status

1. **The name is permanent.** *Resolved by Q2.* The student cannot change the name. They see it in the confirmation step and are told to contact staff before submitting if it is wrong. Staff can correct it until a v2 submission exists. The `name_mismatch` review path is not built. A typo noticed only after submit can't be corrected in RTO Data; this is accepted.
2. **Hold emails and existing learners.** *Resolved by Q3a.* `contact_email` is never sent, so it is never overwritten or erased. If enrol does overwrite an existing learner's `email` and `other_email` with the hold address, they stay invalid until the update succeeds. A failed update ends in `needs_review` with an alert (T11).
3. **Same name and DOB, different person.** *Accepted (Q3b).* No alert.
4. **Retrying a clean refusal.** *Decided.* One retry, then `needs_review`. Configurable through `enrolment.v2.enrol_refusal_retries` (default 1). See T4 and T5.
5. **Receive timeouts can duplicate a document.** *Accepted.* A timed-out `/receive` may be resent.
6. **WEBP not proven with `/receive`.** *Decided.* WEBP is converted to JPEG on the server before storage, so `/receive` only receives PDF, JPEG or PNG (§5.3). HEIC stays rejected with a clear message (§5.1, §5.3). See §10.3 G.
7. **Unproven fields.** *Resolved (Q4).* Confirmed live.
8. **Long forms and CSRF.** *Accepted.* The v2 endpoints are excluded from CSRF (§3).
9. **Update before email delays the confirmation.** Unchanged; intentional.
10. **Templates keyed on course and plan.** *Resolved (Q7).* No default template. The list-page warning and the resolver's log warning remain.
11. **`mobile_phone` `^04\d{8}$`** blocks international and landline-only learners. Unchanged.
12. **Encrypting `form_data`** ties it to `APP_KEY`. Unchanged.
13. **The 8 MB in-memory copy.** Unchanged.
14. **Schedule is not sent.** *Resolved (Q3).* v2 refuses any enrolment with a schedule (§9.1). See §10.3 B and C.
15. **Late uploads after `complete`.** The proposed `info` alert was not in the decisions, so it is **not adopted**. A late document is sent and recorded as an event only.
16. **No automatic purge.** *Resolved (Q8).* 7-day purge (§9.4). See §10.3 H.
17. **Student-entered DOB vs the Student record.** *Resolved (Q2).* Checkout collects no DOB, and the v2 submit writes it to `students.date_of_birth`. See §10.3 A.
18. **A dead cron is undetectable.** *Resolved.* `GET /health/enrolments` returns 503 when `last_sweep_at` is more than 5 minutes old (§3). An external uptime monitor must be pointed at it; that monitor is outside this codebase.

### 10.3 Conflicts with the codebase and new risks from the decisions

None of these is worked around in the design. Each needs either acceptance or a follow-up change.

**A. Checkout overwrites the student's name and wipes the DOB (conflicts with Q2).** Both checkout paths run `Student::updateOrCreate(['email' => …], ['first_name', 'last_name', 'phone', 'date_of_birth' => $studentData['date_of_birth'] ?? null])` (`CheckoutOrderCreator.php:82-90`, `PurchaseOrderCheckoutController.php:164-172`). Checkout collects no DOB, so any later order for the same email:
- sets `students.date_of_birth` back to `null`, erasing the DOB written by the v2 submit;
- overwrites `first_name` and `last_name`, bypassing the Filament lock, because the lock lives in the Filament form and resource, not the model.

What reaches RTO Data is not affected, because the mapper and the update step read the `form_data.identity` snapshot taken at submit (§6), not the live `Student`. The local Student record, however, can drift from what was enrolled. **Recommended follow-up (it changes checkout, so it was not added here):** never write `date_of_birth` from checkout when it is null, and don't overwrite the name of a student who has a v2 submission.

**B. Falling back to v1 does not enrol the session either.** v1 does not send a schedule (`EnrolmentApiService` has no schedule field). The Q3 guard keeps face-to-face courses off v2. But a face-to-face enrolment that falls back to v1 is still enrolled into the plan without its session, exactly as today. The guard prevents a v2 regression; it does not fix v1.

**C. Online links that carry a schedule would silently use v1.** `CheckoutLoadController` stores whatever `schedule` query parameter arrives (`CheckoutLoadController.php:23, 33, 87`). If any online refresher's checkout link includes `schedule=…`, every one of its enrolments goes to v1, with only a log warning. Check the website's online course links before enabling v2. The resolver's warning line is how you would notice.

**D. DirectAdmin vs cPanel.** The repository targets DirectAdmin: commit `7bf2ebf` "Prepare application for DirectAdmin dev deployment", and audit §4. §9.2 now documents cPanel only, per Q12. If the dev server stays on DirectAdmin, its cron line and PHP settings will differ from §9.2.

**E. cPanel CLI limits.**
- The worker's `--timeout`, and the job's `$timeout` and `$failOnTimeout`, only work when the `pcntl` extension is loaded in the `ea-php82` CLI. Shared cPanel often leaves it out. Without it, a hung job is not killed and `failed()` does not run. The HTTP timeouts (30 s + 10 s) still bound every RTO Data call, and T17 still parks a row stuck for 30 minutes. Check with `/usr/local/bin/ea-php82 -m | grep pcntl`.
- The scheduler starts commands through `proc_open`, which must not be in the CLI's `disable_functions`.
- The MultiPHP INI Editor sets web PHP. The CLI may read a different `php.ini`. §9.2 explains why the worker doesn't need the web values.

**F. The health endpoint is public.** It exposes only `ok`/`stale` and the sweep age, and it is throttled. That is acceptable for an uptime monitor, but it does reveal whether the scheduler is running.

**G. WEBP conversion needs GD with WebP support** in the web PHP (`gd_info()['WebP Support']`). Without it, every WEBP upload fails with the "upload it as a JPG, PNG or PDF" message. Check before enabling. Decoding costs about 4–5 bytes per pixel, so the 25 MP cap (`webp_max_megapixels`) keeps a single conversion around 125 MB, inside `memory_limit = 256M`. Re-encoding at quality 85 loses a little detail, which is acceptable for ID scans.

**H. The purge can remove documents before an admin acts.** A submission parked in `needs_review` (for example `enrol_ambiguous`) for more than 7 days has its pending documents purged, with an event row but no alert (Q8 specifies event rows only). When an admin later resumes or links it, those documents are gone. The student's 24-hour upload window has also closed, so staff must collect the documents by hand. If that is not acceptable, the options are an alert per purge of a never-sent document, or excluding `needs_review` submissions from the idle rule.

**I. v1 keeps writing PII after the scrub.** The §1.4 migration cleans existing rows once. Q9 only stops `public_key`, so every new v1 submission still writes `buildPayload()` (form data including DOB, USI and address) to `enrolment_submissions.api_request_payload` and `integration_logs.request_payload` (`SubmitEnrolmentToApiJob.php:55, 85, 113, 132`). This continues until v1 is retired. Note also that `api_request_payload` never held `public_key`; the key was only in `api_response_payload.request_body`. The migration still checks both columns, as asked.

**J. Optional USI and certificates.** An RTO generally cannot issue a certificate without a verified USI unless an exemption applies. Enrolments without a USI therefore need staff follow-up. The "Missing USI" filter is the tool for that; there is no automatic reminder to the student.

**K. Scope of the name lock.** The lock is per student: once any v2 submission exists for any of the student's enrolments, staff cannot change the name in Filament, including for later enrolments. A legitimate change, such as a legal name change, then needs a database edit. DOB is not locked in Filament (Q2 locks only the name), and editing it there does not change RTO Data.

### 10.4 Additions not in the brief

- The **Link existing RTO Data enrolment** admin action (the safe resolution for `enrol_ambiguous`).
- The `client_submission_id` idempotency key.
- The per-submission upload token.
- The `enrolment_v2_events` timeline.
- The queue-backlog alert.
- The `form_data.identity` snapshot, so RTO Data receives exactly the name and email the student confirmed (§10.3 A).
- The `usi_provided` column, needed because `form_data` is encrypted and can't be filtered (Q10).
- The `webp_max_megapixels` cap and the `purged` document status.
