/*
 * Enrolment v2 form driver. Port of the inline script in the reference's
 * ams2_enrolment_form() shortcode.
 *
 * It saves the enrolment first, with no files, then uploads each document in
 * its own request, then tells the server it has finished. Three requests rather
 * than one multipart POST, because that is what lets a single flaky upload be
 * retried without resubmitting the enrolment, and it keeps every request inside
 * upload_max_filesize instead of needing all the documents to fit at once.
 *
 * Every learner-facing string is the reference's, word for word.
 *
 * Two deliberate departures:
 *
 *  - WordPress nonces become Laravel's CSRF token, refreshed from a small
 *    endpoint and retried once on the `nonce` code, which is the same dance.
 *  - The reference beacons client-side diagnostics to an ams_log_client action.
 *    There is no such endpoint here, so those calls are dropped rather than
 *    pointed at something that would 404 on every file a learner rejects.
 */
(function () {
    var C = window.AMS2;
    var root = document.getElementById('ams2-enrol');
    if (!C || !root) return;

    var form = document.getElementById('ams2-form');
    var statusEl = document.getElementById('ams2-status');
    if (!form || !statusEl) return;

    /*
     * Extensions, not MIME types. A learner's file may carry no type at all,
     * and the server decides the real type from the contents anyway — this is
     * only to fail fast and visibly before anything is sent.
     */
    var ALLOWED = ['pdf', 'jpg', 'jpeg', 'jfif', 'jpe', 'png', 'webp'];
    var INPUTS = { id_document: 'id_document', vet_transcript: 'vet_transcript' };
    var LABELS = { id_document: 'ID document', vet_transcript: 'VET transcript' };
    var LIMIT = C.maxBytesLabel;

    var TYPE_MSG = 'This file type can\'t be uploaded. Please choose a PDF, JPG, PNG or WEBP file.';
    var READ_MSG = 'We couldn\'t read this file. Save it to your Desktop or Downloads folder first, then choose it again.';

    var SUBMIT_TIMEOUT = 60000;
    var UPLOAD_TIMEOUT = 180000;
    var TOKEN_TIMEOUT = 20000;
    var UPLOAD_ATTEMPTS = 4;
    var WATCHDOG_MS = 10 * 60 * 1000;

    var copies = {};
    var token = C.csrfToken;
    var working = false;

    /*
     * A learner who closes the tab mid-upload loses documents that nothing will
     * ask for again, so the browser is asked to warn them.
     */
    window.addEventListener('beforeunload', function (e) {
        if (!working) return;
        e.preventDefault();
        e.returnValue = '';
    });

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    /* Accents off, matching the server's own prefill. */
    function clean(s) {
        return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function mb(b) { return (b / 1048576).toFixed(1) + 'MB'; }
    function wait(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

    function box(kind, html) {
        statusEl.innerHTML = '<div class="ams-box ams-box--' + kind + '">' + html + '</div>';
    }

    function fieldError(input, msg) {
        var row = input.closest('.ef-upload-row') || input.parentNode;
        var el = row.querySelector('.upload-error');

        if (!msg) {
            if (el) el.remove();
            input.classList.remove('upload-error-field');
            return;
        }

        if (!el) {
            el = document.createElement('span');
            el.className = 'upload-error';
            el.setAttribute('role', 'alert');
            row.appendChild(el);
        }

        el.textContent = msg;
        input.classList.add('upload-error-field');
    }

    function readBuffer(f) {
        if (f.arrayBuffer) return f.arrayBuffer();

        return new Promise(function (resolve, reject) {
            var r = new FileReader();
            r.onload = function () { resolve(r.result); };
            r.onerror = function () { reject(r.error); };
            r.readAsArrayBuffer(f);
        });
    }

    /*
     * Checks the file's actual contents, so a renamed .docx or .zip cannot pass
     * as a PDF or image. The server checks again; this is so the learner is
     * told while they can still choose another file.
     */
    function kindOf(buf) {
        var head = new Uint8Array(buf, 0, Math.min(1024, buf.byteLength));

        for (var i = 0; i + 3 < head.length; i++) {
            if (head[i] === 0x25 && head[i + 1] === 0x50 && head[i + 2] === 0x44 && head[i + 3] === 0x46) return 'pdf';
        }

        var b = head;
        if (b.length >= 3 && b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) return 'jpg';
        if (b.length >= 4 && b[0] === 0x89 && b[1] === 0x50 && b[2] === 0x4E && b[3] === 0x47) return 'png';
        if (b.length >= 12 && b[0] === 0x52 && b[1] === 0x49 && b[2] === 0x46 && b[3] === 0x46 &&
            b[8] === 0x57 && b[9] === 0x45 && b[10] === 0x42 && b[11] === 0x50) return 'webp';

        return null;
    }

    function typeOf(input) {
        for (var t in INPUTS) { if (input.id === INPUTS[t]) return t; }
        return null;
    }

    /* ---------- Each file is copied into memory as soon as it is chosen ---------- */

    form.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.setAttribute('accept', '.pdf,.jpg,.jpeg,.jfif,.jpe,.png,.webp');
    });

    form.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || !input.matches || !input.matches('input[type="file"]')) return;

        var type = typeOf(input);
        if (!type) return;

        delete copies[type];
        fieldError(input, null);

        var f = input.files && input.files[0];
        if (!f) return;

        var ext = f.name.indexOf('.') === -1 ? '' : f.name.split('.').pop().toLowerCase();
        var problem = null;

        if (ALLOWED.indexOf(ext) === -1) problem = TYPE_MSG;
        else if (f.size === 0) problem = 'This file is empty. Please choose the file again or save a new copy first.';
        else if (f.size > C.maxBytes) problem = 'This file is ' + mb(f.size) + '. The maximum size is ' + LIMIT + '. Please choose a smaller file.';

        if (problem) {
            input.value = '';
            fieldError(input, problem);
            return;
        }

        /*
         * Read now, not at submit time. Files on cloud-synced folders can
         * become unreadable between being chosen and being sent, and a copy in
         * memory survives that — which is what READ_MSG is about.
         */
        copies[type] = readBuffer(f).then(function (buf) {
            if (!kindOf(buf)) {
                if (input.files[0] === f) { input.value = ''; fieldError(input, TYPE_MSG); }
                return null;
            }

            return new File([buf], f.name, {
                type: f.type || 'application/octet-stream',
                lastModified: f.lastModified
            });
        }).catch(function () {
            if (input.files[0] === f) { input.value = ''; fieldError(input, READ_MSG); }
            return null;
        });
    }, true);

    /* ---------- Requests ---------- */

    function post(url, fd, timeoutMs) {
        return new Promise(function (resolve) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.timeout = timeoutMs;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onload = function () {
                var json = null;
                try { json = JSON.parse(xhr.responseText); } catch (err) {}

                if (json && json.success) {
                    resolve({ ok: true, data: json.data, status: xhr.status });
                    return;
                }

                var d = (json && json.data) || {};
                resolve({
                    ok: false,
                    network: false,
                    code: d.code || '',
                    status: xhr.status,
                    message: d.message || 'The server returned an unexpected response (HTTP ' + xhr.status + ').'
                });
            };

            xhr.onerror = function () {
                resolve({ ok: false, network: true, status: 0, message: 'We could not reach the server. Check your internet connection.' });
            };

            xhr.ontimeout = function () {
                resolve({ ok: false, network: true, status: 0, message: 'The server took too long to respond.' });
            };

            xhr.send(fd);
        });
    }

    function refreshToken() {
        return new Promise(function (resolve) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', C.tokenUrl);
            xhr.timeout = TOKEN_TIMEOUT;
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.onload = function () {
                try {
                    var json = JSON.parse(xhr.responseText);
                    if (json && json.success && json.data && json.data.token) {
                        token = json.data.token;
                        resolve(true);
                        return;
                    }
                } catch (err) {}
                resolve(false);
            };

            xhr.onerror = function () { resolve(false); };
            xhr.ontimeout = function () { resolve(false); };
            xhr.send();
        });
    }

    /*
     * One retry on a stale token. A learner who left the form open past the
     * session lifetime gets a fresh one rather than losing what they typed.
     */
    function send(url, build, timeoutMs) {
        function attempt() {
            var fd = build();
            fd.set('_token', token);
            return post(url, fd, timeoutMs);
        }

        return attempt().then(function (r) {
            if (!r.ok && r.code === 'nonce') {
                return refreshToken().then(function (fresh) { return fresh ? attempt() : r; });
            }
            return r;
        });
    }

    function retryable(r) {
        return r.network || [429, 500, 502, 503, 504].indexOf(r.status) !== -1;
    }

    function uploadOne(doc, attempt) {
        return send(C.uploadUrl, function () {
            var fd = new FormData();
            fd.append('secretKey', C.secretKey);
            fd.append('id', C.requestId);
            fd.append('doc_type', doc.type);
            fd.append('document', doc.file, doc.file.name);
            return fd;
        }, UPLOAD_TIMEOUT).then(function (r) {
            if (r.ok) return { type: doc.type, ok: true };

            if (retryable(r) && attempt < UPLOAD_ATTEMPTS) {
                return wait(3000 * attempt).then(function () { return uploadOne(doc, attempt + 1); });
            }

            return { type: doc.type, ok: false };
        });
    }

    /* Sequential, so two large files never compete for the same connection. */
    function uploadAll(docs) {
        var results = [];
        var chain = Promise.resolve();

        docs.forEach(function (d) {
            chain = chain
                .then(function () { return uploadOne(d, 1); })
                .then(function (res) { results.push(res); });
        });

        return chain.catch(function () {}).then(function () {
            return docs.filter(function (d) {
                return !results.some(function (res) { return res && res.ok && res.type === d.type; });
            });
        });
    }

    /*
     * Sent with keepalive so it still goes as the page unloads. The server
     * answers success either way; a row that never gets this is picked up once
     * the documents step's 15-minute grace period expires.
     */
    function finalise() {
        var fd = new FormData();
        fd.append('secretKey', C.secretKey);
        fd.append('id', C.requestId);
        fd.set('_token', token);

        if (window.fetch) {
            try {
                return fetch(C.finaliseUrl, {
                    method: 'POST',
                    body: fd,
                    keepalive: true,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                }).catch(function () {});
            } catch (err) {}
        }

        return post(C.finaliseUrl, fd, TOKEN_TIMEOUT);
    }

    function finish() {
        working = false;
        window.location.href = C.successUrl;
    }

    function done() {
        finalise();
        box('ok', '<strong>&#10003; Enrolment successfully submitted.</strong> Taking you to the confirmation page…');
        finish();
    }

    function handleUploads(docs) {
        box('info', 'Uploading your documents… Please keep this page open.');

        /*
         * However badly the uploads go, the learner is not left on this page
         * indefinitely: they are already enrolled, and the documents can be
         * emailed in.
         */
        var timedOut = false;
        var watchdog = setTimeout(function () {
            timedOut = true;
            done();
        }, WATCHDOG_MS);

        return uploadAll(docs).then(function (failed) {
            clearTimeout(watchdog);
            if (timedOut) return;

            if (!failed.length) { done(); return; }

            working = false;

            var names = failed.map(function (d) { return LABELS[d.type] || d.type; }).join(' and ');

            statusEl.innerHTML = '<div class="ams-box ams-box--bad"><strong>Your enrolment has been saved, but your ' + esc(names) + ' did not upload.</strong><br>'
                + 'This is usually a brief connection problem. Press Try again. If it still does not work, press Continue and email the document to '
                + '<a href="mailto:sales@amstraining.com.au">sales@amstraining.com.au</a>.</div>'
                + '<button type="button" class="ams-retry" id="ams2-retry">Try again</button> '
                + '<button type="button" class="ams-continue" id="ams2-continue">Continue</button>';

            document.getElementById('ams2-retry').onclick = function () {
                working = true;
                handleUploads(failed);
            };

            document.getElementById('ams2-continue').onclick = function () {
                done();
            };
        });
    }

    /* ---------- Submit ---------- */

    var submitWrap = form.querySelector('.ef-submit');
    var btn = document.getElementById('ams2-submit');
    submitWrap.parentNode.insertBefore(statusEl, submitWrap);
    var busy = false;

    function resetButton() {
        busy = false;
        working = false;
        btn.disabled = false;
        btn.textContent = 'Register';
    }

    function blockedByFileError() {
        var bad = form.querySelector('.upload-error-field');
        if (!bad) return false;

        box('bad', 'Please choose a different file where shown before submitting.');
        bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return true;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) return;
        if (blockedByFileError()) return;

        busy = true;
        working = true;
        btn.disabled = true;
        btn.textContent = 'Submitting enrolment…';
        box('info', 'Submitting your enrolment… This can take a minute. Please keep this page open.');

        var types = Object.keys(copies);

        Promise.all(types.map(function (t) { return copies[t]; })).then(function (files) {
            var docs = [];
            files.forEach(function (file, i) { if (file) docs.push({ type: types[i], file: file }); });

            // A file may have been rejected while its contents were being read.
            if (form.querySelector('.upload-error-field')) {
                resetButton();
                blockedByFileError();
                return;
            }

            return send(C.submitUrl, function () {
                var fd = new FormData(form);
                fd.delete('id_document');
                fd.delete('vet_transcript');
                fd.delete('documents[]');
                fd.set('secretKey', C.secretKey);
                fd.set('doc_expected', docs.map(function (d) { return d.type; }).join(','));
                return fd;
            }, SUBMIT_TIMEOUT).then(function (r) {
                if (!r.ok) {
                    resetButton();

                    /*
                     * "We could not confirm" and "has not been submitted" are
                     * different situations and the wording keeps them apart: a
                     * network failure may have saved the enrolment anyway, and
                     * pressing Register again is safe because a resubmit
                     * returns the same row.
                     */
                    if (r.network) {
                        box('bad', '<strong>We could not confirm your enrolment.</strong><br>' + esc(r.message) +
                            ' Press Register to try again. You will not be enrolled twice.');
                    } else {
                        box('bad', '<strong>Your enrolment has not been submitted.</strong><br>' + esc(r.message));
                    }

                    statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                C.requestId = r.data.id;

                form.querySelectorAll('.ef-section, .ef-submit').forEach(function (el) {
                    el.style.display = 'none';
                });

                statusEl.scrollIntoView({ behavior: 'smooth', block: 'start' });

                if (!docs.length) { done(); return; }

                return handleUploads(docs);
            });
        });
    });
})();
