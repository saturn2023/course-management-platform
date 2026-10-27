# Enrolment process audit (current state)

Investigation only. Nothing in the app was changed. Line numbers refer to the working tree on branch `dev-laravel-deployment` at commit `7bf2ebf`. Secret values are not reproduced, only where they live.

---

## 0. End-to-end flow in one paragraph

A purchaser checks out (card or purchase order). `ProcessOrderJob` dispatches `CreateXeroInvoiceJob` and `CreateEnrolmentJob`. `CreateEnrolmentJob` creates one `enrolments` row per student with a random 64-char token and queues `SendEnrolmentEmailJob`, which emails `https://<app>/enrol/{token}`. The student opens the link (`GET /enrol/{token}`), fills in a Blade form, and posts it (`POST /enrol/{token}`). The closure stores uploads on the `local` disk, creates an `enrolment_submissions` row, dispatches `SubmitEnrolmentToApiJob`, and marks the enrolment `completed`. The job calls RTO Data `POST /enrol` and then `POST /receive` once per document. SMS is only sent when an admin clicks a resend or retry action.

---

## 1. Entry points

### 1.1 Routes

All enrolment routes are closures in `routes/web.php`. There are no enrolment controllers, form requests, Livewire components or Inertia pages.

| Method / URI | Location | Purpose |
|---|---|---|
| `GET /enrol/{token}` (`enrol.show`) | `routes/web.php:90-137` | Look up the enrolment by token and render `enrolments.registration-form` |
| `POST /enrol/{token}` (`enrol.submit`) | `routes/web.php:139-338` | Validate, store uploads, create submission, queue API job, mark link used |
| `GET /enrolment-completed` | `routes/web.php:340-342` | Static success page `enrolments.completed` |
| `GET /enrolment-not-successful` | `routes/web.php:344-346` | Static failure page `enrolments.failed`, returned with HTTP **404** |

No middleware is applied to these routes beyond the default `web` group (no `throttle`, no `auth`). The global middleware is in `bootstrap/app.php:14-40`.

Upstream entry points that lead to enrolment creation:

| Entry | Location |
|---|---|
| `GET /checkout/load?code=&plan=&schedule=&quantity=` (throttle 30/min) | `routes/web.php:58-60` → `app/Http/Controllers/CheckoutLoadController.php:19-104`, request `app/Http/Requests/CheckoutLoadRequest.php:14-22` |
| PO checkout → `ProcessOrderJob::dispatch` | `app/Http/Controllers/PurchaseOrderCheckoutController.php:237` |
| Card payment → `ProcessOrderJob::dispatch` | `app/Http/Controllers/CardPaymentController.php:190` |
| Admin "Process Order" | `app/Filament/Resources/Orders/Tables/OrdersTable.php:302-335` |
| Admin "Retry Order" | `app/Filament/Resources/Orders/Tables/OrdersTable.php:337-410` |
| "ENROL NOW" link builder | `app/Models/Course.php:206-220` (`/checkout/load?code={ams_enrolment_code}&plan={ams_plan_id}`, or `enrolment_url` override) |

### 1.2 Link validation (GET and POST share identical checks)

| Check | GET | POST |
|---|---|---|
| Token lookup: `Enrolment::where('enrolment_token', $token)->first()` (plain-text equality) | `web.php:91-93` | `web.php:140-142` |
| Unknown token → redirect `/enrolment-not-successful` | `web.php:95-97` | `web.php:144-146` |
| Already used (`enrolment_completed_at` set) → redirect | `web.php:99-101` | `web.php:148-150` |
| Expired (`enrolment_token_expires_at` in the past) → redirect | `web.php:103-108` | `web.php:152-157` |

- **Token generation:** `Str::random(64)` in a uniqueness loop (`app/Jobs/CreateEnrolmentJob.php:288-295`). It is stored in plain text in `enrolments.enrolment_token`, which is `string(80)`, unique and nullable (`database/migrations/2026_05_22_062458_add_secure_token_fields_to_enrolments_table.php:12`).
- **Expiry:** hardcoded at 30 days from creation (`CreateEnrolmentJob.php:187`). Resending an email does not extend it.
- **Single use:** enforced only by `enrolment_completed_at`, which is set at `web.php:325-335` after the submission row is created and the job is dispatched.
- **`secret_key`:** generated as a sha256 of random bytes plus `microtime` (`CreateEnrolmentJob.php:297-304`) and stored (`:190`). Nothing reads it.
- **`secret_base_url`:** built as `env('AMS_ENROLMENT_FORM_URL', url('/registration-form')) . '/?code=…&plan=…'` (`CreateEnrolmentJob.php:306-319`). The GET handler only parses its query string to get `code` and `plan` (`web.php:110-118`, `:130-131`), which are rendered as hidden inputs (`registration-form.blade.php:373-374`).

### 1.3 Views

| View | Notes |
|---|---|
| `resources/views/enrolments/registration-form.blade.php` (1067 lines) | Standalone page with inline CSS and JS. The form posts to `route('enrol.submit', token)` with `multipart/form-data` and `@csrf` (`:367-370`). JS toggles conditional sections and blocks files over 8 MB on the client (`:1006-1065`). |
| `resources/views/enrolments/completed.blade.php` | Static "Registration Completed" page |
| `resources/views/enrolments/failed.blade.php` | Static "Registration Link Unavailable" page |

The GET route passes `config('enrolment_languages')` (`config/enrolment_languages.php`) and `config('enrolment_countries')` (`config/enrolment_countries.php`) for the language and country selects (`web.php:120-121`).

### 1.4 POST validation (`web.php:159-244`)

Every rule is `nullable`. Nothing is server-side required. The `required` attributes exist only in the HTML. Summary:

