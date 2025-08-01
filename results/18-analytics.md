# Analytics - PARTIAL

## Why This IS Partially Supported

The plugin has built-in analytics capability through the Adyen Drop-in component, but lacks explicit analytics configuration. While telemetry collection occurs automatically, the implementation doesn't expose or configure Adyen's full analytics features:

### What IS Supported (Default Drop-in Analytics):

1. **Basic Drop-in Telemetry** (Automatic):
   - `dropin.js:112` - `AdyenCheckout()` instantiation includes default telemetry collection
   - Built-in data collection for Adyen's analytics service (enabled by default in v5.16.0+)
   - Basic platform and integration type identification

### What Is NOT Fully Implemented:

1. **Missing Analytics Configuration**:
   - No explicit `analytics` parameter in `AdyenCheckout()` configuration
   - Cannot disable/enable analytics data collection per Adyen documentation
   - No configuration for fraud detection data collection (optional feature)

2. **Limited Analytics Control**:
   - No custom analytics configuration options exposed
   - Cannot configure telemetry data collection preferences
   - Missing advanced analytics features that require explicit setup

### What Works by Default:

1. **Automatic Telemetry Collection**:
   - Drop-in component automatically collects basic telemetry (v5.16.0+)
   - Platform, integration type, and version data sent automatically
   - Device and browser information collected by default

2. **Payment Event Tracking**:
   - Payment submissions automatically tracked
   - 3D Secure interactions logged
   - Payment method selection recorded

### Technical Implementation Assessment:

**Current Implementation:**
```javascript
// dropin.js:112-149
return new AdyenCheckout({
    paymentMethodsResponse: configuration.paymentMethods,
    paymentMethodsConfiguration: { /* ... */ },
    clientKey: configuration.clientKey,
    locale: configuration.locale,
    environment: configuration.environment,
    // Missing: analytics configuration options
    onSubmit: (state, dropin) => { /* ... */ },
    onAdditionalDetails: (state, dropin) => { /* ... */ },
    onError: (error, component) => { /* ... */ }
});
```

**What's Missing for Full Analytics Support:**
- No `analytics` parameter configuration
- No fraud detection data collection setup
- No custom telemetry configuration options

**What Works Automatically:**
- Default telemetry collection (enabled by Drop-in v5.16.0+)
- Basic platform and integration detection
- Automatic checkout attempt tracking

### Improvement Opportunities:

1. **Add Analytics Configuration:**
```javascript
// Enhanced implementation would include:
return new AdyenCheckout({
    // ... existing config
    analytics: {
        enabled: true, // or false to disable
        fraudDetection: true // for additional data collection
    }
});
```

2. **Configurable Telemetry:**
- Add merchant control over analytics data collection
- Support for disabling telemetry if required by privacy policies
- Custom analytics configuration per environment

### Current Business Value:

1. **Automatic Basic Analytics**: Default telemetry provides basic insights
2. **Payment Tracking**: Core payment events tracked automatically
3. **Integration Monitoring**: Basic platform detection and error tracking

### What Merchants Get Today:

**Available Through Adyen Customer Area:**
- Basic payment performance data (automatic)
- Drop-in integration identification
- Platform and environment detection
- Core transaction analytics

**Missing Advanced Features:**
- Custom analytics configuration
- Fraud detection data collection (requires explicit setup)
- Advanced telemetry customization
- Analytics opt-out functionality

### Plugin's Additional Logging:

The plugin provides complementary logging infrastructure:
- **API Interaction Logs**: All Adyen API calls logged locally
- **Admin Interface**: Sylius admin grids for transaction analysis
- **Event Tracking**: Payment state changes and webhook processing
- **Error Documentation**: Detailed error tracking with context

### Assessment Summary:

**What Works:** Basic analytics through Drop-in's automatic telemetry collection
**What's Missing:** Explicit analytics configuration and advanced features
**Impact:** Merchants get basic insights but lack control over analytics features

**Status:** ⚠️ PARTIAL SUPPORT
**Coverage:** Basic automatic analytics via Drop-in component, missing explicit configuration and advanced features