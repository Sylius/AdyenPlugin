# Installation

## Overview:
GENERAL
- [Requirements](#requirements)
- [Composer](#composer)
- [Basic configuration](#basic-configuration)
---
FRONTEND
- [Templates](#templates)
- [Webpack](#webpack)
---
ADDITIONAL
- [Additional configuration](#additional-configuration)
- [Known Issues](#known-issues)
---

## Requirements:
### Installed Refund Plugin
Complete installation instructions for the RefundPlugin can be found here:

- [RefundPlugin installation](https://github.com/Sylius/RefundPlugin)

We work on stable, supported and up-to-date versions of packages. We recommend you to do the same.

| Package       | Version         |
|---------------|-----------------|
| PHP           | \>8.0           |
| sylius/sylius | 1.12.x - 1.13.x |
| MySQL         | \>= 5.7         |
| NodeJS        | \>= 18.x        |

## Composer:
```bash
composer require sylius/adyen-plugin --no-scripts
```

## Basic configuration:
Add plugin dependencies to your `config/bundles.php` file:

```php
# config/bundles.php

return [
    ...
    Sylius\AdyenPlugin\SyliusAdyenPlugin::class => ['all' => true],
];
```

Import required config in your `config/packages/_sylius.yaml` file:

```yaml
# config/packages/_sylius.yaml

imports:
    ...
    - { resource: "@SyliusAdyenPlugin/config/config.yaml" }
```

Add Adyen payment method as a supported refund gateway in `config/packages/_sylius.yaml`:
```yaml
# config/packages/_sylius.yaml

parameters:
  sylius_refund.supported_gateways:
     - offline
     - adyen
```

Import routing in your `config/routes.yaml` file:
```yaml
# config/routes.yaml

sylius_adyen_plugin:
    resource: "@SyliusAdyenPlugin/config/routes.yaml"
```

Add logging to your environment in config/packages/{dev, prod, staging}/monolog.yaml
```yaml
# config/packages/{dev, prod, staging}/monolog.yaml

monolog:
    channels: [adyen]
    handlers: # Add alongside other handlers you might have
        doctrine:
            type: service
            channels: [adyen]
            id: sylius_adyen.logging.monolog.doctrine_handler
```

### Extend ProductVariant entity
Create or modify your ProductVariant entity to include the CommodityCodeAwareTrait and implement CommodityCodeAwareInterface:

```php
<?php
// src/Entity/Product/ProductVariant.php

declare(strict_types=1);

namespace App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareInterface;
use Sylius\AdyenPlugin\Entity\CommodityCodeAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;
use Sylius\Component\Product\Model\ProductVariantInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product_variant')]
class ProductVariant extends BaseProductVariant implements CommodityCodeAwareInterface
{
    use CommodityCodeAwareTrait;
}
```

Make sure your ProductVariant is properly registered in your `config/packages/sylius.yaml`:

```yaml
# config/packages/sylius.yaml
sylius_product:
    resources:
        product_variant:
            classes:
                model: App\Entity\Product\ProductVariant
```

### Update your database
First, please run legacy-versioned migrations by using command:
```bash
bin/console doctrine:migrations:migrate
```

After migration, please create a new diff migration and update database:
```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```
### Clear application cache by using command:
```bash
bin/console cache:clear
```
**Note:** If you are running it on production, add the `-e prod` flag to this command.

## Templates

### Run commands

```bash
mkdir -p templates/bundles/SyliusAdminBundle/Order/Show \
         templates/bundles/SyliusShopBundle/Checkout/Complete \
         templates/bundles/SyliusShopBundle/Checkout/SelectPayment && \
cp vendor/sylius/adyen-plugin/templates/bundles/SyliusAdminBundle/Order/Show/_payment.html.twig \
   templates/bundles/SyliusAdminBundle/Order/Show/ && \
cp vendor/sylius/adyen-plugin/templates/bundles/SyliusAdminBundle/Order/Show/_payments.html.twig \
   templates/bundles/SyliusAdminBundle/Order/Show/ && \
cp vendor/sylius/adyen-plugin/templates/bundles/SyliusShopBundle/Checkout/Complete/_navigation.html.twig \
   templates/bundles/SyliusShopBundle/Checkout/Complete/ && \
cp vendor/sylius/adyen-plugin/templates/bundles/SyliusShopBundle/Checkout/SelectPayment/_payment.html.twig \
   templates/bundles/SyliusShopBundle/Checkout/SelectPayment/ && \
```

## Assets

### Add the plugin's assets to your entrypoint files:
```js
// assets/admin/entrypoint.js

import '../../vendor/sylius/adyen-plugin/assets/admin/entrypoint';
```
and:
```js
// assets/shop/entrypoint.js

import '../../vendor/sylius/adyen-plugin/assets/shop/entrypoint';
```

### Install assets
```bash
bin/console assets:install public
```

## Webpack
### Run commands
```bash
yarn install
yarn encore dev # or prod, depends on your environment
```

## Additional configuration
- [Obtain Adyen credentials and configure the payment method](https://github.com/BitBagCommerce/SyliusAdyenPlugin/blob/master/doc/configuration.md)

If you want to access the log page, visit /adyen/log.

## Known issues
### Translations not displaying correctly
For incorrectly displayed translations, execute the command:
```bash
bin/console cache:clear
```