- **Strings with max lengths:** all personal, address, postal, contact, education, demography and needs fields.
- **`email`, `email_address`:** `email` rule.
- **`qualification_level`, `disability_type`:** `array` of strings.
- **Declarations (`agree_*`, `student_declaration`, `privacy_declaration`):** `['nullable']` with no `accepted` rule.
- **Uploads:** `id_document`, `vet_transcript` and `documents.*` are `['nullable','file','max:8192']`, with no `mimes` or `mimetypes` rule.

Identity override (`web.php:257-259`): `first_name`, `last_name` and `email` in `$validated` are overwritten from the linked `Student` row.

---

## 2. Data

### 2.1 Tables and columns

**`enrolments`.** Model `app/Models/Enrolment.php` (fillable `:10-29`, casts `:31-37`). Relations: `order`, `student`, `course`, `submission` (hasOne).

| Column | Defined in |
|---|---|
| `id`, `order_id` (FK cascadeOnDelete), `student_id` (FK nullOnDelete), `course_id` (FK nullOnDelete), `external_enrolment_id` string null, `enrolment_link` string null, `status` string default `pending`, `error_message` text, `request_payload` longText, `response_payload` longText, timestamps | `database/migrations/2026_05_19_064908_create_enrolments_table.php:15-32` |
| `email_sent_at`, `link_sent_at` timestamps | `2026_05_21_031007_add_email_sent_fields_to_enrolments_table.php:12-13` |
| `sms_sent_at` timestamp, `sms_error_message` text | `2026_05_22_051906_add_sms_sent_fields_to_enrolments_table.php:12-13` |
| `enrolment_token` string(80) unique, `enrolment_token_expires_at`, `enrolment_completed_at` | `2026_05_22_062458_add_secure_token_fields_to_enrolments_table.php:12-14` |
| `secret_key` string(120), `secret_base_url` text | `2026_05_22_063537_add_secret_link_fields_to_enrolments_table.php:12-13` |

There is no unique constraint on (`order_id`, `student_id`, `course_id`).

`enrolments.status` values:
- Migration comment: `pending, processing, success, failed` (`create_enrolments_table.php:25`).
- Values actually written: `link_created` (`CreateEnrolmentJob.php:193`), `link_sent` (`SendEnrolmentEmailJob.php:99`), `completed` (`web.php:326`).
- `failed` is only referenced in the dedupe query (`CreateEnrolmentJob.php:121`) and is never written.
- `external_enrolment_id` is a local placeholder, `'ENR-' . Str::random(10)` (`CreateEnrolmentJob.php:168`). It is not the RTO Data ID.

**`enrolment_submissions`.** Model `app/Models/EnrolmentSubmission.php` (fillable `:10-29`, casts `:31-38`: `form_data`, `api_request_payload` and `api_response_payload` as arrays).

| Column | Defined in |
|---|---|
| `enrolment_id` FK cascadeOnDelete, **unique**; `order_id`, `student_id`, `course_id` FKs nullOnDelete; `code`, `plan` strings; `form_data` json; `id_document_path`, `vet_transcript_path` strings; `submitted_at` | `2026_05_25_025901_create_enrolment_submissions_table.php:12-48` |
| `api_status` default `pending`, `api_attempts` uint default 0, `api_last_attempted_at`, `api_submitted_at`, `api_error_message` text, `api_request_payload` json, `api_response_payload` json, `external_reference` string | `2026_05_26_020618_add_api_tracking_fields_to_enrolment_submissions_table.php:12-19` |

`api_status` values written: `pending` (`web.php:320`), `processing` (`SubmitEnrolmentToApiJob.php:51`), `skipped` (`:75`), `success` (`:101`), `failed` (`:123`). `external_reference` is meant to hold the RTO Data enrolment ID (`EnrolmentApiService.php:132-134`). See problem P3 for how it can be overwritten.

**`students`.** Model `app/Models/Student.php:10-16`. Columns `first_name`, `last_name`, `email`, `phone` null, `date_of_birth` date null (`2026_05_19_034906_create_students_table.php:15-21`). `email` is made unique after a dedupe merge (`2026_06_11_000003_dedupe_students_and_add_unique_email_index.php:17-51`). Students are upserted by lower-cased email in `app/Services/CheckoutOrderCreator.php:77-94` and `PurchaseOrderCheckoutController.php:158-176`. The table has no AVETMISS columns: `title`, `gender`, `usi`, address and so on do not exist.

**`orders`** (enrolment-relevant parts). Model `app/Models/Order.php` (relations `student` `:66`, `items` `:71`, `enrolments` `:81`, `students` `:91`).

| Column | Defined in |
|---|---|
| `status` default `pending`, `xero_status`, `enrolment_status` default `pending` | `2026_05_19_034916_create_orders_table.php:19-26` |
| `billing_*` fields | `2026_05_21_052250_add_billing_fields_to_orders_table.php:12-21` |
| `purchaser_confirmation_sent_at` | `2026_05_21_063501_…:12` |
| `purchase_order_number`, `purchase_order_document_path`, `payment_method`, `payment_status` | `2026_06_02_033354_…:12-15`, `2026_06_04_005545_…:12-26` |
| `pin_charge_token` unique, `paid_at`, etc. | `2026_06_11_000002_…:20-24` |
| `origin` | `2026_09_01_000001_…:27` |

`orders.enrolment_status` values written: `pending`, `processing`, `link_created`, `link_sent`, `failed` (`CreateEnrolmentJob.php:47,73,80,241,273`; `SendEnrolmentEmailJob.php:173`; `OrdersTable.php:373`; `CheckoutOrderCreator.php:66`; `PurchaseOrderCheckoutController.php:147`). `OrderCompletion::attemptMarkProcessed` moves `orders.status` from `processing` to `processed` when `xero_status = success` and `enrolment_status = link_sent` (`app/Support/OrderCompletion.php:16-53`). It does not wait for the RTO Data submission.

**`order_items`.** `course_id` FK nullOnDelete, `name`, `quantity`, `unit_price`, `total` (`2026_05_19_034927_create_order_items_table.php:15-22`). Plan ID and schedule ID are **not** stored on the order item.

