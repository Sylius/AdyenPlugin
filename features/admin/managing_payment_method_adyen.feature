@managing_payment_method_adyen
Feature: Adding a new payment method
    In order to pay for orders in different ways
    As an Administrator
    I want to add a new payment method to the registry

    Background:
        Given the store operates on a single channel in "United States"
        And I am logged in as an administrator

    @todo @ui
    Scenario: Adding a new Adyen payment method with passing validation
        When I want to create a new Adyen payment method
        And Adyen service will confirm merchantAccount "mer" and apiKey "api" are valid
        And I name it "Adyen" in "English (United States)"
        And I specify its code as "adyen"
        And I specify test configuration with:
            | name            | value         |
            | merchantAccount | mer           |
            | apiKey          | api           |
            | hmacKey         | test_key      |
            | clientKey       | client_key    |
            | authUser        | auth_user     |
            | authPassword    | auth_password |
        And I add it
        Then I should be notified that it has been successfully created
        And I want fields "apiKey, hmacKey" to be filled as placeholder
        And the payment method "Adyen" should appear in the registry
        And I want the payment method "Adyen" configuration to be:
            | name            | value         |
            | merchantAccount | mer           |
            | apiKey          | api           |
            | hmacKey         | test_key      |
            | clientKey       | client_key    |
            | authUser        | auth_user     |
            | authPassword    | auth_password |
