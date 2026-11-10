<?php

namespace Zaver\Payment\Model;

/**
 * The Zaver payment method. The checkout lists the Zaver payment methods the Zaver API offers for the cart (e.g.
 * Pay later, Instalments, Bank transfer and their variants); the one the customer picks is kept on the payment and
 * decides which Zaver payment link the customer is sent to.
 */
class Checkout extends \Zaver\Payment\Model\Payment
{
  const METHOD_CODE = 'zaver_checkout';

  /** Additional information keys of the chosen Zaver payment method */
  const KEY_ZAVER_METHOD = 'zaver_payment_method';
  const KEY_ZAVER_VARIANT = 'zaver_payment_method_variant';
  const KEY_ZAVER_TITLE = 'zaver_payment_method_title';

  /**
   * Payment code
   *
   * @var string
   */
  protected $_code = self::METHOD_CODE;

  /**
   * Shows the chosen Zaver payment method in the admin, the customer account and emails
   *
   * @var string
   */
  protected $_infoBlockType = \Zaver\Payment\Block\Info::class;

  /**
   * Keep the Zaver payment method the customer chose in the checkout.
   *
   * @param \Magento\Framework\DataObject $data
   * @return $this
   */
  public function assignData(\Magento\Framework\DataObject $data) {
    parent::assignData($data);

    $additionalData = $data->getData(\Magento\Quote\Api\Data\PaymentInterface::KEY_ADDITIONAL_DATA);
    if (!is_array($additionalData)) {
      return $this;
    }

    $info = $this->getInfoInstance();
    foreach ([self::KEY_ZAVER_METHOD, self::KEY_ZAVER_VARIANT, self::KEY_ZAVER_TITLE] as $key) {
      if (array_key_exists($key, $additionalData)) {
        $info->setAdditionalInformation($key, (string)$additionalData[$key]);
      }
    }

    return $this;
  }

  /**
   * A Zaver payment method must have been chosen.
   *
   * @return $this
   * @throws \Magento\Framework\Exception\LocalizedException
   */
  public function validate() {
    parent::validate();

    if (empty($this->getInfoInstance()->getAdditionalInformation(self::KEY_ZAVER_METHOD))) {
      throw new \Magento\Framework\Exception\LocalizedException(__('Please choose a Zaver payment method.'));
    }

    return $this;
  }
}
