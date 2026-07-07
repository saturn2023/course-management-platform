/*
 | Pin Payments Hosted Fields — client-side tokenization.
 |
 | Pin requires exactly four Hosted Fields (name, number, cvc, expiry), each
 | rendered as a Pin-controlled iframe, so raw card data never enters this
 | page's DOM or our server. On success we submit ONLY the opaque card_token
 | (plus CSRF) to Laravel, which derives the amount and everything else from
 | the CheckoutSession.
 |
 | The token and card data are never logged.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('pin-payment');
        if (!root) {
            return;
        }

        var publishableKey = root.dataset.publishableKey || '';
        var sandbox = root.dataset.sandbox === 'true';

        var payButton = document.getElementById('pin-pay-button');
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
                input: { 'font-size': '15px', color: '#34404f' }
            }
        });

        hostedFields.on('ready', function () {
            setBusy(false);
        });

        payButton.addEventListener('click', function (event) {
            event.preventDefault();

            // Prevent repeated clicks while in flight, after success, or before
            // the fields are ready.
            if (payButton.disabled || tokenized) {
                return;
            }

            clearError();
            setBusy(true);

            // Name is now collected by the Hosted Field, so it is NOT passed
            // here. Address fields and the publishable key are still supplied
            // as documented tokenization options.
            hostedFields.tokenize({
                address_line1: root.dataset.addressLine1 || '',
                address_city: root.dataset.addressCity || '',
                address_country: root.dataset.addressCountry || 'Australia',
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

                // SUCCESS: submit ONLY the opaque token (plus CSRF). Never logged.
                tokenized = true;
                payButton.disabled = true;
                payButton.textContent = 'Submitting…';

                statusBox.textContent = 'Your card was verified. Completing your payment…';
                statusBox.style.display = 'block';

                document.getElementById('pin-card-token').value = cardToken;
                document.getElementById('pin-charge-form').submit();
            });
        });
    });
})();
