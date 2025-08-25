import { createFetchOptions } from './utils.js';

export class GooglePayHandler {
    constructor(configuration) {
        this.configuration = configuration;
    }

    handlePaymentDataChanged = async (intermediatePaymentData) => {
        try {
            const { shippingAddress, shippingOptionData } = intermediatePaymentData;
            const paymentDataRequestUpdate = {};

            const response = await fetch(this.configuration.googlePay.path.shippingOptions, createFetchOptions({
                shippingAddress,
                shippingOptionId: shippingOptionData.id
            }));
            const data = await response.json();

            paymentDataRequestUpdate.newShippingOptionParameters = data.shippingOptionParameters;
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
                shippingOptionId: shippingOptionData.id
            }));

            const data = await response.json();
            actions.resolve(data);
        } catch (error) {
            actions.reject(error.message);
        }
    };

    handleSubmit = async (state) => {
        try {
            const response = await fetch(this.configuration.googlePay.path.payments, createFetchOptions(state.data));
            const data = await response.json();
            window.location.replace(data.redirect);
        } catch (error) {
            console.error('Payment submission failed:', error);
        }
    };

    getConfig() {
        return {
            isExpress: true,
            emailRequired: true,
            callbackIntents: ['SHIPPING_ADDRESS', 'SHIPPING_OPTION'],
            shippingAddressRequired: true,
            shippingAddressParameters: {
                allowedCountryCodes: this.configuration.allowedCountryCodes,
                phoneNumberRequired: false,
            },
            shippingOptionRequired: this.configuration.shippingOptionRequired,
            transactionInfo: this.configuration.googlePay.transactionInfo,
            paymentDataCallbacks: {
                onPaymentDataChanged: this.handlePaymentDataChanged
            },
            onAuthorized: this.handleAuthorized,
            onSubmit: this.handleSubmit,
        };
    }
}
