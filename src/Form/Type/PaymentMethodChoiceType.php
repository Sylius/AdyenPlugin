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

namespace Sylius\AdyenPlugin\Form\Type;

use Sylius\AdyenPlugin\Client\AdyenClientInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PaymentMethodChoiceType extends ChoiceType
{
    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options,
    ): void {
        parent::buildView($view, $form, $options);
        $view->vars['environment'] = $options['environment'];
        $view->vars['payment_methods'] = $options['payment_methods'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('environment', AdyenClientInterface::TEST_ENVIRONMENT);
        $resolver->setDefault('payment_methods', []);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
