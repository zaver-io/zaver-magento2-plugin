<?php

namespace Zaver\Payment\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Pluginversion extends Field
{
  /**
   * @var \Zaver\Payment\Helper\Data
   */
  public $_helper;

  /**
   * Score constructor.
   *
   * @param \Magento\Backend\Block\Template\Context $context
   * @param \Zaver\Payment\Helper\Data $data
   */
  public function __construct(
    \Magento\Backend\Block\Template\Context $context,
    \Zaver\Payment\Helper\Data $data
  ) {
    $this->_helper = $data;

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
    $text = $this->_helper->getExtensionVersion();
    $element->setData('value', $text);

    return parent::_getElementHtml($element);
  }
}