**`order_students`.** Pivot with unique (`order_id`, `student_id`) (`2026_05_21_052428_create_order_students_table.php:13-19`).

**`checkout_sessions`.** Holds `course_code`, `plan_id`, `schedule_id`, `quantity`, the RTO Data `/detail` snapshot (`rto_payload`), prices, `student_details` json, `billing_details` json, `expires_at` (60 min), `order_id` (`2026_05_27_014900_…:10-47`, `2026_05_27_052437_…:11-13`).

**`courses`.** Enrolment mapping: `ams_enrolment_code`, `ams_plan_id` (`2026_05_25_035217_add_ams_mapping_fields_to_courses_table.php:12-13`), plus `enrolment_url` (`Course.php:35`). They are edited in Filament at `app/Filament/Resources/Courses/Schemas/CourseForm.php:106-127`.

**`integration_logs`.** `order_id`, `service`, `action`, `status`, `request_payload` longText, `response_payload` longText, `error_message` (`2026_05_19_034936_create_integration_logs_table.php:15-30`). The enrolment flow writes rows with these values:
- `service`: `enrolment_api`, `email`, `sms`, `order_processor`.
- `status`: `processing`, `success`, `failed`, `skipped`, `queued`.

### 2.2 How a course is chosen for the enrolment

`CheckoutOrderCreator::resolveCourseId` (`app/Services/CheckoutOrderCreator.php:142-184`) tries three lookups in order. `PurchaseOrderCheckoutController::resolveCourseId` (`:312-372`) is a duplicate.

1. `ams_plan_id = plan` and `code IN (course_code, course_code without -N suffix)`.
2. The same `code` with **any plan**.
3. A plan-only match, used only if exactly one course has that plan.

If nothing matches, `order_items.course_id` is `null`. `CreateEnrolmentJob` uses only `$order->items->first()` (`CreateEnrolmentJob.php:31`) for every student.

### 2.3 Admin (Filament)

- `EnrolmentSubmissionResource` is view-only: `canCreate`, `canEdit` and `canDelete` all return false (`app/Filament/Resources/EnrolmentSubmissions/EnrolmentSubmissionResource.php:26-40`). The infolist shows document paths as copyable text with no download link (`…/Schemas/EnrolmentSubmissionInfolist.php:46-57`). There is no "resubmit to RTO Data" action.
- `EnrolmentResource` has list, create and edit pages and a bulk delete (`app/Filament/Resources/Enrolments/EnrolmentResource.php:42-47`, `…/Tables/EnrolmentsTable.php:44-49`). The form edits raw fields, including `status` and the payload text (`…/Schemas/EnrolmentForm.php:15-37`). Deleting an enrolment cascades to its submission.

---

## 3. RTO Data integration

All server-side calls go to `config('services.enrolment_api.base_url')` plus a path. They use the HTTP client from `EnrolmentApiService::client()` (`app/Services/EnrolmentApiService.php:342-366`) or an equivalent inline client in `RtoDataCourseService` (`app/Services/RtoDataCourseService.php:24-38`). Both clients send:
- Headers `Accept: application/json` and `Content-Type: application/json`, plus `Origin` when configured.
- `timeout` (default 30 s) and `connectTimeout` (default 10 s).
- Redirects followed, with `CURLOPT_POSTREDIR => 3` so redirected requests stay POST.

Neither client retries at the HTTP level.

### 3.1 `POST /enrol`: create enrolment

- **Caller:** `EnrolmentApiService::callEnrol` (`:250-287`), called from `submit()` (`:113`). `submit()` is called only by `SubmitEnrolmentToApiJob` (`app/Jobs/SubmitEnrolmentToApiJob.php:71`).
- **Body (JSON):**
  ```json
  { "public_key": "<config>", "subdomain": "<config>", "values": "<http_build_query string>" }
  ```
  `values` is a **URL-encoded query string**, not an array (`:252-256`). Nested arrays are encoded in bracket form, for example `disability_type%5B11%5D=0&…&prior_achievement%5Ball%5D%5B008%5D=1&prior_achievement%5Bvic%5D%5B008%5D=A`. `http_build_query` silently drops `null` values, so an unmapped `state` or `postal_state` is omitted entirely.
- **Pre-flight:**
  - If `services.enrolment_api.enabled` is false, `submit()` returns `skipped => true` without any HTTP call (`:80-89`).
  - `validateConfig` throws if `base_url`, `public_key` or `subdomain` is empty (`:386-399`).
  - `buildEnrolArgs` throws if `first_name`, `last_name`, `date_of_birth`, `course_id` or `plan_id` is empty (`:241-245`).
- **Response handling:**
  - A non-2xx status throws a `RuntimeException` that includes the full body and response headers (`:262-271`).
  - A non-JSON body throws (`:275-284`).
  - A falsy `success` key throws (`:115-119`).
  - The ID is read from `enrolment_id` or `data.enrolment_id`. If it is missing, the call throws (`:121-129`), even though the remote enrolment may already exist.
  - On success the ID is written immediately to `enrolment_submissions.external_reference` (`:132-134`).
- **Idempotency guard:** if `external_reference` is already set, `/enrol` is skipped and the existing ID is reused (`:105-111`).
- **Timeout:** the Laravel HTTP client throws `ConnectionException`. Nothing catches it in the service, so it propagates to the job, which marks the submission `failed` and rethrows for a queue retry (see §4).

### 3.2 `POST /receive`: upload a document

- **Caller:** `EnrolmentApiService::callReceive` (`:289-340`), looped over `collectDocumentPaths()` (`:401-407`), which returns `id_document_path` and `vet_transcript_path`.
- **Body (JSON):**
  ```json
  { "type": "receive", "enrolment_id": "<string>", "files": [ { "name": "<basename>", "uri": "data:<mime>;base64,<...>" } ],
    "public_key": "<config>", "subdomain": "<config>" }
  ```
  One request is sent per file. The whole file is base64-encoded into memory (`:312-322`).
