# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Reference Documents
- **[ADYEN.md](ADYEN.md)** - Upstream Adyen payment processor functionalities and documentation links
- **[RESULTS.md](RESULTS.md)** - Current analysis of Adyen functionality coverage by this plugin (50% coverage - excellent for core payment processing, limited advanced features)

## Overview
This is the BitBag Sylius Adyen Plugin - a comprehensive payment plugin for Sylius e-commerce platform that integrates with Adyen payment processing. The plugin supports multiple payment methods including cards, wallets (Apple Pay, Google Pay, etc.), and alternative payment methods.

**Main Plugin Entry Point:**
- `BitBagSyliusAdyenPlugin.php:22` - Main bundle class extending Symfony Bundle

**Key Dependencies:**
- Sylius 1.14.0 (e-commerce platform)
- Adyen PHP API Library 11.0 (official Adyen SDK)
- Sylius Refund Plugin 1.0+ (for refund handling)
- PHP 8.0+ with Symfony 6.4+

## Core Functionalities

### 1. Payment Processing
- **Submit Payments**: `PaymentsAction.php:92` handles payment submission via Adyen drop-in
- **Payment Details**: `AdyenClient.php:96` processes additional payment details for 3DS
- **Payment Methods**: `AdyenClient.php:83` retrieves available payment methods per order
- **Payment Finalization**: `PaymentFinalizationHandler.php:56` handles state transitions

### 2. Payment Methods Supported
- Credit/Debit Cards with 3D Secure
- Digital Wallets: Apple Pay, Google Pay, WeChat Pay, AliPay
- Regional Methods: Klarna, iDeal, SEPA, Sofort, Bancontact, Bizum, Blik, Twint, Dotpay
- PayPal integration

### 3. Order Management
- **Capture**: `AdyenClient.php:129` - capture authorized payments
- **Cancel**: `AdyenClient.php:137` - cancel pending payments  
- **Refunds**: `AdyenClient.php:154` - process partial/full refunds via Sylius Refund Plugin

### 4. Security & Authentication
- **HMAC Signature Validation**: `SignatureValidator.php` validates webhook authenticity
- **Encrypted Credentials**: API keys, HMAC keys stored encrypted
- **Environment Separation**: Live/Test environment configuration

### 5. Webhook Processing
- **Notification Handler**: `ProcessNotificationsAction.php` processes Adyen webhooks
- **Event Processing**: `NotificationResolver.php:75` validates and processes notifications
- **State Machine Integration**: Automatic payment state updates based on notifications

### 6. Token Management
- **Stored Payment Methods**: `AdyenTokenInterface.php:18` for recurring payments
- **Token Storage**: Customer-linked payment method tokens
- **Token Removal**: `AdyenClient.php:145` removes stored payment methods

### 7. Data Entities
- **AdyenReference**: `AdyenReferenceInterface.php:18` - stores PSP references for payments/refunds
- **AdyenToken**: `AdyenTokenInterface.php:18` - manages customer payment tokens
- **Logging**: Comprehensive API interaction logging

### 8. Frontend Integration
- **Drop-in UI**: JavaScript integration with Adyen's drop-in component
- **Checkout Flow**: Seamless integration with Sylius checkout process
- **Admin Interface**: Payment method configuration in Sylius admin

### 9. Command/Query Bus Pattern
- **Commands**: Payment lifecycle commands (authorize, capture, cancel, refund)
- **Handlers**: Dedicated handlers for each payment operation
- **Event Sourcing**: Payment status changes tracked via events

### 10. Configuration Management
- **Gateway Config**: Merchant account, API credentials, environment settings
- **Payment Method Config**: Per-method configuration in Sylius admin
- **Validation**: Real-time credential validation

## Development Commands

### Testing
```bash
# Run PHPUnit tests
bin/phpunit

# Run Behat tests
bin/behat

# Run PHPSpec tests  
bin/phpspec run
```

### Code Quality
```bash
# Run ECS (Easy Coding Standard) for code style checking
vendor/bin/ecs check

# Fix code style issues
vendor/bin/ecs check --fix

# Run PHPStan for static analysis
vendor/bin/phpstan analyse
```

