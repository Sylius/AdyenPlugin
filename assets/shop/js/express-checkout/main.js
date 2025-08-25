import { SELECTORS } from './constants.js';
import { loadConfiguration } from './utils.js';
import { GooglePayHandler } from './googlepay.js';
import { PayPalHandler } from './paypal.js';

const initExpressCheckout = async ($container) => {
    const configUrl = $container.getAttribute('data-config-url');
    if (!configUrl) return;

    const { AdyenCheckout, GooglePay, PayPal } = window.AdyenWeb;

    const configuration = await loadConfiguration(configUrl);

    const checkout = await AdyenCheckout({
        paymentMethodsResponse: configuration.paymentMethods,
        clientKey: configuration.clientKey,
        locale: configuration.locale,
        environment: configuration.environment,
        countryCode: configuration.allowedCountryCodes[0],
    });

    const googlePayHandler = new GooglePayHandler(configuration);
    const googlePay = new GooglePay(checkout, googlePayHandler.getConfig());

    googlePay
        .isAvailable()
        .then(() => {
            googlePay.mount(SELECTORS.GOOGLEPAY_MOUNT);
        })
        .catch(e => {
            console.error('Google Pay is not available:', e);
        });

    const paypalHandler = new PayPalHandler(configuration);
    const payPal = new PayPal(checkout, paypalHandler.getConfig());

    payPal
        .isAvailable()
        .then(() => {
            payPal.mount(SELECTORS.PAYPAL_MOUNT);
        })
        .catch(e => {
            console.error('PayPal is not available:', e);
        });
};

document.addEventListener('DOMContentLoaded', () => {
    const $container = document.getElementById(SELECTORS.CONTAINER);
    if ($container) {
        initExpressCheckout($container);
    }
});