- **Local pre-checks** return `success => false` without throwing:
  - The file is missing from the `local` disk (`:293-299`).
  - `mime_content_type()` is not `application/pdf`, `image/jpeg`, `image/png` or `image/jpg` (`:301-310`).
- **Response handling:** success means a 2xx status **and** the raw body string containing `"success":1` (`:332`). The code comment says this copies "Old WordPress logic". The per-file result is returned and nothing is thrown per file. After the loop, if any file failed, the service throws one `RuntimeException` that lists all failures (`:149-158`).
- **Timeout:** a `ConnectionException` in `callReceive` is not caught, so it aborts the loop and propagates to the job.

### 3.3 `POST /detail`: course and plan lookup during checkout

- **Caller:** `RtoDataCourseService::getCourseDetail` (`app/Services/RtoDataCourseService.php:11-135`), used by `CheckoutLoadController.php:27`.
- **Body (JSON):** `{ public_key, subdomain, course_id: <code>, plan_id: <plan>, type: "course" }` (`:39-45`).
- **Pre-flight:** throws if `base_url`, `public_key`, `subdomain` or **`origin`** is empty (`:20-22`).
- **Response handling:**
  - Non-2xx → `Log::warning` and "Could not load course details…" (`:47-57`).
  - Non-JSON → warning and an exception (`:61-69`).
  - The course is read from `data`, `detail` or the root (`:71`).
  - Plan lookup (`:137-160`), schedule lookup (`:162-194`) and price, the first `payment_due` (`:196-207`).
- **Failure or timeout:** the controller catches any `Throwable`, logs it, and renders `checkout.load-error` with HTTP 422 and `$e->getMessage()` as the message (`CheckoutLoadController.php:26-37`). A timeout therefore shows the raw cURL exception text to the visitor.

### 3.4 Client-side widget (not server-side, listed for completeness)

`resources/views/components/layout/header.blade.php:397-400` renders a `div.rtodata-certificate-check` with **hardcoded** `data-api-user`, `data-api-key` and `data-subdomain` attributes. `:412` loads `https://amstraining.rtodata.com.au/api/widget/widget.js`. These are public certificate-check widget credentials that appear in page source on every page with the header.

### 3.5 Not present

No code calls an RTO Data `update`, `check` or any other endpoint. Only `/enrol`, `/receive` and `/detail` are used.

---

## 4. Background work

### 4.1 Jobs in the enrolment chain

| Job | File | `$tries` | `$backoff` | `$timeout` | Uniqueness/overlap guard | Dispatched from |
|---|---|---|---|---|---|---|
| `ProcessOrderJob` | `app/Jobs/ProcessOrderJob.php` | 3 (`:15`) | default (0) | none | none | `PurchaseOrderCheckoutController.php:237`, `CardPaymentController.php:190`, `OrdersTable.php:328,397` |
| `CreateEnrolmentJob` | `app/Jobs/CreateEnrolmentJob.php` | 3 (`:17`) | default | none | DB-check dedupe (`:118-122`) | `ProcessOrderJob.php:56` |
| `SendEnrolmentEmailJob` | `app/Jobs/SendEnrolmentEmailJob.php` | 3 (`:19`) | default | none | `email_sent_at` check unless `forceResend` (`:41-64`) | `CreateEnrolmentJob.php:148,223`, `OrdersTable.php:383,501` |
| `SendEnrolmentSmsJob` | `app/Jobs/SendEnrolmentSmsJob.php` | 3 (`:16`) | default | none | `sms_sent_at` check unless `forceResend` (`:27-44`) | `OrdersTable.php:384,523` only |
| `SubmitEnrolmentToApiJob` | `app/Jobs/SubmitEnrolmentToApiJob.php` | 5 (`:16`) | 300 s (`:18`) | none | `api_status === 'success'` early return (`:29-46`) | `routes/web.php:323` only |
| `ProcessTestOrderJob` | `app/Jobs/ProcessTestOrderJob.php` | default | — | — | — | Not dispatched anywhere |

- None of these jobs implement `failed()`, `ShouldBeUnique`, `WithoutOverlapping` or `maxExceptions`. A search of `app/Jobs` for these found nothing.
- `ProcessOrderJob` dispatches the two child jobs independently, not as a chain (`ProcessOrderJob.php:53-56`).
- **Queue config:** the default connection is `env('QUEUE_CONNECTION','database')` (`config/queue.php:16`). The database driver has `retry_after = 90` s (`config/queue.php:42`) and `after_commit = false` (`:43`). `.env:40` sets `QUEUE_CONNECTION`; its value is not reproduced here.
- **Scheduler and commands:** `routes/console.php` contains only the stock `inspire` command. There are no scheduled tasks, Artisan commands, events or listeners for enrolment: `app/Console`, `app/Listeners` and `app/Events` do not exist. The repository does not document how the queue worker runs on the DirectAdmin server.

### 4.2 `SubmitEnrolmentToApiJob` lifecycle (`app/Jobs/SubmitEnrolmentToApiJob.php`)

1. Load the submission, `findOrFail` (`:26-27`).
2. If `api_status = success`, log `skipped` and return (`:29-46`).
3. Build the payload. `buildEnrolArgs` can throw here, **outside** the try/catch, so the submission is not marked `failed` (`:48`).
4. Set `api_status = processing`, `api_attempts + 1`, and store `api_request_payload` (`:50-56`). Log with `api_enabled` read from `env('ENROLMENT_API_ENABLED')` directly (`:66`).
5. Call `$service->submit()` (`:71`).
6. If the result is skipped (API disabled), set `api_status = skipped` and return (`:73-93`). Nothing ever requeues skipped submissions.
7. On success, read `$externalReference` from `$result['response']['id' | 'reference' | 'external_reference']` (`:95-98`) and write `api_status = success` plus `external_reference = $externalReference` (`:100-106`).
8. On any `Throwable`, set `api_status = failed` and `api_error_message`, log, and rethrow (`:121-137`). The queue retries after 300 s, up to 5 attempts. Between attempts the row reads `failed` even though more attempts are pending.

