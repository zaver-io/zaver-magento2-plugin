<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Zaver\Payment\Observer\InstallmentContainer;

use Magento\Catalog\Block\ShortcutButtons;
use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zaver\Payment\Helper\Data;

class AddInstallment implements ObserverInterface
{
    /**
     * @var Data
     */
    private $_helper;

    public function __construct(
        Data $_helper
    ) {
        $this->config = $_helper;
    }

    public function execute(Observer $observer)
    {
        if (!$this->config->getZVApiKey()) { 
            return; 
            }             
        
        // We only want to show this button in the minicart
      /*  if ($observer->getData('is_catalog_product') || !$this->isEnabled()) {
            return;
        }
        */
        /** @var ShortcutButtons $shortcutButtons */
        $shortcutButtons = $observer->getEvent()->getContainer();
        $shortcut = $shortcutButtons->getLayout()->createBlock(Button::class);
        $shortcut->setIsCart(get_class($shortcutButtons) === QuoteShortcutButtons::class);
        $shortcutButtons->addShortcut($shortcut);
    }

}
