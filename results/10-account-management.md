# Account Management - NO

## Why This Is NOT Supported

The plugin lacks Adyen Account Management integration because it's a merchant-level account structure organization feature, not customer account management:

### What Adyen Account Management Actually Is:

According to Adyen's official documentation, Account Management is **not customer account features** but rather **merchant account structure organization** with these characteristics:

1. **Company Account**: Core business entity that holds all merchant accounts and users, issues monthly invoices
2. **Merchant Accounts**: Sub-accounts where payments are processed, configured for payment methods, currencies, and risk management
3. **Account Groups**: Optional layer to manage multiple merchant accounts, allowing cross-account reporting and payment searches
4. **Account Structure Organization**: Businesses can organize accounts by region, brand, or business need

### Why This Is Not Applicable to Plugin:

The plugin focuses on **payment processing integration**, not **merchant account structure management**:

1. **Single Merchant Account Focus**: The plugin is designed to work with a single configured merchant account
2. **Adyen Dashboard Management**: Account structure management is handled through Adyen's merchant dashboard, not plugin code
3. **Configuration vs. Management**: The plugin handles API configuration for a merchant account, not account structure organization

### What The Plugin Actually Provides (Customer Token Management):

The plugin does provide excellent **customer payment token management** (which was confused with "account management"):

1. **Payment Token Storage**: `AdyenToken` entity stores customer payment method tokens linked to Sylius customers
2. **Token Lifecycle Management**: Automatic token creation, retrieval, and deletion via `AdyenClient`
3. **Customer Linking**: Tokens properly associated with Sylius customer accounts
4. **Basic Security**: Tokens stored securely and accessible only to associated customers

### Implementation Considerations:

**Multi-Merchant Account Support** (If Ever Needed):
If the plugin were to support Adyen's account management structure, it would require:

1. **Multiple Merchant Account Configuration**: Ability to configure multiple merchant accounts per installation
2. **Account Selection Logic**: Dynamic merchant account selection based on business rules
3. **Cross-Account Reporting**: Integration with Adyen's account group reporting capabilities
4. **Account Group Management**: Interface for managing account group structures

### Why This Is Not Currently Needed:

- **Single Merchant Focus**: Most Sylius installations serve a single business entity
- **Complexity vs. Value**: Multi-merchant account support adds significant complexity for limited benefit
- **Adyen Dashboard**: Account structure management is better handled through Adyen's native tools
- **Enterprise Feature**: Multi-account structures are primarily needed by large enterprises with complex organizational structures

### Business Context:

- **Target Market**: The plugin serves individual merchants, not enterprise account aggregators
- **Operational Simplicity**: Single merchant account configuration keeps the plugin simple and focused
- **Adyen Tools**: Merchants needing complex account structures can manage them through Adyen's dashboard

### What The Plugin Does Provide:

The plugin provides **excellent customer payment token management** which is what most merchants actually need:
- Token storage and lifecycle management
- Customer-linked payment methods
- Secure token handling
- Integration with Sylius customer accounts

### Use Cases Not Covered:
- Multi-merchant account management within single plugin installation
- Account group reporting and analytics
- Cross-account payment processing
- Automated account structure organization

### Use Cases That Are Covered (Customer Tokens):
- Customer payment method storage
- Returning customer checkout with saved cards
- Token deletion for privacy compliance
- Secure customer-linked payment processing

**Status:** ❌ NOT SUPPORTED (and not typically needed for single-merchant installations)
**Coverage:** Not applicable - Adyen Account Management is for merchant account structure organization, not payment processing features