### 4.3 Can a timed-out enrol be resent and create a duplicate?

**Yes.** These paths can call `/enrol` more than once for the same submission:

1. **Lost response or timeout after RTO Data created the record.** `callEnrol` throws before `external_reference` is saved (`EnrolmentApiService.php:113`, then `:132-134` never runs). The job rethrows, and the next attempt 300 s later sees no `external_reference` and calls `/enrol` again. This can happen on up to 5 attempts.
2. **2xx with `success` truthy but no `enrolment_id` in the expected keys.** This throws at `:125-129` and the retry calls `/enrol` again.
3. **Overlapping execution of the same job.** The worst-case runtime is one `/enrol` plus two `/receive` calls at up to 10 s connect plus 30 s each, about 120 s. The database queue's `retry_after` is 90 s, and the job has no `$timeout` and no overlap lock. A second worker can therefore pick the same job up while the first is still waiting for `/enrol`, and both call it.
4. **Partial document failure.** When `external_reference` exists, `/enrol` is skipped. However, *all* documents are re-sent on every retry, including ones that already succeeded (`:145-147`). This can duplicate documents in RTO Data.
5. **`external_reference` overwrite.** See P3. After a successful run, `external_reference` is likely overwritten with `null`, which removes the guard for any future re-dispatch.

---

## 5. Documents

| Aspect | Current behaviour | Location |
|---|---|---|
| Form inputs | `id_document` (`accept=".pdf,.jpg,.jpeg,.png"`), `vet_transcript` (`accept=".pdf"`). Both are optional in HTML. | `registration-form.blade.php:922,929` |
| Client size check | JS blocks files over 8 MB (`8*1024*1024`) and shows a modal | `registration-form.blade.php:1045-1056`, modal `:997-1003` |
| Server validation | `nullable|file|max:8192` (KB) for `id_document`, `vet_transcript` and `documents.*`. **No MIME/extension rule.** | `web.php:240-243` |
| `documents[]` | Accepted and validated, but the form has no such input. When posted, files are stored and the first two backfill a missing ID or transcript. Paths are saved in `form_data.uploaded_documents`. | `web.php:279-297` |
| Storage | `->store('enrolment-documents', 'local')`, a random hashed filename under `storage/app/private/enrolment-documents` | `web.php:272,276,285`; disk root `config/filesystems.php:33-36` (`serve => true`) |
| DB reference | `enrolment_submissions.id_document_path` and `vet_transcript_path` | `web.php:317-318` |
| MIME check | Only at send time: `mime_content_type()` must be pdf, jpeg or png, otherwise that document "fails" permanently and the job throws on every attempt | `EnrolmentApiService.php:301-310` |
| Sent to RTO Data | `/receive`, one base64 data-URI per file, `name` = stored random basename | `EnrolmentApiService.php:312-328` |
| Cleanup | **None.** No code deletes files under `enrolment-documents`, before or after a successful upload. The only `Storage::delete` in the app is for PO documents (`PurchaseOrderCheckoutController.php:222`). | — |
| PHP limits | There is no `.user.ini` or `php.ini` in the repo, and `public/.htaccess` sets no upload limits. Whether the server's `upload_max_filesize`/`post_max_size` allow two 8 MB files has not been verified. | — |

---

## 6. Email and SMS

| Message | Class / job | Template | Trigger | Course/plan specific? |
|---|---|---|---|---|
| Enrolment link email | `app/Mail/EnrolmentLinkMail.php` (subject hardcoded `:28`), sent synchronously inside `SendEnrolmentEmailJob.php:95-96` | `resources/views/emails/enrolment-link.blade.php` (markdown; signature "Ian Cole, AMS Training" hardcoded `:33-35`) | `CreateEnrolmentJob.php:148,223`; admin resend/retry `OrdersTable.php:383,501` | No. It only interpolates `course->title`. No branching on `course_id` or `plan_id`. |
| Purchaser confirmation | `SendPurchaserConfirmationEmailJob` → `PurchaserConfirmationMail` | `resources/views/emails/purchaser-confirmation.blade.php` | `CreateEnrolmentJob.php:258`; `OrdersTable.php:391,542` | No. It uses the first item name. |
| Enrolment SMS | `SendEnrolmentSmsJob` (Twilio REST via `Http::withBasicAuth`, `:106-112`) | Inline string `SendEnrolmentSmsJob.php:160-167` | **Admin only:** `OrdersTable.php:384,523`. Not sent automatically on order processing. | No |
| Registration-completed confirmation to student | — | — | **Does not exist** | — |
| Admin alert on any failure (link, email, SMS, RTO Data) | — | — | **Does not exist.** Failures go only to `integration_logs` and the submission or order columns. | — |

Notes:
- The SMS `To` value is `students.phone` as entered. The form pattern is `^04\d{8}$` (`registration-form.blade.php:664`), and the job does no E.164 normalisation. If Twilio is disabled, the job logs `skipped` and returns (`:65-83`). A missing phone throws and burns all 3 tries (`:46-59`).
- The admin "Process Order" success notification says "Xero, enrolment, email, and SMS jobs have been queued" (`OrdersTable.php:332`), but nothing in that chain dispatches SMS.

---

## 7. Configuration and secrets

### 7.1 Where values come from

