# Partial Payments - NO

## Why This Is NOT Supported

The plugin lacks Adyen Partial Payments integration because it's a specialized Adyen service that requires specific Orders API integration, rather than a custom payment splitting implementation:

### What Partial Payments Is (Adyen Service):

Adyen Partial Payments is a payment orchestration service that allows shoppers to complete a purchase using multiple payment methods for a single order using Adyen's `/orders` API. Examples:
- Gift card with partial balance + credit card for remaining amount
- Multiple gift cards for high-value purchases  
- Corporate credit card + employee personal card for split billing
- Combining different funding sources for complex transactions

### Adyen Partial Payments Implementation Process:

1. **Create an Order**: 
   - Make POST request to `/orders` endpoint with full order amount and currency
   - Receive unique order reference and remaining amount tracking
   - Order serves as container for multiple payment attempts

2. **Make Partial Payments**:
   - Submit payments through `/payments` endpoint with order reference
   - Each payment can be less than total order amount
   - Include order data from initial order creation in payment requests
   - Track remaining balance after each successful payment

3. **Complete or Cancel Order**:
   - Order automatically completes when total payments reach full amount
   - Manual cancellation available for incomplete orders
   - Automatic expiration if order not completed within timeframe

### Technical Architecture Requirements:

1. **Orders API Integration**: Partial payments require Adyen's `/orders` API to create an order container that tracks multiple payment attempts against a single order total. The plugin currently only implements the `/payments` API for single transactions.

2. **Order State Management**: Unlike simple payments, partial payments require tracking:
   - Order creation with total amount and unique order reference
   - Multiple payment attempts with remaining balances
   - Payment method combinations and sequencing
   - Order expiration and completion logic
   - Cancellation scenarios for incomplete orders

3. **Enhanced Payment Flow**: 
   - Create order via `/orders` endpoint before any payment attempts
   - Include order reference and order data in each payment request
   - Track remaining amount after each successful payment
   - Handle payment failures without affecting successful payments
   - Complete order when full amount is collected or handle expiration

4. **Webhook Integration**: Enhanced webhook handling for:
   - Order-level events and status changes
   - Payment completion within order context
   - Order expiration and cancellation events
   - Remaining balance updates

### Current Architecture Limitations:

- **Single Payment Model**: Plugin architecture assumes 1:1 relationship between Sylius orders and Adyen payments
- **No Orders API**: No integration with Adyen's `/orders` endpoint for order container management
- **State Machine Simplicity**: Payment state machine designed for single payment completion
- **Frontend Constraints**: Drop-in integration expects single payment method completion

### Why This Service Integration Wasn't Implemented:

- **Specialized Use Case**: Partial payments are primarily needed for gift card scenarios and corporate split billing
- **Complexity vs. Demand**: Orders API integration requires significant architectural changes for limited use cases
- **Sylius Architecture**: Sylius order management doesn't natively support multi-payment scenarios
- **Resource Prioritization**: Core payment processing took priority over specialized payment orchestration

### Technical Implementation Requirements:

**Orders API Integration:**
1. Add `/orders` endpoint integration to AdyenClient
2. Create order entities to track Adyen order containers
3. Implement order state management and lifecycle
4. Add webhook handling for order-level events

**Enhanced Payment Flow:**
1. Modify payment controllers for multi-step processing
2. Update state machine for order-based payment tracking
3. Enhance frontend for remaining balance display
4. Add order completion and expiration handling

**Database Schema:**
1. New AdyenOrder entity for order container tracking
2. Link multiple payments to single order container
3. Track payment sequencing and remaining amounts

**Estimated Integration Effort:** 120-160 hours (3-4 weeks for 1 dev)

**Key Insight:**
Partial Payments is an **Adyen payment orchestration service** that requires Orders API integration, not a custom payment splitting implementation. The effort involves integrating with Adyen's order management system rather than building custom logic.

**Status:** ❌ NOT SUPPORTED
**Coverage:** No integration with Adyen's Partial Payments service using Orders API for multi-method payment orchestration