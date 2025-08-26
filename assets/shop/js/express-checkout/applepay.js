import { createFetchOptions } from './utils.js';

export class ApplePayHandler {
    constructor(configuration) {
        this.configuration = configuration;
    }

    getConfig() {
        return {
            isExpress: true,
            countryCode: this.configuration.allowedCountryCodes[0],
            requiredBillingContactFields: ['postalAddress'],
            requiredShippingContactFields: ['postalAddress', 'name', 'email'],

            onShippingContactSelected: async (resolve, reject, event) => {
                console.log('Apple Pay shipping contact selected:');

                const response = await fetch(
                    this.configuration.applePay.path.addressChange,
                    createFetchOptions({
                        shippingContact: event.shippingContact,
                    })
                );
                const data = await response.json();

                resolve(data);
            },
            onShippingMethodSelected: async (resolve, reject, event) => {
                console.log('Apple Pay shipping method selected:');

                const response = await fetch(
                    this.configuration.applePay.path.optionsChange,
                    createFetchOptions({
                        selectedShippingMethod: event.shippingMethod,
                    })
                );
                const data = await response.json();

                resolve(data);
            },
            onAuthorized: (data, actions) => {
                console.log('Apple Pay authorized:', data);
                actions.resolve();
            },
            onSubmit: async (state, component, actions) => {
                console.log('Apple Pay submit:');
                console.log(state, component, actions);
            }
        };
    }
}
