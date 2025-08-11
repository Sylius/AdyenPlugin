<?php

/*
 * This file is part of the Sylius Adyen Plugin package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('commodityCode', TextType::class, [
            'label' => 'sylius_adyen.form.product_variant.commodity_code',
            'help' => 'sylius_adyen.form.product_variant.commodity_code_help',
            'required' => false,
            'attr' => [
                'maxlength' => 12,
            ],
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }
}
