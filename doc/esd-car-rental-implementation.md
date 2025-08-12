# Adding New ESD Type: Car Rental

Technical documentation presenting the complete process of adding a new Enhanced Scheme Data type using Car Rental ESD as an example.

## ESD Architecture Overview

### Component Structure

The ESD system in the plugin is based on the following architecture:

#### Main Components:
- **EsdCollectorInterface** - interface for all ESD collectors
- **CompositeEsdCollector** - main collector aggregating all ESD types
- **EsdTypeProvider** - provider of available ESD types for forms
- **ClientPayloadFactory** - factory generating payloads with ESD data

#### Collector Pattern:
```php
interface EsdCollectorInterface
{
    public function supports(string $merchantCategoryCode): bool;
    public function collect(OrderInterface $order): array;
}
```

#### Tagging System:
Collectors are registered in the DI container with the `sylius_adyen.esd.collector` tag:
```xml
<tag name="sylius_adyen.esd.collector" priority="100" type="level3" />
```

#### Automatic Configuration:
- **EsdTypeProvider** automatically detects available types based on tagged services
- **CompositeEsdCollector** automatically selects the appropriate collector based on MCC
- Forms dynamically generate ESD options based on available types

## Car Rental ESD Implementation

### Step 1: Create CarRentalEsdCollector

```php
<?php

declare(strict_types=1);

namespace App\Collector;

use Sylius\Component\Core\Model\OrderInterface;

final class CarRentalEsdCollector implements EsdCollectorInterface
{
    public function supports(string $merchantCategoryCode): bool
    {
        return in_array($merchantCategoryCode, [
            // Put here the supported MCCs for Car Rental ESD
        ], true);
    }

    public function collect(OrderInterface $order): array
    {
        return [
            // Put here the needed ESD data for Car Rental
        ];
    }
}
```

### Step 2: Service Configuration

Add to `config/services.xml`:

```xml
<service
    id="app.collector.esd.car_rental"
    class="App\Collector\CarRentalEsdCollector"
>
    <tag name="sylius_adyen.esd.collector" priority="200" type="car_rental" />
</service>
```

### Step 3: Add Translations

In `translations/messages.en.yaml`:

```yaml
sylius_adyen:
    ui:
        esd_type_car_rental: Car Rental Data
```

### Gateway Configuration in Admin
Now, the administrator can either select "Car Rental Data" from the dropdown in the payment method configuration 
to force sending this data, or enter an MCC, in which case the system will automatically determine and return 
the appropriate ESD.
