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

namespace Sylius\AdyenPlugin\Validator\Constraint;

use Adyen\Exception\HMACKeyValidationException;
use Sylius\AdyenPlugin\Provider\SignatureValidatorProviderInterface;
use Sylius\AdyenPlugin\Resolver\Notification\Struct\NotificationItemData;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Webmozart\Assert\Assert;

class HmacSignatureValidator extends ConstraintValidator
{
    public const PAYMENT_METHOD_FIELD_NAME = 'paymentCode';

    /** @var SignatureValidatorProviderInterface */
    private $signatureValidatorProvider;

    /** @var PropertyAccessorInterface */
    private $propertyAccessor;

    /** @var NormalizerInterface */
    private $normalizer;

    public function __construct(
        SignatureValidatorProviderInterface $signatureValidatorProvider,
        PropertyAccessorInterface $propertyAccessor,
        NormalizerInterface $normalizer,
    ) {
        $this->signatureValidatorProvider = $signatureValidatorProvider;
        $this->propertyAccessor = $propertyAccessor;
        $this->normalizer = $normalizer;
    }

    private function violate(bool $result, HmacSignature $constraint): void
    {
        if ($result) {
            return;
        }

        $this->context->buildViolation($constraint->message);
    }

    private function getPaymentCode(): string
    {
        /**
         * @var object|array $objectOrArray
         */
        $objectOrArray = $this->context->getRoot();

        return (string) $this->propertyAccessor->getValue(
            $objectOrArray,
            self::PAYMENT_METHOD_FIELD_NAME,
        );
    }

    private function getNormalizedNotificationData(NotificationItemData $value): array
    {
        $params = (array) $this->normalizer->normalize($value);
        $params['success'] = ($value->success ?? false) ? 'true' : 'false';

        return $params;
    }

    /**
     * @param mixed $value
     * @param Constraint|HmacSignature $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        Assert::isInstanceOf($value, NotificationItemData::class);
        Assert::isInstanceOf($constraint, HmacSignature::class);

        $validator = $this->signatureValidatorProvider->getValidatorForCode(
            $this->getPaymentCode(),
        );

        $params = $this->getNormalizedNotificationData($value);

        try {
            $result = $validator->isValid($params);
        } catch (HMACKeyValidationException $ex) {
            $result = false;
        }

        $this->violate($result, $constraint);
    }
}
