# Referenced Refund - YES

## Why This Is Supported

The plugin has comprehensive referenced refund functionality because it's essential for e-commerce order management and integrates deeply with Sylius's refund system:

### Technical Implementation Details:

1. **Direct API Integration**: `AdyenClient.php:154-161` implements `requestRefund` using Adyen's Modification API with proper reference tracking via `$this->getModification()->refund($params)`

2. **Sylius Refund Plugin Integration**: Deep integration with `sylius/refund-plugin` through `RefundPaymentGenerated` events, allowing seamless refund processing from Sylius admin interface

3. **Reference Management**: `AdyenReference` entity stores PSP references for both original payments and refunds, enabling proper transaction linking and audit trails essential for financial reconciliation

4. **Partial Refund Support**: Full support for partial refunds (refunding less than the original payment amount) which is crucial for returns, damaged goods, and customer service scenarios

### Why Referenced Refunds Work Well Here:

- **Business Critical**: Refunds are mandatory for e-commerce operations - merchants must be able to reverse transactions for returns, cancellations, and disputes
- **Reference Integrity**: Adyen requires original payment references for refunds, which the plugin properly maintains through the `AdyenReference` entity
- **Sylius Integration**: Refunds initiated in Sylius admin automatically trigger Adyen API calls, providing seamless merchant experience
- **State Management**: Refund states are properly tracked in both Sylius and Adyen systems with webhook confirmation

### Integration Architecture:
- **Event-Driven**: Refunds are triggered by Sylius `RefundPaymentGenerated` events
- **Command Pattern**: Refund processing follows the established command/handler pattern
- **State Synchronization**: Webhook notifications confirm refund completion and update order states
- **Admin Interface**: Merchants can process refunds directly from order management screens

### Reference Tracking:
- Original payment PSP references stored in `AdyenReference` entity
- Refund transactions get their own PSP references
- Parent-child relationship maintained for financial reporting
- Full audit trail for accounting and reconciliation

**Status:** ✅ SUPPORTED
**Coverage:** Comprehensive refund functionality with reference tracking, partial refund support, and deep Sylius integration