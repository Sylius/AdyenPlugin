import { AdyenCheckout, ApplePay, GooglePay, PayPal } from '@adyen/adyen-web';
import { SELECTORS } from '../constants.js';
import { loadConfiguration } from '../utils.js';
import { ApplePayHandler } from './applepay.js';
import { GooglePayHandler } from './googlepay.js';
import { PayPalHandler } from './paypal.js';

const initExpressCheckout = async ($container) => {
    const configUrl = $container.getAttribute('data-config-url');
    if (!configUrl) return;

    const configuration = await loadConfiguration(configUrl);

    const checkout = await AdyenCheckout({
        paymentMethodsResponse: configuration.paymentMethods,
        clientKey: configuration.clientKey,
        locale: configuration.locale,
        environment: configuration.environment,
        countryCode: configuration.allowedCountryCodes[0],
    });

    try {
        const applePayHandler = new ApplePayHandler(configuration);
        const applePay = new ApplePay(checkout, applePayHandler.getConfig());

        applePay
            .isAvailable()
            .then(() => {
                applePay.mount(SELECTORS.APPLEPAY_MOUNT);
            });
    } catch (e) {
        console.error('Apple Pay is not available');
    }

    try {
        const googlePayHandler = new GooglePayHandler(configuration);
        const googlePay = new GooglePay(checkout, googlePayHandler.getConfig());

        googlePay
            .isAvailable()
            .then(() => {
                googlePay.mount(SELECTORS.GOOGLEPAY_MOUNT);
            });
    } catch (e) {
        console.error('Google Pay is not available');
    }

    try {
        const paypalHandler = new PayPalHandler(configuration);
        const payPal = new PayPal(checkout, paypalHandler.getConfig());

        payPal
            .isAvailable()
            .then(() => {
                payPal.mount(SELECTORS.PAYPAL_MOUNT);
            });
    } catch (e) {
        console.error('PayPal is not available');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const $container = document.getElementById(SELECTORS.CART_CONTAINER);
    if ($container) {
        initExpressCheckout($container);
    }
});
