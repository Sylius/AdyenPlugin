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

namespace Sylius\AdyenPlugin\Factory;

use Sylius\AdyenPlugin\Entity\AdyenReferenceInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\RefundPlugin\Entity\RefundPaymentInterface;
use Webmozart\Assert\Assert;

final class AdyenReferenceFactory implements AdyenReferenceFactoryInterface
{
    public function __construct(private readonly FactoryInterface $baseFactory)
    {
    }

    public function createForPayment(PaymentInterface $payment): AdyenReferenceInterface
    {
        $details = $payment->getDetails();
        Assert::keyExists($details, 'pspReference', 'Payment does not contain pspReference');

        $result = $this->createNew();
        $result->setPayment($payment);
        $result->setPspReference((string) $details['pspReference']);

        return $result;
    }

    public function createForRefund(
        string $reference,
        PaymentInterface $payment,
        RefundPaymentInterface $refundPayment,
    ): AdyenReferenceInterface {
        $result = $this->createNew();
        $result->setPayment($payment);
        $result->setRefundPayment($refundPayment);
        $result->setPspReference($reference);

        return $result;
    }

    public function createNew(): AdyenReferenceInterface
    {
        /**
         * @var AdyenReferenceInterface $result
         */
        $result = $this->baseFactory->createNew();

        return $result;
    }
}
