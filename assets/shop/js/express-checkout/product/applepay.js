import {createFetchOptions, createUrlWithToken} from '../utils.js';

export class ApplePayHandler {
    constructor(configuration) {
        this.configuration = configuration;
        this.productId = null;
        this.orderToken = null;
    }

    handleClick = async (resolve, reject) => {
        const formData = new FormData(document.getElementsByName('sylius_add_to_cart')[0]);
        const response = await fetch(
            this.configuration.path.addToNewCart.replace('_PRODUCT_ID_', this.productId),
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

    handleShippingContactSelected = async (resolve, reject, event) => {
        try {
            const { shippingContact } = event;

            const response = await fetch(
                createUrlWithToken(this.configuration.applePay.path.addressChange, this.orderToken),
                createFetchOptions({
                    shippingContact,
                })
            );
            const data = await response.json();

            // if (data.error) {
            //     if (data.code === 'NO_SHIPPING_OPTION') {
            //         return reject({
            //             errors: [{
            //                 code: 'shippingContactInvalid',
            //                 contactField: 'countryCode',
            //                 message: data.message
            //             }]
            //         });
            //     } else {
            //         return reject(data.message);
            //     }
            // }

            return resolve({
                newTotal: data.newTotal,
                newLineItems: data.newLineItems,
                newShippingMethods: data.newShippingMethods,
            });
        } catch (error) {
            return reject(error.message);
        }
    };

    handleShippingMethodSelected = async (resolve, reject, event) => {
        try {
            const response = await fetch(
                createUrlWithToken(this.configuration.applePay.path.optionsChange, this.orderToken),
                createFetchOptions({
                    selectedShippingMethod: event.shippingMethod,
                })
            );
            const data = await response.json();

            // if (data.error) {
            //     if (data.code === 'NO_SHIPPING_OPTION') {
            //         return resolve({
            //             errors: [{
            //                 code: 'shippingContactInvalid',
            //                 contactField: 'countryCode',
            //                 message: data.message
            //             }]
            //         });
            //     } else {
            //         return reject({
            //             code: "unknown",
            //             message: data.message
            //         });
            //     }
            // }

            return resolve({
                newTotal: data.newTotal,
                newLineItems: data.newLineItems,
            });
        } catch (error) {
            return reject(error.message);
        }
    };

    handleAuthorized = async (paymentData, actions) => {
        try {
            const { shippingContact } = paymentData.authorizedEvent.payment;

            const response = await fetch(
                createUrlWithToken(this.configuration.applePay.path.checkout, this.orderToken),
                createFetchOptions({
                    shippingContact,
                })
            );

            const data = await response.json();

            actions.resolve(data);
        } catch (error) {
            actions.reject(error.message);
        }
    };

    handleSubmit = async (state, component, actions) => {
        try {
            const response = await fetch(
                createUrlWithToken(this.configuration.applePay.path.payments, this.orderToken),
                createFetchOptions(state.data)
            );
            const data = await response.json();

            window.location.replace(data.redirect);
        } catch (error) {
            console.error('Payment submission failed:', error);
        }
    };

    handleError = (error) => {
        if (this.orderToken !== null) {
            fetch(this.configuration.path.removeCart.replace('_TOKEN_VALUE_', this.orderToken), { method: 'DELETE' });
            this.orderToken = null;
        }
    };

    getConfig(productId) {
        this.productId = productId;

        return {
            amount: {
                currency: this.configuration.applePay.amount.currency,
                value: this.configuration.applePay.amount.value
            },
            isExpress: true,
            countryCode: this.configuration.allowedCountryCodes[0],
            requiredBillingContactFields: ['postalAddress'],
            requiredShippingContactFields: ['postalAddress', 'name', 'email'],

            onShippingContactSelected: this.handleShippingContactSelected,
            onShippingMethodSelected: this.handleShippingMethodSelected,
            onAuthorized: this.handleAuthorized,
            onSubmit: this.handleSubmit,
            onClick: this.handleClick,
            onError: this.handleError,
        };
    }
}