### Frontend Development
```bash
# Install frontend dependencies (from tests/Application/)
cd tests/Application
yarn install

# Build assets for development
yarn encore dev

# Build assets for production
yarn encore prod

# Watch for changes during development
yarn encore dev --watch

# Install Sylius assets
bin/console assets:install
```

### Database Setup
```bash
# Create test database
bin/console doctrine:database:create -e test

# Run migrations
bin/console doctrine:migrations:migrate

# Create new migration after schema changes
bin/console doctrine:migrations:diff
```

## Architecture Overview

### Core Components
- **Bus System**: Uses Symfony Messenger for payment processing commands and handlers (`src/Bus/`)
- **Payment Processing**: Command-based architecture with separate handlers for authorization, capture, refund operations
- **Client Integration**: Adyen API client wrapper with signature validation (`src/Client/`)
- **Entity Layer**: Custom entities for Adyen references, tokens, and logs (`src/Entity/`)
- **Normalizers**: Transform Sylius data structures to Adyen API format (`src/Normalizer/`)

### Key Directories
- `src/Bus/` - Command/Query/Handler pattern for payment operations
- `src/Client/` - Adyen API integration layer
- `src/Controller/Shop/` - Frontend payment controllers
- `src/Entity/` - Doctrine entities for plugin data
- `src/Processor/` - Payment response processing logic
- `src/Resources/` - Configuration, routing, templates, assets

### Payment Flow
1. Payment initiated via Sylius checkout
2. Commands dispatched through `sylius.command_bus`
3. Handlers interact with Adyen API through client layer
4. Responses processed by specialized processors
5. Payment state updates managed through Sylius state machine

### Configuration
The plugin is configured via:
- `config/packages/_sylius.yaml` - Main plugin import and refund gateway setup
- Monolog integration for logging API communications
- State machine configuration for payment transitions

### Frontend Integration
- Uses Adyen Drop-in component for payment forms
- Custom JavaScript in `src/Resources/public/js/`
- Twig templates in `src/Resources/views/`
- Webpack Encore for asset building

### Testing Structure
- **Unit Tests**: `tests/Unit/` - Component-level testing
- **Behat Tests**: `tests/Behat/` - BDD integration tests
- **Application**: `tests/Application/` - Test Sylius application

## Integration Points
- **Sylius Order Management**: Deep integration with order processing
- **State Machine**: Payment/refund state transitions
- **Admin Interface**: Configuration panels and logging grids
- **Frontend**: Shop checkout integration with Adyen drop-in
- **Webhook Endpoints**: Real-time payment status updates

## Key File Locations

### Core Classes
- `src/BitBagSyliusAdyenPlugin.php` - Main plugin bundle
- `src/Client/AdyenClient.php` - Main API client wrapper
- `src/Client/AdyenClientInterface.php` - Client interface

### Controllers
- `src/Controller/Shop/PaymentsAction.php` - Payment submission endpoint
- `src/Controller/Shop/PaymentDetailsAction.php` - Payment details processing
- `src/Controller/Shop/ProcessNotificationsAction.php` - Webhook handler

### Entities & Interfaces
- `src/Entity/AdyenReference.php` - PSP reference storage
- `src/Entity/AdyenToken.php` - Customer payment tokens
- `src/Entity/Log.php` - API interaction logging

### Bus Pattern Implementation
- `src/Bus/Command/` - Payment lifecycle commands
- `src/Bus/Handler/` - Command handlers
- `src/Bus/Query/` - Query objects

### Configuration
- `src/Resources/config/services.xml` - Service definitions
- `src/Resources/config/routing.yaml` - Route definitions
- `src/DependencyInjection/Configuration.php` - Plugin configuration

### Frontend Assets
- `src/Resources/public/js/` - JavaScript components
- `src/Resources/public/css/` - Styling
- `src/Resources/views/` - Twig templates

The plugin follows Sylius plugin development best practices with dependency injection, event subscribers, and proper separation of concerns.