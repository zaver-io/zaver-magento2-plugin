<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Zaver\Payment\Block\Info;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Payment\Block\Info;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Stdlib\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use zaver\Payment\Model\Config\ConfigProvider;
use zaver\Payment\Helper;
use zaver\Payment\Model\Installments;
use zaver\Payment\Model\Paylater;
use zaver\Payment\Model\Payment;
use zaver\Payment\Model\Paymentlink;
use zaver\Payment\Service\Magento\PaymentLinkUrl;

class Base extends Info
{
    /**
     * @var string
     */
    protected $_template = 'Zaver_Payment::info/zaver_base.phtml';
    /**
     * @var DateTime\TimezoneInterface
     */
    private $timezone;
    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var PriceCurrencyInterface
     */
    private $price;
    /**
     * @var ConfigProvider
     */
    private $config;
    /**
     * @var PaymentLinkUrl
     */
    private $paymentLinkUrl;

    public function __construct(
        Context $context,
        ConfigProvider $config,
        Registry $registry,
        PriceCurrencyInterface $price,
        PaymentLinkUrl $paymentLinkUrl
    ) {
        parent::__construct($context);
        $this->timezone = $context->getLocaleDate();
        $this->registry = $registry;
        $this->price = $price;
        $this->config = $config;
        $this->paymentLinkUrl = $paymentLinkUrl;
    }

    public function getCheckoutType(): ?string
    {
        try {
            return $this->getInfo()->getAdditionalInformation('checkout_type');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    public function getExpiresAt(): ?string
    {
        try {
            if ($expiresAt = $this->getInfo()->getAdditionalInformation('expires_at')) {
                return $this->timezone->date($expiresAt)->format(DateTime::DATETIME_PHP_FORMAT);
            }
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
        }

        return null;
    }

    public function getPaymentLink($storeId = null): ?string
    {
        return str_replace(
            '%link%',
            $this->getInfo()->getAdditionalInformation('Zaver Paylink'),
            $this->config->paymentLinkMessage($storeId)
        );
    }

    public function getPaymentLinkUrl(): string
    {
        return $this->paymentLinkUrl->execute((int)$this->getInfo()->getParentId());
    }

    public function getCheckoutUrl(): ?string
    {
        try {
            return $this->getInfo()->getAdditionalInformation('Zaver Paylink');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    public function getPaymentStatus(): ?string
    {
        try {
            return $this->getInfo()->getAdditionalInformation('payment_status');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    public function getDashboardUrl(): ?string
    {
        try {
            return $this->getInfo()->getAdditionalInformation('dashboard_url');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    public function getChangePaymentStatusUrl(): ?string
    {
        try {
            return (string)$this->getInfo()->getAdditionalInformation('zaver_change_payment_state_url');
        } catch (\Exception $exception) {
            return null;
        }
    }

    public function getzaverId(): ?string
    {
        try {
            return $this->getInfo()->getAdditionalInformation('zaver_id');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    /**
     * @return bool
     */
    public function isBuyNowPayLaterMethod(): bool
    {
        try {
            $code = $this->getInfo()->getMethod();
            $methods = [
                Installments::CODE,
                Paylater::CODE,
                Payment::CODE,
                Paymentlink::CODE,
                

            ];

            if (in_array($code, $methods)) {
                return true;
            }
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
        }

        return false;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPaymentImage(): string
    {
       return 'https://cdn.zaver.com/ui/brand/zaver_symbol.svg';
    }

    public function getOrderId(): ?string
    {
        try {
            return $this->getInfo()->getParentId();
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
            return null;
        }
    }

    public function getAmountPaidByVoucher()
    {
        if (!$this->getOrder()) {
            return false;
        }

        return $this->getOrder()->getGrandTotal() - $this->getRemainderAmount();
    }

    public function getRemainderAmount()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('remainder_amount');
        } catch (\Exception $e) {
            $this->zaverHelper->addTolog('error', $e->getMessage());
        }

        return false;
    }

    public function formatPrice($amount)
    {
        return $this->price->format($amount);
    }

    private function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }
}