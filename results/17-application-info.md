# Application Info - YES

## Why This IS Supported

The plugin provides comprehensive Adyen Application Information integration through automatic metadata transmission with every API request, meeting Adyen's requirements for payment analytics and troubleshooting:

### What IS Supported (Adyen Application Information Integration):

1. **Automatic Application Info Transmission**:
   - `VersionResolver.php:63` - `appendVersionConstraints()` automatically adds `applicationInfo` to all API requests
   - Complete metadata included in payments, captures, cancellations, refunds, and payment methods requests
   - No merchant configuration required - works out-of-the-box

2. **Comprehensive Platform Information**:
   - **Merchant Application**: Plugin name (`adyen-sylius`) and dynamic version tracking
   - **External Platform**: Sylius version detection and BitBag integrator identification
   - **Automatic Version Resolution**: Uses Composer's `InstalledVersions` for accurate plugin version tracking

3. **Dynamic Version Detection**:
   - `VersionResolver.php:22` - `getPluginVersion()` automatically detects plugin version from Composer
   - `VersionResolver.php:41` - `resolveApplicationInfo()` dynamically detects Sylius version across different Symfony versions
   - Fallback mechanisms for development environments

4. **Complete Metadata Structure**:
   ```php
   'applicationInfo' => [
       'merchantApplication' => [
           'name' => 'adyen-sylius',
           'version' => '[plugin-version]'
       ],
       'externalPlatform' => [
           'name' => 'Sylius',
           'version' => '[sylius-version]',
           'integrator' => 'BitBag'
       ]
   ]
   ```

5. **Universal API Integration**:
   - All payment operations include application info via `ClientPayloadFactory`
   - Consistent metadata across: payments, captures, cancellations, refunds, token operations
   - Automatic inclusion in `createForSubmitPayment()`, `createForCapture()`, `createForCancel()`, etc.

### Technical Implementation Details:

**Version Resolution Strategy:**
- Primary: Uses `\Composer\InstalledVersions::getPrettyVersion()` for accurate package version
- Fallback: Uses legacy `PackageVersions\FallbackVersions` for older environments  
- Development: Returns 'dev' version for local testing environments

**Platform Detection Logic:**
- Symfony 5: Uses `Sylius\Bundle\CoreBundle\Application\Kernel::VERSION`
- Modern Sylius: Uses `Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION` 
- Automatic detection handles Sylius version compatibility

**Integration Pattern:**
- `VersionResolverInterface` defines contract for version constraint addition
- All API requests automatically enhanced via `appendVersionConstraints()`
- Consistent metadata format across all Adyen API interactions

### Business Value and Benefits:

1. **Enhanced Support Experience**: Adyen support can immediately identify plugin and platform versions
2. **Analytics and Insights**: Adyen can analyze integration performance and usage patterns
3. **Troubleshooting Efficiency**: Automatic identification of integration configuration for issue resolution
4. **Partnership Attribution**: Proper BitBag integrator identification for platform partnerships
5. **Zero Configuration**: Works automatically without merchant setup or maintenance

### Why This Implementation Is Excellent:

- **Adyen Requirement Compliance**: Meets Adyen's technical requirements for technology partners
- **Automatic Operation**: No manual configuration or maintenance required
- **Comprehensive Coverage**: All API operations include proper application metadata
- **Version Accuracy**: Dynamic version detection ensures accurate tracking
- **Future-Proof**: Handles different Sylius and Symfony versions automatically

### Use Cases Fully Covered:

- Automatic plugin version tracking in all payments
- Platform partnership attribution (BitBag integrator identification)  
- Integration performance analytics for Adyen
- Support troubleshooting with automatic version identification
- Payment processing analytics with proper metadata
- Platform-level debugging with version information

### Technical Excellence:

The implementation demonstrates sophisticated integration patterns:
- **Dependency Injection**: Clean service architecture with version resolver interface
- **Error Handling**: Graceful fallbacks for version detection failures
- **Platform Compatibility**: Supports multiple Sylius and Symfony versions
- **Performance**: Minimal overhead with caching-friendly version resolution
- **Maintenance**: Self-updating version information without manual updates

**Status:** ✅ SUPPORTED
**Coverage:** Complete integration with Adyen Application Information requirements including automatic version tracking, platform identification, and metadata transmission