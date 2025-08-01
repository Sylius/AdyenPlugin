# Pay-by-Link - NO

## Why This Is NOT Supported

The plugin lacks pay-by-link functionality because it's designed for direct e-commerce checkout flows rather than indirect payment scenarios:

### What Adyen Pay-by-Link Actually Is:

According to Adyen's official documentation, Pay-by-Link is **not a custom implementation** but an **Adyen-hosted service** with these characteristics:

1. **Adyen-Hosted Payment Pages**: Customers are directed to secure, customizable Adyen-hosted payment pages
2. **API-Driven Link Creation**: Uses Adyen's PaymentLinks API to create secure payment links
3. **Automatic Expiration**: Payment links automatically expire after three months
4. **Multi-Payment Method Support**: Supports multiple payment methods on the hosted page
5. **Webhook Integration**: Provides notifications about payment link status and completion

### Excellent Foundation for Integration:

The plugin has outstanding infrastructure that could easily support Pay-by-Link integration:

1. **Adyen Client Infrastructure**:
   - `AdyenClient.php` already integrates with Adyen PHP SDK
   - Could easily add PaymentLinks service integration
   - Existing API key and merchant account configuration ready

2. **Webhook System**:
   - Comprehensive webhook handling via `ProcessNotificationsAction.php`
   - `NotificationToCommandResolver` system for processing different event types
   - HMAC signature validation already implemented
   - Could handle payment link completion notifications

3. **Entity and Admin Framework**:
   - `AdyenReference` and `Log` entities provide patterns to follow
   - Sylius admin grids and controllers already established
   - Could add PaymentLink entity and admin interface following existing patterns

4. **Order Integration**:
   - Deep integration with Sylius order management
   - Payment state machine integration
   - Could link payment links to orders and handle completion

### Implementation Requirements:

1. **PaymentLinks API Integration**: Add PaymentLinks service to `AdyenClient`
2. **PaymentLink Entity**: Store link data, expiration, status, associated order
3. **Admin Interface**: Controllers and forms for creating/managing payment links
4. **Webhook Extension**: Handle payment link completion events
5. **Optional Email Integration**: Send links via Sylius mailer or external services

### Business Context and Value:

- **Adyen-Hosted Service**: Pay-by-Link leverages Adyen's secure, hosted payment pages
- **Supplemental Payment Method**: Designed as additional payment collection method, not primary checkout
- **Key Use Cases**: Invoice payments, offline sales completion, social/chat commerce
- **Automatic Management**: Links expire automatically, reducing merchant maintenance

### Why Integration Makes Sense:

- **Minimal Custom Development**: Leverages Adyen's hosted payment pages and existing infrastructure
- **Strong Foundation**: Plugin's webhook and API infrastructure perfectly suited for integration
- **Market Demand**: Increasingly valuable for B2B merchants and multi-channel sales
- **Low Risk**: Uses proven Adyen service rather than custom implementation

### Simple Integration Approach:

The integration would be straightforward given existing infrastructure:

1. **API Extension**: Add PaymentLinks service to existing `AdyenClient`
2. **Entity Creation**: Create PaymentLink entity following existing patterns
3. **Admin Interface**: Add link management to existing admin framework
4. **Webhook Handling**: Extend existing webhook system for completion events
5. **Order Linking**: Connect links to Sylius orders for payment completion

### Key Insight:

This is **not about building custom payment pages** but about **integrating with Adyen's hosted Pay-by-Link service**. The complexity is much lower because:
- Adyen handles payment page hosting and security
- No need to build custom payment forms or PCI compliance
- Leverages existing webhook and API infrastructure

### Current Status:

**Implementation Feasibility**: Very High - excellent infrastructure foundation exists
**Business Value**: Medium-High - valuable for B2B and multi-channel merchants  
**Technical Complexity**: Low - leverages Adyen-hosted service and existing patterns

### Use Cases That Would Be Covered:
- Manual payment link creation through admin interface
- Payment links for phone/offline sales completion
- Invoice-based payment collection for B2B merchants
- Social media and chat commerce payments
- Email-based payment requests integration
- Order-linked payment completion tracking

### Use Cases Still Not Covered:
- Advanced payment page customization beyond Adyen's options
- Bulk payment link generation (could be added later)
- Complex recurring payment link workflows
- Custom payment link expiration rules (beyond 3 months)

### Technical Implementation:

**Required Changes:**
1. Add PaymentLinks service integration to `AdyenClient`
2. Create PaymentLink entity and admin interface
3. Extend webhook handling for payment link events
4. Add order-to-payment-link association
5. Optional: Email integration for link sending

**Estimated Complexity:** Low - primarily API integration and admin interface

**Status:** ❌ NOT SUPPORTED (but very easy to integrate with existing infrastructure)
**Coverage:** Excellent technical foundation exists for Adyen Pay-by-Link service integration with minimal development effort