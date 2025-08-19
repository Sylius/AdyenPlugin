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

namespace Tests\Sylius\AdyenPlugin\Functional\ResponseProcessing\PaymentResponseProcessor;

use PHPUnit\Framework\Attributes\DataProvider;
use Sylius\AdyenPlugin\Processor\PaymentResponseProcessor\ProcessorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Router;

abstract class AbstractProcessor extends KernelTestCase
{
    protected const DEFAULT_ROUTE_LOCALE = 'en_US';

    /** @var ProcessorInterface */
    protected $processor;

    abstract public static function provideForTestAccepts(): array;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public static function getRouter(ContainerInterface $container): Router
    {
        $router = $container->get('router');
        $requestContext = new RequestContext();
        $requestContext->setParameter('_locale', self::DEFAULT_ROUTE_LOCALE);

        $router->setContext($requestContext);

        return $router;
    }

    #[DataProvider('provideForTestAccepts')]
    public function testAccepts(string $code, bool $accepts): void
    {
        $payment = $this->getPayment($code);
        $this->assertEquals(
            $accepts,
            $this->processor->accepts(Request::create('/'), $payment),
        );
    }

    protected function createRequestWithSession(): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        return $request;
    }

    protected function getPayment(?string $resultCode = null, ?string $orderToken = null): PaymentInterface
    {
        $details = [];
        if (null !== $resultCode) {
            $details['resultCode'] = $resultCode;
        }

        $result = $this->createMock(PaymentInterface::class);
        $result
            ->method('getDetails')
            ->willReturn($details)
        ;

        if (null !== $orderToken) {
            $order = $this->createMock(OrderInterface::class);
            $order
                ->method('getTokenValue')
                ->willReturn($orderToken)
            ;
            $result
                ->method('getOrder')
                ->willReturn($order)
            ;
        }

        return $result;
    }
}
