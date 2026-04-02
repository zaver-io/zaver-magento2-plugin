<?php
/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Zaver\Payment\Observer;

use Magento\Catalog\Block\ShortcutButtons;
use Magento\Checkout\Block\QuoteShortcutButtons;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zaver\Payment\Block\Installment\InstallmentRate;
use Zaver\Payment\Helper\Data;

class AddZaverInstallmentRateObserver implements ObserverInterface
{
    /**
     * @var Data
     */
    private $config;

    public function __construct(
        Data $config
    ) {
        $this->config = $config;
    }

    public function execute(Observer $observer)
    {
       
        // We only want to show this button in the minicart
        if ($observer->getData('is_catalog_product')) {

                    /** @var ShortcutButtons $shortcutButtons */
        $shortcutButtons = $observer->getEvent()->getContainer();
        $shortcut = $shortcutButtons->getLayout()->createBlock(InstallmentRate::class);
        $shortcut->setIsCart(get_class($shortcutButtons) === QuoteShortcutButtons::class);
        $shortcutButtons->addShortcut($shortcut);
                    echo 'zaver_rate';

            
        }

    }

}