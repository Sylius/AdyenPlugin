import { createFetchOptions, showErrorMessage, createUrlWithToken } from '../utils.js';
import {SELECTORS} from "../constants";

export class PayPalHandler {
    constructor(configuration) {
        this.configuration = configuration;
        this.paypalPspReference = null;
        this.productId = null;
    }

    handleClick = async (data, actions) => {
        const formData = new FormData(document.getElementsByName('sylius_add_to_cart')[0]);
        const response = await fetch(
            this.configuration.path.addToNewCart.replace('_PRODUCT_ID_', this.productId),
            createFetchOptions({
                formData,
            })
        );
        const result = await response.json();
        if (data.error) {
            return actions.reject(result.message);
        }

        this.orderToken = result.orderToken;
        return actions.resolve();
    };

    handleSubmit = async (state, component) => {
        try {
            const response = await fetch(
                createUrlWithToken(this.configuration.paypal.path.initialize, this.orderToken),
                createFetchOptions({
                    ...state.data,
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
            showErrorMessage(error.message, SELECTORS.PRODUCT_CONTAINER);
        }
    };

    handleShippingAddressChange = async (data, actions, component) => {
        try {
            if (!this.paypalPspReference) {
                return actions.reject();
            }

            const response = await fetch(
                createUrlWithToken(this.configuration.paypal.path.addressChange, this.orderToken),
                createFetchOptions({
                    paymentData: component.paymentData,
                    pspReference: this.paypalPspReference,
                    shippingAddress: data.shippingAddress,
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
                createUrlWithToken(this.configuration.paypal.path.optionsChange, this.orderToken),
                createFetchOptions({
                    paymentData: component.paymentData,
                    pspReference: this.paypalPspReference,
                    selectedDeliveryMethod: data.selectedShippingOption,
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
                createUrlWithToken(this.configuration.paypal.path.checkout, this.orderToken),
                createFetchOptions({
                    deliveryAddress: data.deliveryAddress,
                    payer : data.authorizedEvent.payer,
                })
            );

            const result = await response.json();

            if (result.error) {
                return actions.reject(result.message);
            }

            return actions.resolve(result);
        } catch (error) {
            return actions.reject(error);
        }
    };

    handleAdditionalDetails = async (state, component) => {
        try {
            const response = await fetch(
                createUrlWithToken(this.configuration.paypal.path.paymentDetails, this.orderToken),
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
            showErrorMessage(error.message, SELECTORS.PRODUCT_CONTAINER);
        }
    };

    handleError = (error) => {
        this.paypalPspReference = null;
        if (this.orderToken !== null) {
            fetch(this.configuration.path.removeCart.replace('_TOKEN_VALUE_', this.orderToken), { method: 'DELETE' });
            this.orderToken = null;
        }
        if (error.message) {
            showErrorMessage(error.message, SELECTORS.PRODUCT_CONTAINER);
        }
    };

    getConfig(productId) {
        this.productId = productId;
        return {
            amount: {
                currency: this.configuration.paypal.amount.currency,
            },
            isExpress: true,
            blockPayPalCreditButton: true,
            blockPayPalPayLaterButton: true,
            blockPayPalVenmoButton: true,

            onClick: this.handleClick,
            onSubmit: this.handleSubmit,
            onShippingAddressChange: this.handleShippingAddressChange,
            onShippingOptionsChange: this.handleShippingOptionsChange,
            onAuthorized: this.handleAuthorized,
            onAdditionalDetails: this.handleAdditionalDetails,
            onError: this.handleError
        };
    }
}
