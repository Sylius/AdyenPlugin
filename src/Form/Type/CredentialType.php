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

use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

final class CredentialType extends PasswordType
{
    public const CREDENTIAL_PLACEHOLDER = '#CREDENTIAL_PLACEHOLDER#';

    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options,
    ): void {
        if (0 === strlen((string) $view->vars['value']) || $form->isSubmitted()) {
            return;
        }

        $view->vars['value'] = self::CREDENTIAL_PLACEHOLDER;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (SubmitEvent $event): void {
                if (self::CREDENTIAL_PLACEHOLDER !== $event->getData()) {
                    return;
                }

                $event->setData(
                    $event->getForm()->getNormData(),
                );
            },
        );
    }
}
