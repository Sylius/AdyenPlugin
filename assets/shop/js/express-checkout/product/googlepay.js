import { createFetchOptions } from '../utils.js';
import {SELECTORS} from "../constants";

export class GooglePayHandler {
    constructor(configuration) {
        this.configuration = configuration;
        this.orderToken = null;

        const $container = document.getElementById(SELECTORS.PRODUCT_CONTAINER);
        this.shippingRequired = $container.getAttribute('data-shipping-required') === '1';
        this.productId = $container.getAttribute('data-product-id');
    }

    handleClick = async (resolve, reject) => {
        const formData = new FormData(document.getElementsByName('sylius_add_to_cart')[0]);
        const response = await fetch(
            this.configuration.googlePay.path.addToNewCart.replace('_PRODUCT_ID_', this.productId),
            createFetchOptions({
                formData,
            })
        );
        const data = await response.json();
        if (data.error) {
            return reject(data.message);
        }

        this.orderToken = data.orderToken;
        return resolve();
    };

    handlePaymentDataChanged = async (intermediatePaymentData) => {
        try {
            const { shippingAddress, shippingOptionData } = intermediatePaymentData;
            const paymentDataRequestUpdate = {};

            const response = await fetch(this.configuration.googlePay.path.shippingOptions, createFetchOptions({
                shippingAddress,
                shippingOptionId: shippingOptionData?.id,
                tokenValue: this.orderToken,
            }));
            const data = await response.json();

            if (data.error) {
                throw new Error(data.message);
            }

            if (shippingOptionData) {
                paymentDataRequestUpdate.newShippingOptionParameters = data.shippingOptionParameters;
            }
            paymentDataRequestUpdate.newTransactionInfo = data.transactionInfo;

            return paymentDataRequestUpdate;
        } catch (error) {
            throw error;
        }
    };

    handleAuthorized = async (paymentData, actions) => {
        try {
            const { email, shippingAddress, shippingOptionData } = paymentData.authorizedEvent;

            const response = await fetch(this.configuration.googlePay.path.checkout, createFetchOptions({
                email,
                shippingAddress,
                shippingOptionId: shippingOptionData?.id,
                tokenValue: this.orderToken,
            }));

            const data = await response.json();

            if (data.error) {
                throw new Error(data.message);
            }

            actions.resolve(data);
        } catch (error) {
            actions.reject(error.message);
        }
    };

    handleSubmit = async (state) => {
        try {
            const response = await fetch(
                this.configuration.googlePay.path.payments,
                createFetchOptions({
                    ...state.data,
                    tokenValue: this.orderToken,
                })
            );
            const data = await response.json();

            window.location.replace(data.redirect);
        } catch (error) {
            console.error('Payment submission failed:', error);
        }
    };

    handleError = (error) => {
        if (this.orderToken !== null) {
            fetch(this.configuration.googlePay.path.removeCart.replace('_TOKEN_VALUE_', this.orderToken), { method: 'DELETE' });
            this.orderToken = null;
        }
    };

    getConfig() {
        let callbackIntents = ['SHIPPING_ADDRESS'];
        if (this.shippingRequired) {
            callbackIntents.push('SHIPPING_OPTION');
        }

        return {
            isExpress: true,
            expressPage: 'pdp',
            emailRequired: true,
            callbackIntents: callbackIntents,
            shippingAddressRequired: true,
            shippingAddressParameters: {
                allowedCountryCodes: this.configuration.allowedCountryCodes,
                phoneNumberRequired: false,
            },
            shippingOptionRequired: this.shippingRequired,
            transactionInfo: this.configuration.googlePay.transactionInfo,
            paymentDataCallbacks: {
                onPaymentDataChanged: this.handlePaymentDataChanged,
            },
            onClick: this.handleClick,
            onSubmit: this.handleSubmit,
            onAuthorized: this.handleAuthorized,
            onError: this.handleError,
        };
    }
}
