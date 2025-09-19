import {createFetchOptions, createUrlWithToken} from '../utils.js';

export class ApplePayHandler {
    constructor(configuration) {
        this.configuration = configuration;
    }

    handleShippingContactSelected = async (resolve, reject, event) => {
        try {
            const { shippingContact } = event;

            const response = await fetch(
                this.configuration.applePay.path.addressChange,
                createFetchOptions({
                    shippingContact,
                })
            );
            const data = await response.json();

            if (data.error) {
                if (data.code === 'NO_SHIPPING_OPTION') {
                    return resolve({
                        errors: [{
                            code: 'shippingContactInvalid',
                            contactField: 'countryCode',
                            message: data.message,
                        }],
                        newTotal: data.newTotal,
                        newLineItems: data.newLineItems,
                        newShippingMethods: data.newShippingMethods ?? [],
                    });
                }

                return resolve({
                    errors: [{ code: 'unknown', message: data.message }],
                    newTotal: data.newTotal,
                    newLineItems: data.newLineItems,
                    newShippingMethods: data.newShippingMethods ?? [],
                });
            }

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
                this.configuration.applePay.path.optionsChange,
                createFetchOptions({
                    selectedShippingMethod: event?.shippingMethod?.identifier ?? event?.shippingMethod ?? null,
                })
            );
            const data = await response.json();

            if (data.error) {
                return resolve({
                    errors: [{
                        code: data.code || 'unknown',
                        contactField: data.code === 'NO_SHIPPING_OPTION' ? 'countryCode' : undefined,
                        message: data.message,
                    }],
                    newTotal: data.newTotal,
                    newLineItems: data.newLineItems,
                    newShippingMethods: data.newShippingMethods ?? [],
                });
            }

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

            const response = await fetch(this.configuration.applePay.path.checkout, createFetchOptions({
                shippingContact,
            }));

            const data = await response.json();

            actions.resolve(data);
        } catch (error) {
            actions.reject(error.message);
        }
    };

    handleSubmit = async (state, component, actions) => {
        try {
            const response = await fetch(this.configuration.applePay.path.payments, createFetchOptions(state.data));
            const data = await response.json();

            window.location.replace(data.redirect);
        } catch (error) {
            console.error('Payment submission failed:', error);
        }
    };

    getConfig() {
        return {
            amount: {
                currency: this.configuration.applePay.amount.currency,
                value: this.configuration.applePay.amount.value
            },
            isExpress: true,
            countryCode: this.configuration.allowedCountryCodes[0],
            requiredBillingContactFields: ['postalAddress'],
            requiredShippingContactFields: ['postalAddress', 'name', 'email'],
            supportedCountries: this.configuration.allowedCountryCodes,

            onShippingContactSelected: this.handleShippingContactSelected,
            onShippingMethodSelected: this.handleShippingMethodSelected,
            onAuthorized: this.handleAuthorized,
            onSubmit: this.handleSubmit
        };
    }
}