| Setting | Source | Read at |
|---|---|---|
| RTO Data enabled / base URL / public key / subdomain / origin / timeouts | `.env` `ENROLMENT_API_*` (lines 79-85, plus `ENROLMENT_API_ORIGIN` again at line 8) → `config/services.php:61-69` | `EnrolmentApiService.php:373-384`, `RtoDataCourseService.php:13-18` |
| Subdomain default | Hardcoded fallback `'amstraining'` | `config/services.php:65`, `EnrolmentApiService.php:379`, `RtoDataCourseService.php:15` |
| Origin default | `config('app.url')` called inside a config file | `config/services.php:66` |
| Enrolment API enabled flag (logging only) | **`env()` directly in a job** | `SubmitEnrolmentToApiJob.php:66` |
| Twilio enabled / SID / auth token / from number | **`env()` directly in a job.** No `config/` entry exists. `.env` lines 74-77. | `SendEnrolmentSmsJob.php:61,98-100` |
| AMS enrolment form base URL | **`env('AMS_ENROLMENT_FORM_URL')` directly in a job.** Not present in `.env`. | `CreateEnrolmentJob.php:308` |
| Mail transport | `.env` `MAIL_*` (lines 53-61) → `config/mail.php` | Laravel mailer |
| Per-course mapping | DB `courses.ams_enrolment_code`, `courses.ams_plan_id`, `courses.enrolment_url` | `CourseForm.php:106-127`, `Course.php:206-220`, `EnrolmentApiService.php:181-182` |
| Language / country code lists | `config/enrolment_languages.php`, `config/enrolment_countries.php` | `web.php:120-121` |

### 7.2 Hardcoded in code rather than config

- RTO Data widget API user, API key and subdomain: `resources/views/components/layout/header.blade.php:397-400`. The widget script URL with the subdomain is at `:412`.
- `country` always `'1101'` (Australia), regardless of the `address_country` input: `EnrolmentApiService.php:195`.
- Defaults `main_language` `'1201'` (`:197`) and `gender` `'M'` (`:185`).
- State code map: `EnrolmentApiService.php:13-22`. ATSI map: `:24-33`. Disability codes: `:35`.
- AVETMISS option lists (school level, study reason, labour force, disability, prior achievement, postal states, titles, ATSI): inline `@php` arrays in `registration-form.blade.php` (`:385, 525, 611-615, 681-689, 731-740, 760-772, 793-802, 819, 865-876`).
- Token expiry 30 days: `CreateEnrolmentJob.php:187`.
- Upload limit 8 MB: `web.php:240-243` and `registration-form.blade.php:1047`.
- Twilio API URL: `SendEnrolmentSmsJob.php:108`.
- Email subject and signature: `EnrolmentLinkMail.php:28`, `enrolment-link.blade.php:33-35`.
- `/receive` success detection string `"success":1`: `EnrolmentApiService.php:332`.

### 7.3 `.env` observations (names only)

- `ENROLMENT_API_ORIGIN` is defined **twice** (`.env:8` and `.env:83`). Which one wins depends on the dotenv loader.
- `AMS_ENROLMENT_FORM_URL` is not defined.
- Because of the direct `env()` calls listed in 7.1, running `php artisan config:cache` will make those reads return `null`. Twilio would then be treated as disabled, and the credentials would be missing.

---

## 8. AVETMISS field mapping (form → RTO Data `values`)

Mapping code is in `EnrolmentApiService::buildEnrolArgs` (`app/Services/EnrolmentApiService.php:173-248`). Form option values are in `resources/views/enrolments/registration-form.blade.php`.

| Form field (line) | Form values | Enrol arg | Transformation | Notes |
|---|---|---|---|---|
| `first_name`, `last_name` (395, 404, readonly) | — | `first_name`, `last_name` | Taken from `Student` (`web.php:257-258`), trimmed | Required by the service (`:241-245`) |
| `date_of_birth` (413, `type=date`) | `YYYY-MM-DD` | `date_of_birth` | `strtotime` → `Y-m-d` (`:409-422`) | An unparseable value becomes `null` and the service throws |
| — | — | `course_id` | `(int)(course.ams_enrolment_code ?? submission.code ?? 0)` (`:181`) | Falls back to the hidden form field `code` |
| — | — | `plan_id` | `(int)(course.ams_plan_id ?? submission.plan ?? 0)` (`:182`) | Falls back to the hidden form field `plan`. The checkout's actual `plan_id` and `schedule_id` are not used. |
| `title` (383) | Mr, Ms, Mrs, Miss | `salutation` | as-is | |
| `gender` (422) | F, M, X, @ | `gender` | as-is, **default `'M'`** if absent (`:185`) | |
| `city_of_birth` (436) | free text | `town_of_birth` | as-is | |
| `country_of_birth` (444) | SACC codes from `config/enrolment_countries.php` | `country_of_birth` | as-is | |
| `usi` (465, maxlength 10) | free text | `usi_number` | `usi_number ?? usi` | No format or checksum validation |
| **State:** `state` (523) | NSW, VIC, QLD, WA, SA, TAS, ACT, NT | `state` | `mapState` → `01`–`08` (`:424-437`); a 2-digit input passes through | An unknown value becomes `null` and is dropped from the query string |
| `street_number`, `street_name`, `suburb`, `postcode` | free text | same names | as-is | |
| `building_name`, `unit_number` (480, 488) | free text | — | **not sent** | |
| `address_country` (547) | free text, default "Australia" | — | **not sent**; `country` is hardcoded `1101` (`:195`) | |
| **Postal address:** `postal_address_type` (555) | `same` / `different` | `postal_flag` | `1` if `different`, else `0` (`:218-230`) | |
| `postal_box`, `postal_building_property`, `postal_unit_flat`, `postal_street_number`, `postal_street_name`, `postal_suburb`, `postal_postcode` | free text | same names | trimmed, only when `different` | |
| `postal_state` (608) | `01`–`08`, `09`, `99` | `postal_state` | `mapState` passes 2-digit codes through | |
| `postal_country` (633) | SACC codes | — | **not sent** | |
| `email` (654, readonly) | — | `email` | from `Student` | |
| `mobile_phone` (663, pattern `^04\d{8}$`) | digits | `mobile_phone` | `mobile_phone ?? phone ?? student.phone` | Pattern is client-side only |
| `main_language_home` (830) | ASCL codes from `config/enrolment_languages.php` | `main_language` | as-is, default `1201` | |
| **Highest school level:** `school_level` (691) | `02`, `08`–`12`, `@@` | `highest_school_level` | **`(int)` cast** (`:211`): `'02'`→`2`, `'08'`→`8`, `'09'`→`9`, `'@@'`→`0` | Leading zeros and the not-stated code are lost |
| `currently_at_school` (704) | Y, N, @ | `at_school_flag` | `(string)` | |
| **Prior achievement:** `other_qualifications` (717) + `qualification_level[]` (746) | `Yes`/`No`; checkbox values `prior_achievement[all][008]`, `[410]`, `[420]`, `[511]`, `[514]`, `[521]`, `[524]`, `[990]` | `prior_achievement_flag` + `prior_achievement[all][code]=1` and `prior_achievement[vic][code]=A` | Regex `\[(\d+)\]$` extracts the code (`:473-513`) | `[vic]` is always populated regardless of state. Flag `Y` if any codes, else `N`. |
| **Study reason:** `study_reason` (774) | `01`–`08`, `11`, `12`, `13` | — | **not sent** | Collected and stored in `form_data` only |
| **Labour force:** `labour_force` (804) | `01`–`07`, `@@` | `labour_force` | as-is (`labour_force ?? employment_status`) | |
| **Indigenous status:** `atsi_status` (817) | `No`, `Yes, Aboriginal`, `Yes, Torres Strait Islander` | `indigenous_status` | `ATSI_MAP` → `4`, `1`, `2`; unknown → `@` (`:439-448`) | The form offers no "both" (`3`) or "not stated" (`@`) option, although the map supports them |
| `disability` (851) | N, Y | `disability_flag` | as-is | |
| **Disability types:** `disability_type[]` (882) | `11`–`19`, `99` | `disability_type[11..99]` | All 10 keys are always sent as `1`/`0`. `99` zeroes the others (`:450-471`). | Sent even when `disability = N`; `Y` with nothing ticked sends all zeros |
| `individual_needs` (895) + `individual_needs_specify` (908) | No/Yes + text | `individual_needs` | `''` if No, else the specify text (`:515-532`) | |
| — | — | `client_id` | `form_data['client_id'] ?? ''` (`:213`) | No such input or validation rule exists, so it is always `''` |
| Declarations `agree_*` (952-983) | `1` | — | **not sent** | Stored in `form_data` only. Not server-required. |

