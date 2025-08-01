# Pre-authorization - YES

## Why This Is Supported

The plugin has comprehensive pre-authorization support because it's fundamental to secure e-commerce payment processing and maps naturally to standard payment workflows:

### Technical Implementation Details:

1. **State Machine Integration**: `state_machine.yml:13-21` shows the payment state machine properly handles the `authorized` state with transitions to `processing` and `completed`, supporting the full auth-capture workflow.

2. **Command Structure**: The bus system includes `authorize_payment_handler` for processing authorization commands, enabling proper separation of authorization and capture operations.

3. **Payment Flow Design**: The entire plugin architecture is built around the authorize-first model:
   - Initial payment creates authorization
   - Funds are held but not transferred
   - Separate capture step completes the transaction
   - Manual or automatic capture triggers available

4. **API Mapping**: Adyen's `/payments` API naturally supports pre-authorization through the `captureDelayHours` parameter and manual capture workflows.

### Why Pre-authorization Works Excellently Here:

- **Risk Management**: Pre-authorization allows merchants to verify fund availability without immediately charging customers, reducing fraud risk and chargebacks.

- **Business Logic Alignment**: Most e-commerce operations require time between order placement and fulfillment (inventory verification, shipping preparation), making pre-auth ideal.

- **Flexible Capture Timing**: Merchants can capture:
  - Immediately for digital goods
  - Upon shipment for physical products
  - After service delivery for services
  - Manually for high-value or complex orders

- **Standard E-commerce Pattern**: The authorize-capture model is the industry standard for card-not-present transactions.

### Integration Benefits:

- **Inventory Management**: Authorizations can be held while inventory is allocated and prepared for shipment
- **Order Verification**: Complex orders can be reviewed before funds are captured
- **Customer Service**: Issues can be resolved before money changes hands
- **Fraud Prevention**: Suspicious orders can be investigated while funds remain authorized but not captured

### Workflow Examples:

1. **Standard E-commerce Flow**:
   - Customer places order → Authorization created
   - Inventory allocated → Order prepared for shipping
   - Items shipped → Capture triggered
   - Funds transferred → Order completed

2. **High-Value Orders**:
   - Customer places order → Authorization created
   - Manual review process → Order approved
   - Manual capture → Funds transferred

3. **Service-Based Orders**:
   - Service booked → Authorization created
   - Service delivered → Capture triggered
   - Payment completed → Order closed

### State Management:
- **Authorized State**: Funds held, awaiting capture decision
- **Processing State**: Capture in progress
- **Completed State**: Funds successfully captured
- **Failed Handling**: Failed authorizations properly handled with clear error states

**Status:** ✅ SUPPORTED
**Coverage:** Complete pre-authorization implementation with flexible capture timing, proper state management, and full e-commerce workflow integration