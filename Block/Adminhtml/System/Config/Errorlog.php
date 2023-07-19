<?php

namespace Zaver\Payment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Errorlog extends Field
{
  /**
   * @var \Zaver\Payment\Helper\Data
   */
  public $_helper;

  /**
   * @var Magento\Framework\Filesystem\DirectoryList
   */
  protected $_directory;

  /**
   * @var LoggerInterface
   */
  protected $_logger;

  /**
   * Score constructor.
   *
   * @param \Magento\Backend\Block\Template\Context $context
   * @param \Zaver\Payment\Helper\Data $data
   */
  public function __construct(
    \Magento\Backend\Block\Template\Context $context,
    \Zaver\Payment\Helper\Data $data,
    \Magento\Framework\Filesystem\DirectoryList $directory,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->_helper = $data;
    $this->_directory = $directory;
    $this->_logger = $logger;

    parent::__construct($context);
  }

  /**
   * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
   *
   * @return string
   */
  public function _getElementHtml(
    \Magento\Framework\Data\Form\Element\AbstractElement $element
  ) {
    $fileName = $this->_helper->getErrorLogPath();
    $baseurl = $this->_directory->getPath("log") . $fileName;
    $urlFile = $this->getUrl("zaver_payment/system_config/downloadlog");

    if (file_exists($baseurl)) {
      $strText = '<a href="' . $urlFile . '" download>' . __('Download file') . '</a>';
      return $strText;
    }
    else {
      $strText = __('None available');
    }

    $element->setData('value', $strText);

    return parent::_getElementHtml($element);
  }
}
