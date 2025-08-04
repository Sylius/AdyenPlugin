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

use Monolog\Logger;
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
                    'sylius_adyen.ui.logging.info' => Logger::INFO,
                    'sylius_adyen.ui.logging.debug' => Logger::DEBUG,
                    'sylius_adyen.ui.logging.error' => Logger::ERROR,
                ],
            ]);
    }
}
