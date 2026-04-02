<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Zaver\Payment\Observer\StartPaylink;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Module\Manager;
use Magento\InstantPurchase\Model\QuoteManagement\PaymentConfiguration;
use Magento\Sales\Api\Data\OrderInterface;
use Zaver\Payment\Controller\Paylink\CreatePaylink;
use Zaver\Payment\Helper\Data;
use \Magento\Framework\Exception\LocalizedException;

class StartPaylink implements ObserverInterface
{
    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @var CreatePaylink
     */
    private $zaver;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var null|string
     */
    private $redirectUrl = null;

    public function __construct(
        Manager $moduleManager,
        CreatePaylink $zaver,
        Data $helper
    ) {
        $this->moduleManager = $moduleManager;
        $this->zaver = $zaver;
        $this->helper = $helper;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function execute(Observer $observer): void
    {

        if (!$this->moduleManager->isEnabled('Magento_InstantPurchase')) {
            return;
        }

        if (!$observer->hasData('order')) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $observer->getData('order');

        if ($order->getPayment()->getMethod() !== 'zaver_paymentlink') {
            return;
        }

        $this->redirectUrl = $this->zaver->startTransaction($order, $this->helper);
    }

}
