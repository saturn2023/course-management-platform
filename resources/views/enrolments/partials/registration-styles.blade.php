{{--
    Styles for the enrolment registration form.

    Shared by the v1 form (enrolments.registration-form) and the v2 form, so a
    change to one is a change to both. The v2 form adds its own status-box
    styles on top of these rather than restating them.
--}}
<style>
    /*
     * The site header above this is full-bleed, and the browser's default
     * body margin of 8px shows as a white strip down both sides of it. The
     * page gutter belongs to .ef-wrapper below, not to <body>.
     */
    body {
        margin: 0;
    }

    .ef-wrapper {
        max-width: 760px;
        margin: 0 auto;
        padding: 30px 20px 60px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #333;
        background: #fff;
    }

    .ef-top-heading {
        color: #1a3d6d;
        font-size: 28px;
        font-weight: 700;
        margin: 25px 0 10px;
    }

    .ef-top-text {
        font-size: 15px;
        line-height: 1.6;
        color: #444;
        margin: 0 0 20px;
    }

    .ef-important-warning {
        color: #d9534f;
        font-size: 15px;
        line-height: 1.5;
        margin: 20px 0 30px;
    }

    .ef-title {
        display: none;
    }

    .ef-form {
        margin-top: 30px;
    }

    .ef-section {
        border: 1px solid #ddd;
        margin-bottom: 25px;
        background: #fff;
    }

    .ef-legend {
        background: #f5f5f5;
        padding: 12px 18px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #555;
        border-bottom: 1px solid #e3e3e3;
    }

    .ef-row {
        display: flex;
        border-bottom: 1px solid #e3e3e3;
        align-items: stretch;
    }

    .ef-row:last-child {
        border-bottom: none;
    }

    .ef-label {
        flex: 0 0 35%;
        background: #f7f7f7;
        padding: 14px 18px;
        font-size: 14px;
        color: #555;
        display: flex;
        align-items: center;
        border-right: 1px solid #e3e3e3;
    }

    .ef-field {
        flex: 1;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        background: #fff;
    }

    .ef-field input[type="text"],
    .ef-field input[type="email"],
    .ef-field input[type="tel"],
    .ef-field input[type="date"],
    .ef-field input[type="number"],
    .ef-field select,
    .ef-field textarea {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background: #fff;
        font-size: 14px;
        color: #333;
        font-family: inherit;
        box-sizing: border-box;
    }

    .ef-field input:focus,
    .ef-field select:focus,
    .ef-field textarea:focus {
        outline: none;
        border-color: #999;
    }

    .ef-req {
        color: #d9534f;
        margin-left: 2px;
    }

    .ef-row--full {
        display: block;
    }

    .ef-field--full {
        padding: 14px 18px;
        background: #fff;
    }

    .ef-sublabel {
        font-size: 14px;
        color: #555;
        margin: 0 0 10px;
        font-weight: 600;
    }

    .ef-cb-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .ef-cb-group label {
        font-size: 14px;
        color: #444;
        font-weight: normal;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        cursor: pointer;
    }

    .ef-cb-group input[type="checkbox"] {
        margin-top: 3px;
    }

    .ef-upload-row {
        padding: 16px 18px;
        border-bottom: 1px solid #e3e3e3;
        background: #fff;
    }

    .ef-upload-row:last-child {
        border-bottom: none;
    }

    .ef-upload-row label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .ef-upload-row span {
        display: block;
        font-size: 13px;
        color: #777;
        margin-bottom: 10px;
    }

    .ef-upload-row input[type="file"] {
        font-size: 13px;
    }

    .ef-privacy-notice {
        padding: 18px;
        font-size: 14px;
        line-height: 1.6;
        color: #444;
        border-bottom: 1px solid #e3e3e3;
        background: #fff;
    }

    .ef-privacy-notice strong {
        display: block;
        font-size: 15px;
        margin-bottom: 8px;
        color: #333;
    }

    .ef-privacy-notice ul {
        margin: 8px 0;
        padding-left: 22px;
    }

    .ef-privacy-notice li {
        margin: 3px 0;
    }

    .ef-privacy-notice a {
        color: #2a7ca8;
        text-decoration: underline;
    }

    .ef-check-row {
        padding: 14px 18px;
        border-bottom: 1px solid #e3e3e3;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #fff;
    }

    .ef-check-row:last-child {
        border-bottom: none;
    }

    .ef-check-row input[type="checkbox"] {
        margin-top: 3px;
        flex: 0 0 auto;
    }

    .ef-check-row label {
        font-size: 13px;
        line-height: 1.5;
        color: #444;
        font-weight: 600;
        cursor: pointer;
    }

    .ef-submit {
        margin-top: 20px;
    }

    .ef-submit button {
        width: 100%;
        padding: 16px 20px;
        background: #87c65a;
        color: #fff;
        border: none;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 3px;
        transition: background 0.15s;
    }

    .ef-submit button:hover {
        background: #79b84d;
    }

    .field-error {
        color: #d9534f;
        font-size: 13px;
        margin-top: 4px;
        width: 100%;
    }

    .ef-error-summary {
        background: #fdecea;
        border: 1px solid #d9534f;
        color: #a94442;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
        border-radius: 3px;
    }

    .ef-error-summary ul {
        margin: 6px 0 0;
        padding-left: 20px;
    }

    /* File size modal */
    .tm-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .tm-modal-content {
        background: #fff;
        padding: 30px;
        border-radius: 4px;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .tm-modal-content h3 {
        margin: 0 0 12px;
        color: #d9534f;
    }

    .tm-modal-content p {
        margin: 0 0 18px;
        color: #444;
        font-size: 14px;
        line-height: 1.5;
    }

    .tm-modal-content button {
        background: #87c65a;
        color: #fff;
        border: none;
        padding: 10px 30px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-radius: 3px;
    }

    @media (max-width: 600px) {
        .ef-row {
            flex-direction: column;
        }
        .ef-label {
            flex: 1 1 auto;
            border-right: none;
            border-bottom: 1px solid #e3e3e3;
        }
    }
</style>
