<?php

namespace Zaver\Payment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Data extends AbstractHelper
{
  /**
   * @var ScopeConfigInterface
   */
  protected $scopeConfig;

  /**
   * @var ModuleListInterface
   */
  protected $moduleList;

  /**
   * @var \Magento\Store\Model\StoreManagerInterface
   */
  protected $storeManager;

  const LOG_FILENAME = 'zaver.log';
  const ERROR_LOG_PATH = "/zaver/zaver.log";
  const PLUGIN_CODE = 'zaver';

  const PLUGIN_CODE_TXT = 'Zaver ';

  const PLUGIN_PREFIX = 'zv_';

  const VAR_CONFIG = 'zaver_config';

  const KEY_LOG_LEVEL = 'logLevel';

  const ORDER_OK = 'OK';
  const ORDER_ERROR = 'ERROR';
  const ORDER_NOT_FINISHED = 'NOT_FINISHED';
  const ORDER_IN_PAYMENT = 'IN_PAYMENT';
  const ORDER_IN_PROCESS = 'IN_PROCESS';
  const ORDER_CANCELED = 'CANCELED';

  const XML_PATH_ZV_HOSTURL = 'payment/zaver/hosturl';
  const XML_PATH_ZV_APIKEY = 'payment/zaver/apikey';
  const XML_PATH_ZV_CALLBACKTOKEN = 'payment/zaver/callbacktoken';
  const XML_PATH_ZV_AUTOCAPTURE = 'payment/zaver/autocapture';
  const XML_PATH_ZV_TESTMODE = 'payment/zaver/testmode';
  const XML_PATH_ZV_PAYLATER_ACTIVE = 'payment/zaver_paylater/active';
  const XML_PATH_ZV_INSTALLMENTS_ACTIVE = 'payment/zaver_installments/active';
  const XML_PATH_GN_LOCALE = 'general/locale/code';

  const ZAVER_PAYLATER_CODE = 'PAY_LATER';
  const ZAVER_INSTALLMENTS_CODE = 'INSTALLMENTS';

  /**
   *
   * @var array
   */
  private $methodsCode = [
    "zaver_paylater" => self::ZAVER_PAYLATER_CODE,
    "zaver_installments" => self::ZAVER_INSTALLMENTS_CODE
  ];

  /**
   * Data constructor.
   *
   * @param Context $context
   * @param ScopeConfigInterface $scopeConfig
   * @param ModuleListInterface $moduleList
   * @param StoreManagerInterface $storeManager
   */
  public function __construct(Context $context,
                              ScopeConfigInterface $scopeConfig,
                              ModuleListInterface $moduleList,
                              StoreManagerInterface $storeManager) {
    $this->scopeConfig = $scopeConfig;
    $this->moduleList = $moduleList;
    $this->storeManager = $storeManager;

    parent::__construct($context);
  }

  /**
   * Get the Host URL from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVHostUrl() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_HOSTURL, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the Api-Key from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVApiKey() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_APIKEY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the callbacktoken from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVCallbackToken() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_CALLBACKTOKEN, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the autocapture from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVAutoCapture() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_AUTOCAPTURE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the active from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVTestMode() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_TESTMODE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the active from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVPayLaterActive() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_PAYLATER_ACTIVE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the active from Store->Settings->Configuration->Sales->Payment Methods->Zaver
   */
  public function getZVInstallmentsActive() {
    return $this->scopeConfig->getValue(self::XML_PATH_ZV_INSTALLMENTS_ACTIVE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
  }

  /**
   * Get the locale from General->locale
   */
  public function getLocale() {
    $locale = $this->scopeConfig->getValue(self::XML_PATH_GN_LOCALE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    return substr($locale, 0, 2);
  }

  /**
   * Get the plugin version
   */
  public function getExtensionVersion() {
    $moduleCode = 'Zaver_Payment';
    $moduleInfo = $this->moduleList->getOne($moduleCode);
    return $moduleInfo['setup_version'];
  }

  /**
   * Get an Instance of the Magento Store Manager
   * @return \Magento\Store\Model\StoreManagerInterface
   */
  protected function getStoreManager() {
    return $this->storeManager;
  }

  /**
   * @param string
   * @throws NoSuchEntityException If given store doesn't exist.
   * @return string
   */
  public function getSuccessUrl($orderId) {
    return $this->getStoreManager()->getStore()->getBaseUrl() . "zaver/checkout/redirect?action=success&orderId=$orderId";
  }

  /**
   * @param string
   * @throws NoSuchEntityException If given store doesn't exist.
   * @return string
   */
  public function getCancelUrl($orderId) {
    return $this->getStoreManager()->getStore()->getBaseUrl() . "zaver/checkout/redirect?action=cancel&orderId=$orderId";
  }

  /**
   * @param string
   * @throws NoSuchEntityException If given store doesn't exist.
   * @return string
   */
  public function getCallbackUrl($orderId) {
    return $this->getStoreManager()->getStore()->getBaseUrl() . "zaver/callback/index?orderId=$orderId";
  }

  /**
   * @param string
   * @throws NoSuchEntityException If given store doesn't exist.
   * @return string
   */
  public function getRefundCallbackUrl($orderId) {
    return $this->getStoreManager()->getStore()->getBaseUrl() . "zaver/callback/refund?orderId=$orderId";
  }

  /**
   * Get the installments payment code
   */
  public function getZaverInstallmentsCode() {
    return self::ZAVER_INSTALLMENTS_CODE;
  }

  /**
   * Get the pay later payment code
   */
  public function getZaverPayLaterCode() {
    return self::ZAVER_PAYLATER_CODE;
  }

  /**
   * Get the zaver method code
   */
  public function getZaverMethodCode($code) {
    return $this->methodsCode[$code];
  }

  /**
   * Get the error log path
   */
  public function getErrorLogPath() {
    return self::ERROR_LOG_PATH;
  }
}