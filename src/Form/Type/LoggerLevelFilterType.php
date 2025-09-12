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

use Monolog\Level;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class LoggerLevelFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loggerLevel', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    'sylius_adyen.ui.logging.info' => Level::Info->value,
                    'sylius_adyen.ui.logging.debug' => Level::Debug->value,
                    'sylius_adyen.ui.logging.error' => Level::Error->value,
                ],
                'data_class' => null,
                'required' => false,
                'placeholder' => 'sylius.ui.all',
            ])
        ;
    }
}
