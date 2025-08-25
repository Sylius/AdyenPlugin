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

        const _showErrorMessage = (message) => {
            _clearErrorMessage();

            const errorElement = document.createElement('div');
            errorElement.className = 'adyen-payment-error';
            errorElement.innerHTML = `
                <span class="error-message">${message}</span>
                <button class="error-close" onclick="this.parentElement.remove()">×</button>
            `;

            errorElement.style.cssText = `
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                border-radius: 4px;
                padding: 12px 15px;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 14px;
                animation: fadeIn 0.3s ease-in;
            `;

            $container.parentElement.insertBefore(errorElement, $container);
        }

        const _clearErrorMessage = () => {
            const existingError = document.querySelector('.adyen-payment-error');
            if (existingError) {
                existingError.remove();
            }
        }

        const _onSubmitHandler = (e) => {
            if ($container.classList.contains('hidden')) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
        };

        const submitHandler = (state, dropin, url, actions) => {
            _clearErrorMessage();

            const options = {
                method: 'POST',
                body: JSON.stringify(state.data),
                headers: {
                    'Content-Type': 'application/json'
                }
            }

            _toggleLoader(true);

            return fetch(url, options)
                .then((response) => {
                    if (response.status >= 400 && response.status < 600){
                        return response.json().then(errorData => Promise.reject(errorData));
                    }

                    return response.json();
                })
                .then(data => {
                    _toggleLoader(false);

                    if (data.action) {
                        dropin.handleAction(data.action);
                    } else if (data.redirect) {
                        window.location.replace(data.redirect);
                    }

                    return data;
                })
                .catch(error => {
                    _toggleLoader(false);

                    if (error && error.error === true) {
                        _showErrorMessage(error.message);
                        actions.reject(error.message);
                    } else {
                        _showErrorMessage('Payment processing failed. Please try again.');
                        actions.reject();
                    }

                    if (dropin && typeof dropin.setStatus === 'function') {
                        setTimeout(() => {
                            dropin.setStatus('ready');
                        }, 100);
                    }

                    return undefined;
                });
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
                    submitHandler(state, dropin, configuration.path.payments, actions);
                },
                onAdditionalDetails: (state, dropin, actions) => {
                    submitHandler(state, dropin, configuration.path.paymentDetails, actions);
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
