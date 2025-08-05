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
use Sylius\AdyenPlugin\Provider\AdyenClientProviderInterface;
use Sylius\AdyenPlugin\Validator\Constraint\AdyenCredentials;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('environment', ChoiceType::class, [
                'choices' => [
                    'sylius_adyen.ui.test_platform' => AdyenClientInterface::TEST_ENVIRONMENT,
                    'sylius_adyen.ui.live_platform' => AdyenClientInterface::LIVE_ENVIRONMENT,
                ],
                'label' => 'sylius_adyen.ui.platform',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.environment.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('merchantAccount', TextType::class, [
                'label' => 'sylius_adyen.ui.merchant_account',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.merchant_account.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('liveEndpointUrlPrefix', TextType::class, [
                'label' => 'sylius_adyen.ui.live_endpoint_url_prefix',
            ])
            ->add('apiKey', CredentialType::class, [
                'label' => 'sylius_adyen.ui.api_key',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.api_key.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('clientKey', CredentialType::class, [
                'label' => 'sylius_adyen.ui.client_key',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.merchant_account.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('hmacKey', CredentialType::class, [
                'label' => 'sylius_adyen.ui.hmac_key',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.hmac_key.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('authUser', CredentialType::class, [
                'label' => 'sylius_adyen.ui.auth_user',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.auth_user.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('authPassword', CredentialType::class, [
                'label' => 'sylius_adyen.ui.auth_password',
                'constraints' => [
                    new NotBlank([
                        'message' => 'sylius_adyen.auth_password.not_blank',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add(AdyenClientProviderInterface::FACTORY_NAME, HiddenType::class, [
                'data' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('constraints', [
            new AdyenCredentials([
                'groups' => ['sylius'],
            ]),
        ]);
    }
}
