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

namespace Sylius\AdyenPlugin\Callback;

use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PreserveOrderTokenUponRedirectionCallback
{
    public const NON_FINALIZED_CART_SESSION_KEY = '_ADYEN_PAYMENT_IN_PROGRESS';

    /** @var ?SessionInterface */
    private $session;

    public function __construct(RequestStack $session)
    {
        if (null == $session->getMainRequest()) {
            return;
        }
        $this->session = $session->getSession();
    }

    public function __invoke(OrderInterface $order): void
    {
        if (null === $this->session) {
            return;
        }
        $tokenValue = $order->getTokenValue();

        if (null === $tokenValue) {
            return;
        }

        $this->session->set(
            self::NON_FINALIZED_CART_SESSION_KEY,
            $tokenValue,
        );
    }
}
