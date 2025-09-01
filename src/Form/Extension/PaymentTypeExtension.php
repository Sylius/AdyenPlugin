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

use Sylius\AdyenPlugin\Form\Type\PaymentMethodChoiceType;
use Sylius\AdyenPlugin\Provider\PaymentMethodsProviderInterface;
use Sylius\AdyenPlugin\Repository\PaymentMethodRepositoryInterface;
use Sylius\AdyenPlugin\Resolver\Order\PaymentCheckoutOrderResolverInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\PaymentType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;

final class PaymentTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly PaymentCheckoutOrderResolverInterface $paymentCheckoutOrderResolver,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly PaymentMethodsProviderInterface $paymentMethodsProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $adyen = $builder->create('channels', FormType::class, [
            'compound' => true,
            'mapped' => false,
        ]);

        $paymentMethods = $this->paymentMethodRepository->findAllAdyenByChannel($this->channelContext->getChannel());
        foreach ($paymentMethods as $paymentMethod) {
            $order = $this->paymentCheckoutOrderResolver->resolve();
            $result = $this->paymentMethodsProvider->provideForOrder($paymentMethod->getCode(), $order);

            $adyen->add((string) $paymentMethod->getCode(), PaymentMethodChoiceType::class, [
                'environment' => $paymentMethod->getGatewayConfig()->getConfig()['environment'],
                'payment_methods' => $result['paymentMethods'],
            ]);
        }

        $builder->add($adyen);
    }

    public static function getExtendedTypes(): array
    {
        return [PaymentType::class];
    }
}
