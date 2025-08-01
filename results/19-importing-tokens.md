# Importing Tokens - NO

## Why This Is NOT Supported

The plugin lacks Adyen's Token Import service integration because it's a specialized migration service that requires direct coordination with Adyen support rather than API integration:

### What Token Import Is (Adyen Migration Service):

Adyen's Token Import is a **manual migration service** where merchants provide encrypted CSV files to Adyen for importing existing recurring payment contracts from other payment processors. This involves:
- Creating encrypted CSV files with payment method details
- Direct submission to Adyen support teams
- Adyen processing and validating the token data
- Receiving processed results with new Adyen token references

### Service Requirements and Process:

1. **CSV File Preparation**:
   - UTF-8 format with specific column structure
   - Maximum 1,000,000 lines and 1 GB file size
   - Encrypted using Adyen-provided PGP public key
   - Contains shopper references, payment method details, contract types

2. **Supported Payment Methods for Import**:
   - Credit/Debit Cards (with card details or tokens)
   - SEPA Direct Debit
   - ACH (US bank accounts)
   - PayPal account references
   - Klarna customer data

3. **Manual Processing by Adyen**:
   - Direct submission to Adyen support team
   - Adyen validates and processes token data
   - Returns CSV with migration results and new token references
   - Includes import status, payment method variants, and recurring detail references

### Why This Is NOT a Plugin Feature:

1. **Manual Service Process**: Token import is handled directly between merchants and Adyen support teams, not through API integration or plugin functionality.

2. **One-Time Migration**: This is a migration service for switching from other payment processors to Adyen, not an ongoing operational feature.

3. **Security and Compliance**: The process requires direct coordination with Adyen for security validation, encryption key exchange, and compliance verification.

4. **No API Endpoints**: Adyen doesn't provide public APIs for token import - it's handled through secure file exchange with support teams.

### Current Plugin Token Infrastructure:

The plugin provides comprehensive token management for **new tokens**:
- **AdyenToken Entity**: Complete token storage and management (`src/Entity/AdyenToken.php`)
- **Token Creation**: Automatic token creation during normal payment flows
- **Token Storage**: Customer-linked token storage with payment method association
- **Token Removal**: `AdyenClient::removeStoredToken()` for token lifecycle management
- **Recurring Payments**: Full support for using stored tokens for future payments

### Integration with Existing Token Infrastructure:

After Adyen processes imported tokens through their migration service, the results can be:
- **Manually Added**: Imported token references added to AdyenToken entities
- **Customer Linking**: Tokens linked to existing Sylius customers via shopper references
- **Payment Method Association**: Tokens associated with appropriate Sylius payment methods
- **Normal Operation**: Imported tokens work seamlessly with existing token management

### Alternative Migration Approaches:

1. **Adyen Migration Service**: Use Adyen's official token import service for bulk migration
2. **Gradual Migration**: Let customers naturally create new tokens through normal purchases
3. **Customer Re-enrollment**: Encourage customers to save new payment methods
4. **Manual Processing**: Handle VIP customers individually during migration

### Technical Implementation Requirements (If Needed):

**Post-Migration Token Integration:**
1. Create console command to import Adyen's processed CSV results
2. Parse returned token references and create AdyenToken entities
3. Link tokens to customers via shopper references
4. Associate with appropriate payment methods
5. Validate token status and recurring contract details

**Estimated Integration Effort:** 40-60 hours (1-1.5 weeks for 1 dev)

### Use Cases NOT Covered:

- **Direct API Token Import**: No automated bulk import through plugin APIs
- **Real-time Migration**: No live token transfer from other processors
- **Cross-platform Token Sharing**: No token exchange between different merchant accounts
- **Automated Validation**: No automated token verification during import

### Key Insight:

Token Import is an **Adyen migration service** handled by support teams, not a plugin feature. The plugin provides excellent token infrastructure that works seamlessly with imported tokens after Adyen's migration service processes them.

**Status:** ❌ NOT SUPPORTED  
**Coverage:** No direct integration with Adyen's manual token import migration service, but provides complete infrastructure for managing imported tokens after Adyen processes them