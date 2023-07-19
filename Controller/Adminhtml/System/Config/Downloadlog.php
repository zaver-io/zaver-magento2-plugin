<?php

namespace Zaver\Payment\Controller\Adminhtml\System\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * file download controller.
 */
class Downloadlog extends Action
{
  /**
   * @var Magento\Framework\App\Response\Http\FileFactory
   */
  protected $_downloader;

  /**
   * @var Magento\Framework\Filesystem\DirectoryList
   */
  protected $_directory;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $_logger;

  /**
   * @param Context $context
   * @param PageFactory $resultPageFactory
   */
  public function __construct(
    Context $context,
    \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
    \Magento\Framework\Filesystem\DirectoryList $directory,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->_downloader = $fileFactory;
    $this->directory = $directory;
    $this->_logger = $logger;

    parent::__construct($context);
  }

  public function execute() {
    $fileName = "zaver.log";
    $file = $this->directory->getPath("log") . "/zaver/" . $fileName;

    /**
     * do file download
     */
    return $this->_downloader->create(
      $fileName,
      @file_get_contents($file)
    );
  }
}