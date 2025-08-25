import { createFetchOptions, showErrorMessage } from './utils.js';

export class PayPalHandler {
    constructor(configuration) {
        this.configuration = configuration;
        this.paypalPspReference = null;
    }

    handleSubmit = async (state, component) => {
        try {
            const response = await fetch(
                this.configuration.paypal.path.initialize,
                createFetchOptions({
                    ...state.data
                })
            );
            const result = await response.json();

            if (result.action) {
                this.paypalPspReference = result.pspReference;
                component.handleAction(result.action);
            } else {
                throw new Error('No PayPal action received from server');
            }
        } catch (error) {
            showErrorMessage(error.message);
        }
    };

    handleShippingAddressChange = async (data, actions, component) => {
        try {
            if (!this.paypalPspReference) {
                return actions.reject();
            }

            const response = await fetch(
                this.configuration.paypal.path.addressChange,
                createFetchOptions({
                    paymentData: component.paymentData,
                    pspReference: this.paypalPspReference,
                    shippingAddress: data.shippingAddress
                })
            );
            const result = await response.json();

            if (result.error) {
                if (result.code === 'NO_SHIPPING_OPTION') {
                    return actions.reject(data.errors.ADDRESS_ERROR);
                } else {
                    return actions.reject(result.message);
                }
            }

            component.updatePaymentData(result.paymentData);
        } catch (error) {
            return actions.reject(error);
        }
    };

    handleShippingOptionsChange = async (data, actions, component) => {
        try {
            if (!this.paypalPspReference) {
                return actions.reject();
            }

            const response = await fetch(
                this.configuration.paypal.path.optionsChange,
                createFetchOptions({
                    paymentData: component.paymentData,
                    pspReference: this.paypalPspReference,
                    selectedDeliveryMethod: data.selectedShippingOption
                })
            );
            const result = await response.json();

            component.updatePaymentData(result.paymentData);
        } catch (error) {
            return actions.reject(error);
        }
    };

    handleAuthorized = async (data, actions) => {
        try {
            if (!this.paypalPspReference) {
                return actions.reject();
            }

            const response = await fetch(
                this.configuration.paypal.path.checkout,
                createFetchOptions({
                    billingAddress: data.billingAddress,
                    deliveryAddress: data.deliveryAddress,
                    payer : data.authorizedEvent.payer,
                })
            );

            const result = await response.json();
            return actions.resolve(result);
        } catch (error) {
            return actions.reject(error);
        }
    };

    handleAdditionalDetails = async (state, component) => {
        try {
            const response = await fetch(
                this.configuration.paypal.path.paymentDetails,
                createFetchOptions({
                    details: state.data.details,
                    paymentData: component.paymentData,
                }),
            );
            const result = await response.json();

            if (result.error) {
                throw new Error(result.message);
            }

            window.location.replace(result.redirect);
        } catch (error) {
            showErrorMessage(error.message);
        }
    };

    handleError = (error) => {
        this.paypalPspReference = null;
        if (error.message) {
            showErrorMessage(error.message);
        }
    };

    getConfig() {
        return {
            amount: {
                currency: this.configuration.paypal.amount.currency,
                value: this.configuration.paypal. amount.value
            },
            isExpress: true,
            blockPayPalCreditButton: true,
            blockPayPalPayLaterButton: true,
            blockPayPalVenmoButton: true,

            onSubmit: this.handleSubmit,
            onShippingAddressChange: this.handleShippingAddressChange,
            onShippingOptionsChange: this.handleShippingOptionsChange,
            onAuthorized: this.handleAuthorized,
            onAdditionalDetails: this.handleAdditionalDetails,
            onError: this.handleError
        };
    }
}
