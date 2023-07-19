<?php

namespace Zaver\Payment\Model;

class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{
  protected $_code = 'zaver_payment';
  protected $_isGateway = true;
  protected $_canCapture = true;
  protected $_canCapturePartial = true;
  protected $_canRefund = true;
  protected $_canRefundInvoicePartial = true;
  protected $_isOffline = false;
  protected $_canVoid = true;
  protected $_request;
  protected $customerSession;
  protected $_checkoutSession;
  protected $zvDataHelper;

  public function __construct(
    \Magento\Framework\Model\Context $context,
    \Magento\Framework\Registry $registry,
    \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
    \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
    \Magento\Payment\Helper\Data $paymentData,
    \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
    \Magento\Payment\Model\Method\Logger $logger,
    \Magento\Customer\Model\Session $customerSession,
    \Magento\Checkout\Model\Session $checkoutSession,
    \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
    \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
    array $data = []
  ) {
    parent::__construct(
      $context,
      $registry,
      $extensionFactory,
      $customAttributeFactory,
      $paymentData,
      $scopeConfig,
      $logger,
      $resource,
      $resourceCollection,
      $data
    );
    $this->_customerSession = $customerSession;
    $this->_checkoutSession = $checkoutSession;
    $this->_code = $this->getCode();
  }

  public function getDefaultSuccessPageUrl() {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $urlInterface = $objectManager->get(‘\Magento\Framework\UrlInterface’);
    return $urlInterface->getUrl('zaver_payment/index/redirect');
  }

  public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    if (!$this->canAuthorize()) {
      throw new \Magento\Framework\Exception\LocalizedException(__('The authorize action is not available .'));
    }
    return $this;
  }

  public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    if (!$this->canCapture()) {
      throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available .'));
    }
    return $this;
  }

  public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount) {
    if (!$this->canRefund()) {
      throw new \Magento\Framework\Exception\LocalizedException(__('The refund action is not available .'));
    }
    return $this;
  }

  public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null) {
    return parent::isAvailable($quote);
  }
}