---

## 9. Tests

There are **no tests** for `GET`/`POST /enrol/{token}`, `CreateEnrolmentJob`, `SendEnrolmentEmailJob`, `SendEnrolmentSmsJob`, `SubmitEnrolmentToApiJob`, `EnrolmentApiService` or `RtoDataCourseService`. A search of `tests/` for "enrol" finds only a comment at `tests/Feature/CardPaymentControllerTest.php:392`.

Indirect coverage only: `Queue::assertPushed(ProcessOrderJob::class)` in `tests/Feature/CardPaymentControllerTest.php:145,459,496` and `tests/Feature/PurchaseOrderAccessTest.php:133`.

---

## 10. Problems noticed

### Duplicate-enrolment and retry risks

- **P1. A retry after a timeout or lost response on `/enrol` can create a second RTO Data enrolment.** `external_reference` is saved only after a successful parsed response (`EnrolmentApiService.php:113-134`). The job retries 5 times (`SubmitEnrolmentToApiJob.php:16-18`). See §4.3 items 1-2.
- **P2. Concurrent execution of the same submission job.** Worst-case runtime (about 120 s) exceeds the database queue's `retry_after` of 90 s (`config/queue.php:42`). There is no `$timeout`, `ShouldBeUnique` or `WithoutOverlapping` on `SubmitEnrolmentToApiJob`.
- **P3. `external_reference` is likely overwritten with `null` on success.** The service stores the RTO ID under `response.enrolment_id` and top-level `external_reference` (`EnrolmentApiService.php:107-110,121-123,165`). The job instead reads `response.id`, `response.reference` or `response.external_reference` (`SubmitEnrolmentToApiJob.php:95-98`) and writes that value back (`:105`). Unless RTO Data's response also contains one of those keys, the saved ID is replaced with `null`. The ID survives only inside `api_response_payload`.
- **P4. Every retry re-sends all documents**, including ones already accepted (`EnrolmentApiService.php:145-147`).
- **P5. Deterministic failures are retried pointlessly.** These fail on every attempt: a missing required field (`:241-245`), an unsupported MIME type, a missing file (`:293-310`), a missing student email or phone (`SendEnrolmentEmailJob.php:66-79`, `SendEnrolmentSmsJob.php:46-59`). A failure in `buildPayload` happens before the try block (`SubmitEnrolmentToApiJob.php:48`), so the submission stays `pending` or `processing` instead of `failed`.
- **P6. Double form submission is not guarded by a lock.** Two concurrent POSTs both pass the `enrolment_completed_at` check (`web.php:148`) and both store files. The second hits the unique index on `enrolment_submissions.enrolment_id` (`2026_05_25_025901_…:48`) and returns a 500, leaving orphaned files.
- **P7. Non-atomic submit sequence.** The steps run in this order with no transaction: create submission (`web.php:309`), dispatch job (`:323`), mark enrolment completed (`:325`). If dispatch or the update fails, the link stays usable, but every later POST hits the unique index and returns a 500. With a `sync` queue connection, the RTO call would run inline before the link is marked used.
- **P8. Admin Retry and Resend re-email and re-SMS completed students.** `OrdersTable.php:381-388,497-502,519-524` dispatch with `forceResend = true` for every enrolment on the order, including those with `enrolment_completed_at` set. Those students receive a link that now redirects to the failure page.
- **P9. Submissions skipped while the API is disabled are never re-queued** (`SubmitEnrolmentToApiJob.php:73-93`). No admin action exists to resubmit a submission (`EnrolmentSubmissionResource.php:26-40`).
- **P10. A failed final attempt has no `failed()` handler and sends no alert.** The submission shows `failed` between attempts as well as after the last one.

