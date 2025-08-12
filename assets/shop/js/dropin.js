(() => {
    const instantiate = async ($container) => {

        let checkout = null;
        let configuration = {};
        let $form = $container.closest('form');

        const { AdyenCheckout, Dropin, Card } = window.AdyenWeb;

        const _toggleLoader = (show) => {
            const $form = $container.closest('form');
            show ? $form.classList.add('loading') : $form.classList.remove('loading');
        }

        const _loadConfiguration = async (url) => {
            _toggleLoader(true);
            const request = await fetch(url);
            const configuration = await request.json();
            _toggleLoader(false);

            if (typeof configuration['redirect'] == 'string') {
                _toggleLoader(true);
                window.location.replace(configuration['redirect']);
            }

            return configuration;
        }

        const _successfulFetchCallback = (dropin, data) => {
            if (data.resultCode && ['Refused', 'Cancelled', 'Error'].includes(data.resultCode)) {
                _toggleLoader(false);
                dropin.setStatus('error', { message: data.refusalReason || 'Payment was declined. Please try a different payment method.' });

                return;
            }

            if (data.action) {
                _toggleLoader(false);
                dropin.handleAction(data.action);
                return;
            }

            window.location.replace(data.redirect)
        }

        const _onSubmitHandler = (e) => {
            if ($container.classList.contains('hidden')) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
        };

        const submitHandler = (state, dropin, url) => {
            const options = {
                method: 'POST',
                body: JSON.stringify(state.data),
                headers: {
                    'Content-Type': 'application/json'
                }
            }

            _toggleLoader(true);

            fetch(url, options)
                .then(async (response) => {
                    let data;
                    const contentType = response.headers.get("content-type");

                    if (contentType && contentType.includes("application/json")) {
                        try {
                            data = await response.json();
                        } catch (jsonError) {
                            return Promise.reject({
                                error: 'Invalid response format from server',
                                statusCode: response.status
                            });
                        }
                    } else {
                        return Promise.reject({
                            error: 'Server returned an unexpected response. Please try again.',
                            statusCode: response.status,
                            isHtml: true
                        });
                    }

                    if (response.status >= 400 && response.status < 600){
                        return Promise.reject(data);
                    }

                    return Promise.resolve(data);
                })
                .then(data => {
                    _successfulFetchCallback(dropin, data);
                })
                .catch(error => {
                    _toggleLoader(false);

                    let errorMessage = '';
                    if (error && typeof error === 'object') {
                        if (error.resultCode && ['Refused', 'Cancelled', 'Error'].includes(error.resultCode)) {
                            errorMessage = error.refusalReason || 'Payment failed';
                        } else if (error.error) {
                            errorMessage = error.error;
                        } else {
                            errorMessage = 'An error occurred while processing your payment. Please try again.';
                        }
                    } else {
                        errorMessage = 'Connection error. Please check your internet connection and try again.';
                    }

                    dropin.setStatus('error', { message: errorMessage });

                    setTimeout(() => {
                        dropin.setStatus('ready');
                    }, 3000);

                    console.error('Payment submission error:', error);
                })
            ;
        };

        const injectOnSubmitHandler = () => {

            if (!$form) {
                return;
            }

            const $buttons = $form.querySelectorAll('[type=submit]');

            $form.addEventListener('submit', _onSubmitHandler, true);

            $buttons.forEach(($btn) => {
                $btn.addEventListener('click', _onSubmitHandler, true);
            });
        };

        const disableStoredPaymentMethodHandler = (storedPaymentMethod, resolve, reject) => {
            const options = {
                method: 'DELETE'
            };

            let url = configuration.path.deleteToken.replace('_REFERENCE_', storedPaymentMethod);

            fetch(url, options)
                .then(resolve)
                .catch(reject)
            ;
        };

        const init = async () => {
            injectOnSubmitHandler();

            return await AdyenCheckout({
                paymentMethodsResponse: configuration.paymentMethods,
                clientKey: configuration.clientKey,
                locale: configuration.locale,
                environment: configuration.environment,
                countryCode: configuration.billingAddress.countryCode,

                onSubmit: (state, dropin, actions) => {
                    submitHandler(state, dropin, configuration.path.payments, actions)
                },
                onAdditionalDetails: (state, dropin, actions) => {
                    submitHandler(state, dropin, configuration.path.paymentDetails, actions)
                },
                onPaymentCompleted: (result, component) => {
                    _toggleLoader(false);
                    console.info(result, component);
                },
                onPaymentFailed: (result, component) => {
                    _toggleLoader(false);
                    console.error('Payment failed:', result);
                },
                onError: (error, component) => {
                    _toggleLoader(false);
                    console.error(error.name, error.message, error.stack, component);
                }
            });
        };

        configuration = await _loadConfiguration($container.attributes['data-config-url'].value);
        checkout = await init();

        const dropin = new Dropin(checkout, {
            paymentMethodsConfiguration: {
                card: {
                    hasHolderName: true,
                    holderNameRequired: true,
                    enableStoreDetails: configuration.canBeStored,
                },
                paypal: {
                    environment: configuration.environment,
                    countryCode: configuration.billingAddress.countryCode,
                    amount: {
                        currency: configuration.amount.currency,
                        value: configuration.amount.value
                    }
                },
                applepay: {
                    countryCode: configuration.billingAddress.countryCode,
                    amount: {
                        currency: configuration.amount.currency,
                        value: configuration.amount.value
                    }
                }
            },
            showRemovePaymentMethodButton: true,
            onDisableStoredPaymentMethod: disableStoredPaymentMethodHandler
        });

        dropin.mount($container);
    };

    document.addEventListener('DOMContentLoaded', (e) => {
        document.querySelectorAll('.dropin-container').forEach(instantiate);
    })
})();
