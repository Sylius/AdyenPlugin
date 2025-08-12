(() => {
    const SELECTORS = {
        CONTAINER: 'adyen-express-checkout',
        GOOGLEPAY_MOUNT: '#googlepay-container'
    };

    const initExpressCheckout = async ($container) => {
        const configUrl = $container.getAttribute('data-config-url');
        if (!configUrl) return;

        const createFetchOptions = (data) => ({
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const handleResponse = async (response) => {
            if (response.status >= 400 && response.status < 600) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        };

        const _loadConfiguration = async (url) => {
            const response = await fetch(url);
            return await handleResponse(response);
        };


        const handlePaymentDataChanged = async (intermediatePaymentData) => {
            try {
                const { shippingAddress, shippingOptionData } = intermediatePaymentData;
                const paymentDataRequestUpdate = {};

                const response = await fetch(configuration.path.shippingOptions, createFetchOptions({
                    shippingAddress,
                    shippingOptionId: shippingOptionData.id
                }));
                const data = await handleResponse(response);

                paymentDataRequestUpdate.newShippingOptionParameters = data.shippingOptionParameters;
                paymentDataRequestUpdate.newTransactionInfo = data.transactionInfo;

                return paymentDataRequestUpdate;
            } catch (error) {
                throw error;
            }
        };

        const handleAuthorized = async (paymentData, actions) => {
            try {
                const { email, shippingAddress, shippingOptionData } = paymentData.authorizedEvent;

                const response = await fetch(configuration.path.checkout, createFetchOptions({
                    email,
                    shippingAddress,
                    shippingOptionId: shippingOptionData.id
                }));

                const data = await handleResponse(response);
                actions.resolve(data);
            } catch (error) {
                actions.reject(error.message);
            }
        };

        const handleSubmit = async (state) => {
            try {
                const response = await fetch(configuration.path.payments, createFetchOptions(state.data));
                const data = await handleResponse(response);
                window.location.replace(data.redirect);
            } catch (error) {
                console.error('Payment submission failed:', error);
            }
        };

        const createGooglePayConfig = (configuration) => ({
            isExpress: true,
            emailRequired: true,
            callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'],
            shippingAddressRequired: true,
            shippingAddressParameters: {
                allowedCountryCodes: configuration.allowedCountryCodes,
                phoneNumberRequired: false,
            },
            shippingOptionRequired: configuration.shippingOptionRequired,
            transactionInfo: configuration.transactionInfo,
            paymentDataCallbacks: {
                onPaymentDataChanged: handlePaymentDataChanged
            },
            onAuthorized: handleAuthorized,
            onSubmit: handleSubmit,
        });

        const { AdyenCheckout, GooglePay } = window.AdyenWeb;

        const configuration = await _loadConfiguration(configUrl);

        const checkout = await AdyenCheckout({
            paymentMethodsResponse: configuration.paymentMethods,
            clientKey: configuration.clientKey,
            locale: configuration.locale,
            environment: configuration.environment,
            countryCode: configuration.allowedCountryCodes[0],
        });

        const googlePay = new GooglePay(checkout, createGooglePayConfig(configuration));


        googlePay
            .isAvailable()
            .then(() => {
                googlePay.mount(SELECTORS.GOOGLEPAY_MOUNT);
            })
            .catch(e => {
                console.error('Google Pay is not available:', e);
            });
    };

    document.addEventListener('DOMContentLoaded', () => {
        const $container = document.getElementById(SELECTORS.CONTAINER);
        if ($container) {
            initExpressCheckout($container);
        }
    });
})();
