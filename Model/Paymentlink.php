<?php
/**
 * Copyright © 2018 Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace zaver\payment\Model;

use Magento\Sales\Model\Order;
use zaver\payment\Model\Payment;
use Magento\Framework\DataObject;

/**
 * Class Paymentlink
 *
 */
class Paymentlink extends \Zaver\Payment\Model\Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    const METHOD_CODE = 'zaver_paymentlink';
    const CODE = 'zaver_paymentlink';

    protected $_code = self::METHOD_CODE;
    protected $_canUseInternal = true;
    protected $_infoBlockType = 'Zaver\Payment\Block\Info\Zaver_Paymentlink';

    /**
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initialize($paymentAction, $stateObject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $this->getInfoInstance();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(Order::STATE_PENDING_PAYMENT);

    }

    /**
     * @param DataObject $data
     *
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(DataObject $data)
    {
        $limitedMethods = null;
        parent::assignData($data);

        if ($additionalData = $data->getData('additional_data')) {
            if (isset($additionalData['limited_methods'])) {
                $limitedMethods = $additionalData['limited_methods'];
            }
        }

        $this->getInfoInstance()->setAdditionalInformation('limited_methods', $limitedMethods);

        return $this;
    }
}