# Enhanced Scheme Data (ESD) - NO

## Why This Is NOT Supported

The plugin lacks Enhanced Scheme Data integration because it requires additional transaction metadata handling, though it's a relatively simple feature to implement:

### What Enhanced Scheme Data Actually Is:

According to Adyen's official documentation, Enhanced Scheme Data (ESD) is **not software delivery** but rather **optional additional transaction metadata** with these characteristics:

1. **Transaction Metadata**: Additional information sent with payment or capture requests in the `additionalData` object
2. **Industry-Specific**: Different ESD types based on Merchant Category Codes (MCCs) including:
   - Level 2/3 data for corporate cards
   - Airline-specific data
   - Car rental data  
   - Lodging data
   - Temporary services data
3. **Fee Reduction**: Can lower interchange fees by up to 1% for US domestic transactions
4. **Statement Details**: Provides more purchase information on customer card statements

### Excellent Foundation for Integration:

The plugin has outstanding infrastructure that could easily support ESD integration:

1. **Payment API Integration**:
   - `AdyenClient.php` already handles payment requests with `additionalData`
   - `ClientPayloadFactory.php` builds request payloads and could include ESD data
   - Existing API structure supports additional metadata

2. **Order and Product Information**:
   - Deep integration with Sylius order management
   - Access to product details, pricing, tax information
   - Customer and shipping information readily available

3. **Industry Flexibility**:
   - Plugin serves various merchant types and industries
   - Could dynamically generate appropriate ESD based on merchant configuration
   - Existing configuration system could handle ESD preferences

### Implementation Requirements:

1. **ESD Data Collection**: Extend order/product data collection to gather ESD-relevant information
2. **MCC-based Logic**: Implement logic to determine appropriate ESD type based on merchant industry
3. **Payload Enhancement**: Modify `ClientPayloadFactory` to include ESD data in `additionalData`
4. **Configuration Interface**: Add admin interface for ESD preferences and merchant category settings

### Business Context and Value:

- **Fee Reduction**: Up to 1% interchange fee reduction for US domestic transactions
- **Industry Benefits**: Particularly valuable for B2B merchants and corporate card transactions
- **Statement Clarity**: Improved transaction details on customer statements
- **Reporting Enhancement**: Better transaction data for merchant reporting

### Why Implementation Makes Sense:

- **Simple Integration**: Only requires adding metadata to existing payment requests
- **Strong Foundation**: Plugin already handles `additionalData` and has access to all order information
- **Cost Savings**: Potential fee reduction provides direct ROI for merchants
- **Low Risk**: Optional data that doesn't affect payment processing if invalid

### Simple Integration Approach:

The integration would be straightforward:

1. **Data Mapping**: Map Sylius order/product data to appropriate ESD fields
2. **MCC Configuration**: Add merchant category configuration to determine ESD type
3. **Payload Extension**: Enhance existing payload factory to include ESD data
4. **Admin Interface**: Add ESD configuration options to existing admin framework

### Key Insight:

This is **not about building software delivery systems** but about **adding transaction metadata** to reduce fees and improve reporting. The complexity is very low because:
- Uses existing payment request structure
- Leverages existing order/product data
- No new APIs or services required
- Optional enhancement that doesn't break existing functionality

### Use Cases That Would Be Covered:
- Level 2/3 data for corporate card fee reduction
- Industry-specific transaction metadata (airline, lodging, car rental)
- Enhanced customer statement information
- Improved merchant reporting and analytics
- Interchange fee optimization for US transactions

### Use Cases Still Not Covered:
- Non-standard ESD formats beyond Adyen's supported types
- Custom metadata validation beyond Adyen's requirements
- Advanced ESD analytics beyond standard reporting

### Technical Implementation:

**Required Changes:**
1. Extend `ClientPayloadFactory` to include ESD data in `additionalData`
2. Add ESD data collection from Sylius orders/products
3. Create MCC-based ESD type determination logic
4. Add admin configuration for ESD preferences
5. Optional: ESD validation and reporting

**Estimated Complexity:** Low - primarily data mapping and payload enhancement

**Status:** ❌ NOT SUPPORTED (but very easy to integrate with existing infrastructure)
**Coverage:** Excellent technical foundation exists for Enhanced Scheme Data integration with minimal development effort