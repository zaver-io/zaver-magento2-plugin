<?php

namespace Zaver\Payment\Controller;

/**
 * Base Controller Class
 * Class AbstractAction
 * @package Zaver\Payment\Controller
 */
abstract class AbstractAction extends \Magento\Framework\App\Action\Action
{
  /**
   * @var \Magento\Framework\App\Action\Context
   */
  private $_context;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $_logger;

  /**
   * @param \Magento\Framework\App\Action\Context $context
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(
    \Magento\Framework\App\Action\Context $context,
    \Psr\Log\LoggerInterface $logger
  ) {
    parent::__construct($context);
    $this->_context = $context;
    $this->_logger = $logger;
  }

  /**
   * Get Instance of Magento Controller Action
   * @return \Magento\Framework\App\Action\Context
   */
  protected function getContext() {
    return $this->_context;
  }

  /**
   * Get Instance of Magento Object Manager
   * @return \Magento\Framework\ObjectManagerInterface
   */
  protected function getObjectManager() {
    return $this->_objectManager;
  }

  /**
   * Get Instance of Magento global Message Manager
   * @return \Magento\Framework\Message\ManagerInterface
   */
  protected function getMessageManager() {
    return $this->getContext()->getMessageManager();
  }

  /**
   * Get Instance of Magento global Logger
   * @return \Psr\Log\LoggerInterface
   */
  protected function getLogger() {
    return $this->_logger;
  }

  /**
   * Get Instance of Magento scope config interface
   * @return \Magento\Framework\App\Config\ScopeConfigInterface
   */
  protected function getScopeConfig() {
    return $this->_scopeConfig;
  }

  /**
   * Get Instance of Magento scope manager interface
   * @return \Magento\Store\Model\StoreManagerInterface
   */
  protected function getStoreManager() {
    return $this->_storeManager;
  }

  /**
   * Check if param exists in the post request
   * @param string $key
   * @return bool
   */
  protected function isPostRequestExists($key) {
    $post = $this->getPostRequest();

    return isset($post[$key]);
  }

  /**
   * Get an array of the Submitted Post Request
   * @param string|null $key
   * @return null|array
   */
  protected function getPostRequest($key = null) {
    $post = $this->getRequest()->getPostValue();

    if (isset($key) && isset($post[$key])) {
      return $post[$key];
    }
    elseif (isset($key)) {
      return null;
    }
    else {
      return $post;
    }
  }
}
