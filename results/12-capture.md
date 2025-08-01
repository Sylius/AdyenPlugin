# Capture - YES

## Why This Is Supported

The plugin has comprehensive capture functionality because it's a fundamental requirement for pre-authorized payment processing:

### Technical Implementation Details:

1. **Direct API Integration**: `AdyenClient.php:129-135` implements the `requestCapture` method using Adyen's Modification API, calling `$this->getModification()->capture($params)` with properly formatted payment data

2. **Command Pattern Implementation**: The capture process follows the plugin's established command/handler pattern with `RequestCapture` command and corresponding handler, ensuring consistent error handling and logging

3. **State Machine Integration**: Capture operations trigger Sylius state machine transitions, properly moving payments from `authorized` to `completed` states with full audit trails

4. **Payload Factory**: `ClientPayloadFactory` creates properly formatted capture requests with required fields like `originalReference`, `modificationAmount`, and merchant account details

### Why Capture Works Well Here:

- **Standard E-commerce Need**: Most online merchants use auth-capture workflows (authorize during checkout, capture upon shipping) making this essential functionality
- **Simple API Mapping**: Adyen's capture API maps directly to Sylius's payment concepts - one capture per payment with straightforward parameters
- **State Management**: Captures fit naturally into Sylius's payment state machine without requiring complex workflow changes
- **Business Logic Integration**: Captures can be triggered automatically (on order fulfillment) or manually (via admin interface) through the same technical implementation

### Integration Points:
- **Sylius Refund Plugin**: Captures are tracked for proper refund reference handling
- **Order Management**: Captures can be triggered from order state changes
- **Admin Interface**: Manual capture capabilities through payment management screens
- **Webhook Handling**: Capture confirmation via Adyen notifications updates payment states

### Error Handling:
- Failed captures maintain payment in authorized state
- Proper exception handling with merchant-friendly error messages
- Logging integration for debugging capture issues

**Status:** ✅ SUPPORTED
**Coverage:** Full capture implementation with command pattern, state machine integration, and comprehensive error handling