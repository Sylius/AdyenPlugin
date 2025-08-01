# Authorization Release - PARTIAL

## Why This Has PARTIAL Support

The plugin has basic authorization release (cancellation) capabilities but lacks advanced automated release optimization features:

### What IS Supported:

1. **Manual Authorization Cancellation/Release**:
   - `AdyenClient::requestCancellation()` method (`src/Client/AdyenClient.php:137`) for releasing authorizations
   - Integration with Adyen's Modification service for cancellation API calls
   - `CancelPayment` command (`src/Bus/Command/CancelPayment.php`) for order-based cancellation

2. **Webhook Integration for Cancellation Events**:
   - `PaymentCommandFactory` maps 'cancellation' events to `PaymentCancelledCommand`
   - Webhook processing handles cancellation notifications from Adyen
   - Payment state machine integration for cancelled payments

3. **Command/Handler Architecture**:
   - `AlterPaymentHandler` processes cancellation commands
   - Bus pattern integration for async cancellation processing
   - Proper order and payment lifecycle management

### What IS NOT Supported:

1. **Automated Authorization Release**: No proactive, scheduled release of authorizations before expiry
2. **Authorization Age Tracking**: No monitoring of authorization expiry times or age-based release decisions
3. **Partial Authorization Management**: No handling of partial captures with remaining authorized amounts
4. **Release Optimization**: No business logic for determining optimal release timing
5. **Background Job Processing**: No scheduled jobs for automatic authorization monitoring and release

### Business Context and Current Capabilities:

- **Manual Release Available**: The plugin provides manual authorization release through existing cancellation infrastructure
- **E-commerce Workflow Alignment**: Manual release aligns with typical e-commerce fulfillment timelines
- **Merchant Control**: Merchants can cancel/release authorizations when orders are cancelled or cannot be fulfilled

### Why Current Support Is Practical:

- **Manual Control**: Merchants can release authorizations when needed (order cancellations, stock issues)
- **Webhook Integration**: Automatic processing of cancellation events from Adyen
- **State Machine Integration**: Proper payment state management for cancelled/released authorizations
- **Existing Infrastructure**: Uses established command/handler patterns and API integration

### Enhancement Opportunities:

Building on existing infrastructure, the following advanced features could be added:

1. **Automated Release Scheduling**: Background jobs to monitor authorization age and release before expiry
2. **Authorization Age Tracking**: Database tracking of authorization dates and expiry calculations
3. **Release Optimization Logic**: Business rules for determining when to release vs. capture
4. **Partial Authorization Management**: Handle remaining amounts after partial captures
5. **Release Analytics**: Reporting on authorization release patterns and optimization

### Use Cases Currently Covered:
- Manual authorization release/cancellation
- Order cancellation with payment release
- Webhook-driven cancellation processing
- Payment state management for released authorizations

### Use Cases That Could Be Enhanced:
- Automatic authorization release before expiry
- Proactive cash flow optimization through scheduled releases
- Partial authorization management after partial captures
- Advanced authorization analytics and reporting

### Technical Implementation for Enhancements:

**Required Changes for Automated Release:**
1. Add authorization age tracking to existing entities
2. Create scheduled jobs for authorization monitoring
3. Implement release optimization logic
4. Extend existing cancellation infrastructure
5. Add admin configuration for release policies

**Estimated Complexity:** Medium - builds on existing solid foundation

### Implementation Requirements for Request Reversal:

1. Add `requestReversal()` method to AdyenClient
2. Create reversal command/handler pattern
3. Add CANCEL_OR_REFUND webhook processing
4. Extend ClientPayloadFactory for reversal payloads
5. Update payment state machine for reversal states

### Benefits of Adding Request Reversal:

- Single endpoint for all authorization releases
- No need to check capture status before acting
- Reduces errors from incorrect cancel/refund decisions
- Simplifies payment management logic

**Status:** ⚠️ PARTIAL SUPPORT
**Coverage:** Manual authorization release supported through cancellation. Request Reversal integration would add automatic cancel/refund decision capability.
**Estimated Implementation for Reversal:** 30-40 hours (4-5 days for 1 developer)