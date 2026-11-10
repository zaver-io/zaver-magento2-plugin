<?php

namespace Zaver\Payment\Block;

/**
 * Payment info of Zaver orders: the method title ("Zaver") plus the Zaver payment method the customer chose.
 */
class Info extends \Magento\Payment\Block\Info
{
  /**
   * @param \Magento\Framework\DataObject|array|null $transport
   * @return \Magento\Framework\DataObject
   */
  protected function _prepareSpecificInformation($transport = null) {
    $transport = parent::_prepareSpecificInformation($transport);
    $title = $this->getInfo()->getAdditionalInformation(\Zaver\Payment\Model\Checkout::KEY_ZAVER_TITLE);

    if (!empty($title)) {
      $transport->setData((string)__('Payment method'), $title);
    }

    return $transport;
  }
}