### Data correctness

- **P11. The plan sent to RTO Data is not the plan the customer bought.** `plan_id` comes from `course.ams_plan_id` (`EnrolmentApiService.php:182`). The checkout's `plan_id` and `schedule_id` are not stored on `order_items`. `resolveCourseId` can also fall back to "same code, any plan" (`CheckoutOrderCreator.php:164-170`). `schedule_id` is never sent to `/enrol`.
- **P12. Only the first order item is enrolled** (`CreateEnrolmentJob.php:31`). If `course_id` is null because the course was not matched, the hidden `code`/`plan` are also empty (`CreateEnrolmentJob.php:310-316`), and the RTO submission fails on every attempt.
- **P13. Mapping losses:**
  - `highest_school_level` is cast with `(int)` (`:211`).
  - `study_reason`, `building_name`, `unit_number`, `address_country` and `postal_country` are not sent.
  - `country` is hardcoded to `1101`.
  - `gender` defaults to `M`.
  - `disability_type` is sent when the flag is `N`.
  - ATSI options are incomplete.
  - `prior_achievement[vic]` is always sent.
  - `client_id` is always empty.
  - The `@@` codes for labour force and school level are inconsistent with the integer cast.
- **P14. `orders.status` becomes `processed`** once emails are sent and Xero succeeds (`OrderCompletion.php:31-39`), whether or not the student ever enrols or RTO Data accepts it.

### Validation gaps

- **P15. All POST fields are `nullable` server-side** (`web.php:159-244`). Required fields, the mobile pattern, declarations (no `accepted` rule), USI format, postcode and DOB format are enforced only in the browser.
- **P16. Uploads have no MIME or extension validation** (`web.php:240-243`). Bad types are only caught at send time, after the RTO enrolment already exists, which leaves the job permanently failing.
- **P17. No server-side check that conditional detail is provided:** disability types when `disability = Y`, qualifications when `other_qualifications = Yes`, specify text when `individual_needs = Yes`, or postal fields when the postal address is different.

### Security

- **P18. Hidden `code`/`plan` fields are client-controlled.** They are stored from the request (`web.php:314-315`) and used as the RTO `course_id`/`plan_id` whenever the linked course lacks an AMS mapping (`EnrolmentApiService.php:181-182`). A student could alter them in dev tools.
- **P19. `/enrol/{token}` GET and POST have no rate limiting** (`web.php:90,139`). The token is long and random, so guessing is impractical, but nothing limits upload abuse by a valid token holder before submission completes.
- **P20. The RTO Data public key is persisted in the database.** `submit()` returns `request_body` containing `public_key` (`EnrolmentApiService.php:74-78,87,169`), and the job stores the whole result in `enrolment_submissions.api_response_payload` (`SubmitEnrolmentToApiJob.php:76,103`).
- **P21. Personal data is logged in plain text.** Enrol args (DOB, USI, address, disability, indigenous status) are written to `enrolment_submissions.api_request_payload` (`SubmitEnrolmentToApiJob.php:55`) and to `integration_logs.request_payload` (`:113,132`). Error messages embed full RTO response bodies and headers (`EnrolmentApiService.php:263-283`). Integration logs also store enrolment tokens and links (`CreateEnrolmentJob.php:138-139,216`).
- **P22. Hardcoded RTO Data widget credentials** in a Blade view (`header.blade.php:397-400`).
- **P23. Raw exception text is shown to visitors** on a checkout-load failure (`CheckoutLoadController.php:36`).
- **P24. Uploaded identity documents are never deleted** (§5), and there is no retention policy.
- **P25. Ownership checks:** the POST resolves the enrolment only from the token, and the posted `enrolment_id` is validated but ignored (`web.php:160`), so it cannot be used to target another record. No other public endpoint accepts enrolment IDs or secret keys. The admin `EnrolmentResource` allows creating and editing raw enrolment rows, including `status` (`EnrolmentForm.php:15-37`), with no audit trail.

### Configuration

- **P26. `env()` is called outside config** in `SubmitEnrolmentToApiJob.php:66`, `SendEnrolmentSmsJob.php:61,98-100` and `CreateEnrolmentJob.php:308`. These calls break under `config:cache`.
- **P27. `ENROLMENT_API_ORIGIN` is duplicated** in `.env` (lines 8 and 83). `config/services.php:66` calls `config()` inside a config file.

### Unused or dead code

- `enrolments.secret_key` is generated and stored but never read (`CreateEnrolmentJob.php:172,190`).
- `enrolments.secret_base_url` exists only to carry `code`/`plan` into the form. Its default base `url('/registration-form')` points at a route that does not exist (`CreateEnrolmentJob.php:308`).
- `enrolments.external_enrolment_id` (`ENR-…`) is a local placeholder that is never sent anywhere (`CreateEnrolmentJob.php:168`).
- These validation keys have no corresponding form input: `main_language`, `usi_number`, `email_address`, `phone`, `highest_school_level`, `employment_status`, `support_needs`, `notes`, `student_declaration`, `privacy_declaration`, `documents[]`, `enrolment_id` (`web.php:159-244`).
- The form prefills `$student->title`, `gender`, `city_of_birth`, `usi`, `building_name`, `state` and others (`registration-form.blade.php:386-548`). These columns do not exist on `students`, so the prefills are always empty.
- `$requestBody` in `submit()` (`EnrolmentApiService.php:74-78`) is used only for the return value and logging. `callEnrol` rebuilds the real body (`:252-256`).
- `EnrolmentSubmissionForm` (`app/Filament/Resources/EnrolmentSubmissions/Schemas/EnrolmentSubmissionForm.php`) is unreachable because the resource disallows create and edit.
- `ProcessTestOrderJob` is never dispatched.
- The migration comment for `enrolments.status` (`pending, processing, success, failed`) does not match the values actually used (`link_created`, `link_sent`, `completed`).
