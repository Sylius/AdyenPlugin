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

namespace Tests\Sylius\AdyenPlugin\Unit\Checker;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\AdyenPlugin\Checker\OrderCheckoutCompleteIntegrityChecker;
use Sylius\AdyenPlugin\Exception\CheckoutValidationException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class OrderCheckoutCompleteIntegrityCheckerTest extends TestCase
{
    private MockObject|OrderProcessorInterface $orderProcessor;

    private MockObject|ObjectManager $orderManager;

    private MockObject|ValidatorInterface $validator;

    private MockObject|TranslatorInterface $translator;

    private OrderCheckoutCompleteIntegrityChecker $checker;

    protected function setUp(): void
    {
        $this->orderProcessor = $this->createMock(OrderProcessorInterface::class);
        $this->orderManager = $this->createMock(ObjectManager::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->checker = new OrderCheckoutCompleteIntegrityChecker(
            $this->orderProcessor,
            $this->orderManager,
            $this->validator,
            $this->translator,
            ['sylius_checkout_complete'],
        );
    }

    public function testItThrowsExceptionWhenValidationConstraintsAreViolated(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $violation1 = $this->createMock(ConstraintViolation::class);
        $violation1->expects($this->once())
            ->method('getMessage')
            ->willReturn('First validation error');

        $violation2 = $this->createMock(ConstraintViolation::class);
        $violation2->expects($this->once())
            ->method('getMessage')
            ->willReturn('Second validation error');

        $violationList = new ConstraintViolationList([$violation1, $violation2]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($violationList);

        $this->orderProcessor
            ->expects($this->never())
            ->method('process');

        $this->orderManager
            ->expects($this->never())
            ->method('flush');

        $this->expectException(CheckoutValidationException::class);
        $this->expectExceptionMessage('First validation error, Second validation error');

        $this->checker->check($order);
    }

    public function testItThrowsExceptionWhenOrderTotalChangesAfterProcessing(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $emptyViolationList = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($emptyViolationList);

        $order
            ->expects($this->exactly(2))
            ->method('getTotal')
            ->willReturnOnConsecutiveCalls(1000, 1200);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($order);

        $this->orderManager
            ->expects($this->once())
            ->method('flush');

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with('sylius_adyen.runtime.order_total_changed')
            ->willReturn('Your order total has been changed, refresh the page and try again.');

        $this->expectException(CheckoutValidationException::class);
        $this->expectExceptionMessage('Your order total has been changed, refresh the page and try again.');

        $this->checker->check($order);
    }

    public function testItSucceedsWhenValidationPassesAndTotalDoesNotChange(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $emptyViolationList = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($emptyViolationList);

        $order
            ->expects($this->exactly(2))
            ->method('getTotal')
            ->willReturnOnConsecutiveCalls(1000, 1000);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($order);

        $this->orderManager
            ->expects($this->never())
            ->method('flush');

        $this->translator
            ->expects($this->never())
            ->method('trans');

        $this->checker->check($order);
    }

    public function testItUsesCustomValidationGroups(): void
    {
        $customValidationGroups = ['custom_group_1', 'custom_group_2'];

        $customChecker = new OrderCheckoutCompleteIntegrityChecker(
            $this->orderProcessor,
            $this->orderManager,
            $this->validator,
            $this->translator,
            $customValidationGroups,
        );

        $order = $this->createMock(OrderInterface::class);
        $emptyViolationList = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, $customValidationGroups)
            ->willReturn($emptyViolationList);

        $order
            ->expects($this->exactly(2))
            ->method('getTotal')
            ->willReturnOnConsecutiveCalls(1500, 1500);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($order);

        $customChecker->check($order);
    }

    public function testItHandlesSingleValidationViolation(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $violation = $this->createMock(ConstraintViolation::class);
        $violation->expects($this->once())
            ->method('getMessage')
            ->willReturn('Single validation error');

        $violationList = new ConstraintViolationList([$violation]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($violationList);

        $this->expectException(CheckoutValidationException::class);
        $this->expectExceptionMessage('Single validation error');

        $this->checker->check($order);
    }

    public function testItHandlesOrderTotalDecreaseAfterProcessing(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $emptyViolationList = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($emptyViolationList);

        $order
            ->expects($this->exactly(2))
            ->method('getTotal')
            ->willReturnOnConsecutiveCalls(1000, 800);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($order);

        $this->orderManager
            ->expects($this->once())
            ->method('flush');

        $this->translator
            ->expects($this->once())
            ->method('trans')
            ->with('sylius_adyen.runtime.order_total_changed')
            ->willReturn('Your order total has been changed, refresh the page and try again.');

        $this->expectException(CheckoutValidationException::class);
        $this->expectExceptionMessage('Your order total has been changed, refresh the page and try again.');

        $this->checker->check($order);
    }

    public function testItHandlesZeroTotalOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);

        $emptyViolationList = new ConstraintViolationList([]);

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($order, null, ['sylius_checkout_complete'])
            ->willReturn($emptyViolationList);

        $order
            ->expects($this->exactly(2))
            ->method('getTotal')
            ->willReturnOnConsecutiveCalls(0, 0);

        $this->orderProcessor
            ->expects($this->once())
            ->method('process')
            ->with($order);

        $this->orderManager
            ->expects($this->never())
            ->method('flush');

        $this->translator
            ->expects($this->never())
            ->method('trans');

        $this->checker->check($order);
    }
}
