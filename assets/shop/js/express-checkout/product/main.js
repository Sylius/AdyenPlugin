import { SELECTORS } from '../constants.js';
import { loadConfiguration } from '../utils.js';
import { ApplePayHandler } from './applepay.js';
import { GooglePayHandler } from './googlepay.js';
import { PayPalHandler } from "./paypal.js";

const isPaymentMethodAvailable = (paymentMethodData, type) => {
    if (!paymentMethodData || !paymentMethodData.paymentMethods) return false;

    return paymentMethodData.paymentMethods.some(method => method.type === type);
};

export const getSelectedVariant = () => {
    const $container = document.getElementById(SELECTORS.PRODUCT_CONTAINER);
    const variantsData = JSON.parse($container.getAttribute('data-variants') || '[]');

    // Try to get variant from select field (variant selection by choice)
    const variantSelect = document.querySelector('[name="sylius_add_to_cart[cartItem][variant]"]');
    if (variantSelect) {
        const selectedVariantId = parseInt(variantSelect.value, 10);
        return variantsData.find(v => v.id === selectedVariantId) || variantsData[0];
    }

    // Try to get variant from option selects (variant selection by match)
    const form = document.getElementsByName('sylius_add_to_cart')[0];
    if (!form) {
        return variantsData[0];
    }

    const optionSelects = form.querySelectorAll('[name*="sylius_add_to_cart[cartItem][variant]"]');
    if (optionSelects.length === 0) {
        return variantsData[0];
    }

    // For products with options, FormData will contain the variant ID in cartItem[variant] after form submission
    // Since we need to determine it before submission, we return the first variant as fallback
    // The actual variant will be determined by the backend based on selected options
    return variantsData[0];
};

const initExpressCheckout = async ($container) => {
    const configUrl = $container.getAttribute('data-config-url');
    const productId = $container.getAttribute('data-product-id');
    if (!configUrl) return;

    const { AdyenCheckout, ApplePay, GooglePay, PayPal } = window.AdyenWeb;

    const configuration = await loadConfiguration(configUrl);

    const checkout = await AdyenCheckout({
        paymentMethodsResponse: configuration.paymentMethods,
        clientKey: configuration.clientKey,
        locale: configuration.locale,
        environment: configuration.environment,
        countryCode: configuration.allowedCountryCodes[0],
    });

    if (isPaymentMethodAvailable(configuration.paymentMethods, 'applepay')) {
        try {
            const applePayHandler = new ApplePayHandler(configuration);
            const applePay = new ApplePay(checkout, applePayHandler.getConfig(productId));

            applePay
                .isAvailable()
                .then(() => {
                    applePay.mount(SELECTORS.APPLEPAY_MOUNT);
                });
        } catch (e) {
            console.error('Apple Pay is not available');
        }
    }

    if (isPaymentMethodAvailable(configuration.paymentMethods, 'googlepay')) {
        try {
            const googlePayHandler = new GooglePayHandler(configuration);
            const googlePay = new GooglePay(checkout, googlePayHandler.getConfig(productId));

            googlePay
                .isAvailable()
                .then(() => {
                    googlePay.mount(SELECTORS.GOOGLEPAY_MOUNT);
                });
        } catch (e) {
            console.error('Google Pay is not available');
        }
    }

    if (isPaymentMethodAvailable(configuration.paymentMethods, 'paypal')) {
        try {
            const paypalHandler = new PayPalHandler(configuration);
            const payPal = new PayPal(checkout, paypalHandler.getConfig(productId));

            payPal
                .isAvailable()
                .then(() => {
                    payPal.mount(SELECTORS.PAYPAL_MOUNT);
                });
        } catch (e) {
            console.error('PayPal is not available');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const $container = document.getElementById(SELECTORS.PRODUCT_CONTAINER);
    if ($container) {
        initExpressCheckout($container);
    }
});
