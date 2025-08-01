# Auto-Rescue - NO

## Why This Is NOT Supported

The plugin lacks Adyen Auto-Rescue integration because it's a specialized Adyen service that requires specific configuration and webhook handling, rather than a custom retry implementation:

### What Adyen Auto-Rescue Actually Is:

According to Adyen's official documentation, Auto-Rescue is **not a custom implementation** but an **Adyen-provided service** with these characteristics:

1. **Adyen Service**: Automatically retries "shopper-not-present" transactions that were initially refused
2. **Smart Logic**: Uses Adyen's proprietary algorithms to determine retry potential
3. **Supported Transaction Types**: Cards and SEPA direct debit transactions
4. **Automated Process**: Performs multiple retry attempts within a defined "rescue window"
5. **Webhook Integration**: Provides notifications about retry attempts and results

### Excellent Foundation for Integration:

The plugin has outstanding infrastructure that could easily support Auto-Rescue integration:

1. **Webhook Infrastructure**:
   - Comprehensive webhook handling via `ProcessNotificationsAction.php`
   - `NotificationToCommandResolver` system for processing different event types
   - HMAC signature validation already implemented
   - Could easily handle Auto-Rescue webhook notifications

2. **Command/Handler Architecture**:
   - Sophisticated bus pattern with existing payment event handlers
   - `PaymentCommandFactory` already maps different Adyen events to commands
   - Could add Auto-Rescue event handlers following existing patterns

3. **Payment State Management**:
   - Payment state machine integration via `PaymentTransitions`
   - `AdyenReference` entity for tracking payment attempts
   - Comprehensive logging system for audit trails

### Implementation Requirements:

1. **Adyen Service Configuration**: Enable Auto-Rescue in Adyen merchant dashboard
2. **Webhook Event Handling**: Extend existing webhook system to handle Auto-Rescue notifications
3. **Payment State Updates**: Update payment status based on retry attempt results
4. **Optional Customer Communication**: Notify customers about retry attempts if desired

### Business Context and Value:

- **Adyen Service**: Auto-Rescue is an Adyen-provided service, not a custom feature to build
- **Subscription and Recurring Focus**: Primarily valuable for merchants with recurring payments
- **Reduced Failed Payments**: Automatic retry of temporarily failed transactions
- **Zero Custom Development**: The retry logic is handled entirely by Adyen's systems

### Why Integration Makes Sense:

- **Minimal Development Effort**: Only requires webhook integration, not building retry logic
- **Leverage Existing Infrastructure**: Plugin's webhook system can easily handle Auto-Rescue events
- **Adyen's Expertise**: Benefits from Adyen's proprietary retry algorithms and timing
- **Proven Service**: Uses Adyen's tested and optimized retry strategies

### Simple Integration Approach:

The integration would be straightforward:

1. **Service Enablement**: Merchant enables Auto-Rescue in their Adyen dashboard
2. **Webhook Extension**: Add Auto-Rescue event handlers to existing webhook system
3. **Status Updates**: Update payment/order status based on retry results
4. **Optional Notifications**: Integrate with existing notification system

### Key Insight:

This is **not about building custom retry logic** but about **integrating with Adyen's existing Auto-Rescue service**. The complexity is much lower because:
- Adyen handles all retry logic and timing
- No need to build failure analysis or scheduling systems
- Only requires extending existing webhook handling

### Current Alternative Approaches Available:

- **Clear Error Messages**: Payment failures provide specific error information to help customers resolve issues
- **Multiple Payment Methods**: Customers can immediately try different payment methods if one fails
- **3DS Fallback**: Authentication challenges help resolve some security-related failures
- **Webhook Notifications**: Merchants are notified of failed payments for follow-up if needed

### Use Cases That Would Be Covered:
- Automatic retry of refused transactions via Adyen's service
- Intelligent retry timing using Adyen's proprietary algorithms
- Retry attempt tracking through webhook notifications
- Integration with existing payment state management
- Support for Cards and SEPA direct debit transactions

### Use Cases Still Not Covered:
- Custom retry logic beyond Adyen's service
- Multi-PSP failover strategies
- Manual retry configuration and rules
- Advanced retry analytics beyond Adyen's notifications

### Technical Implementation:

**Required Changes:**
1. Extend `PaymentCommandFactory` MAPPING to include Auto-Rescue events
2. Add Auto-Rescue event handlers in `NotificationToCommandResolver`
3. Create Auto-Rescue specific command classes (following existing patterns)
4. Optional: Customer notification integration

**Estimated Complexity:** Very Low - primarily webhook integration

**Status:** ❌ NOT SUPPORTED (but very easy to integrate with existing infrastructure)
**Coverage:** Excellent technical foundation exists for Adyen Auto-Rescue service integration with minimal development effort