/*
 | Pin Payments Hosted Fields — client-side tokenization.
 |
 | Registration, billing and payment are one form. Pin requires exactly four
 | Hosted Fields (name, number, cvc, expiry), each rendered as a Pin-controlled
 | iframe, so raw card data never enters this page's DOM or our server. On
 | success we add ONLY the opaque card_token to the checkout form and submit it
 | alongside the student and billing details; Laravel derives the amount and
 | everything else from the CheckoutSession.
 |
 | The token and card data are never logged.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('cardPane');
        var form = document.getElementById('checkoutForm');

        if (!root || !form) {
            return;
        }

        var publishableKey = root.dataset.publishableKey || '';
        var sandbox = root.dataset.sandbox === 'true';

        var payButton = document.getElementById('pin-pay-button');
        var tokenInput = document.getElementById('pin-card-token');
        var errorBox = document.getElementById('pin-error');
        var statusBox = document.getElementById('pin-status');

        function showError(message) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }

        function clearError() {
            errorBox.textContent = '';
            errorBox.style.display = 'none';
        }

        function setBusy(isBusy) {
            payButton.disabled = isBusy;
            payButton.textContent = isBusy ? 'Processing…' : 'Pay securely';
        }

        if (!publishableKey) {
            showError('Card payment is not available right now. Please contact us to complete your enrolment.');
            return;
        }

        if (typeof HostedFields === 'undefined') {
            showError('We could not load the secure card form. Please refresh the page and try again.');
            return;
        }

        var tokenized = false;

        /*
         | Billing address is typed on this same page, so read it at
         | tokenization time rather than from a server-rendered snapshot.
         */
        function billingValue(field) {
            var input = form.querySelector('[name="billing[' + field + ']"]');

            return input ? input.value : '';
        }

        // Pin requires exactly these four Hosted Fields.
        var hostedFields = HostedFields.create({
            publishable_api_key: publishableKey,
            sandbox: sandbox,
            fields: {
                name: { selector: '#pin-card-name', placeholder: 'Name on card' },
                number: { selector: '#pin-card-number', placeholder: '4242 4242 4242 4242' },
                cvc: { selector: '#pin-card-cvc', placeholder: '123' },
                expiry: { selector: '#pin-card-expiry', placeholder: 'MM / YY' }
            },
            styles: {
                input: { 'font-size': '15px', color: '#1f2937' }
            }
        });

        hostedFields.on('ready', function () {
            // Only take the button out of its loading state while card is the
            // selected method; switching to purchase order disables it again.
            if (!root.hidden) {
                setBusy(false);
            } else {
                payButton.textContent = 'Pay securely';
            }
        });

        // Re-entering the card pane after choosing purchase order must not
        // leave the button reading "Preparing secure form…".
        document.addEventListener('checkout:method-changed', function (event) {
            if (event.detail && event.detail.method === 'card' && !tokenized) {
                payButton.textContent = 'Pay securely';
            }
        });

        payButton.addEventListener('click', function (event) {
            event.preventDefault();

            // Prevent repeated clicks while in flight, after success, or before
            // the fields are ready.
            if (payButton.disabled || tokenized) {
                return;
            }

            /*
             | Check the student and billing fields before tokenizing. A Pin
             | token is single-use, so sending one with a payload the server
             | will reject would force the customer to re-enter their card.
             | The server still validates everything regardless.
             */
            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            clearError();
            setBusy(true);

            // Name is collected by the Hosted Field, so it is NOT passed here.
            // Address fields and the publishable key are still supplied as
            // documented tokenization options.
            hostedFields.tokenize({
                address_line1: billingValue('address_1'),
                address_city: billingValue('city'),
                address_country: 'Australia',
                publishable_api_key: publishableKey
            }, function (error, response) {
                if (error) {
                    var message = 'Please check your card details and try again.';

                    if (error && Array.isArray(error.messages) && error.messages.length) {
                        message = error.messages.map(function (m) {
                            return m && m.message ? m.message : '';
                        }).filter(Boolean).join(' ');
                    }

                    showError(message);
                    setBusy(false);
                    return;
                }

                var cardToken = response && response.token ? response.token : null;

                if (!cardToken) {
                    showError('We could not verify your card. Please check your details and try again.');
                    setBusy(false);
                    return;
                }

                // SUCCESS: submit the checkout form carrying the student and
                // billing details plus ONLY the opaque token. Never logged.
                tokenized = true;
                payButton.disabled = true;
                payButton.textContent = 'Submitting…';

                statusBox.textContent = 'Your card was verified. Completing your payment…';
                statusBox.style.display = 'block';

                tokenInput.disabled = false;
                tokenInput.value = cardToken;

                form.setAttribute('action', root.dataset.action);
                form.submit();
            });
        });
    });
})();
