<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Zaver\Payment\Catalog\Block\Product;

use Magento\Catalog\Pricing\Price;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Render\PriceBox as BasePriceBox;
use Magento\Msrp\Pricing\Price\MsrpPrice;
use Magento\Catalog\Model\Product\Pricing\Renderer\SalableResolverInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\Render\RendererPool;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Pricing\Price\MinimalPriceCalculatorInterface;
use Zaver\Payment\Helper\Data;
use Exception; 

/**
 * Class for final_price rendering
 *
 * @method bool getUseLinkForAsLowAs()
 * @method bool getDisplayMinimalPrice()
 */
class View extends BasePriceBox
{
    /**
     * @var SalableResolverInterface
     */
    private $salableResolver;

    /**
     * @var MinimalPriceCalculatorInterface
     */
    private $minimalPriceCalculator;

    /**
     * @var Data
     */
    private $ZaverConfig;

    /**
     * @var AmountInterface
     */
    protected $amount;

    /**
     * @param Context $context
     * @param SaleableInterface $saleableItem
     * @param PriceInterface $price
     * @param RendererPool $rendererPool
     * @param array $data
     * @param SalableResolverInterface $salableResolver
     * @param MinimalPriceCalculatorInterface $minimalPriceCalculator
     */
    public function __construct(
        Context $context,
        AmountInterface $amount,
        SaleableInterface $saleableItem,
        PriceInterface $price,
        RendererPool $rendererPool,
        Data $ZaverConfig,
        array $data = [],
        SalableResolverInterface $salableResolver = null,
        MinimalPriceCalculatorInterface $minimalPriceCalculator = null,
    ) {
        parent::__construct($context, $saleableItem, $price, $rendererPool, $data);
        $this->salableResolver = $salableResolver ?: ObjectManager::getInstance()->get(SalableResolverInterface::class);
        $this->minimalPriceCalculator = $minimalPriceCalculator
            ?: ObjectManager::getInstance()->get(MinimalPriceCalculatorInterface::class);
            $this->config = $ZaverConfig;
    }

    /**
     * @inheritdoc
     */

        /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->salableResolver->isSalable($this->getSaleableItem())) {
            return '';
        }

        $result = parent::_toHtml();
        //Renders MSRP in case it is enabled
        if ($this->isMsrpPriceApplicable()) {
            /** @var BasePriceBox $msrpBlock */
            $msrpBlock = $this->rendererPool->createPriceRender(
                MsrpPrice::PRICE_CODE,
                $this->getSaleableItem(),
                [
                    'real_price_html' => $result,
                    'zone' => $this->getZone(),
                ]
            );
            $result = $msrpBlock->toHtml();
        }

        return $this->wrapResult($result);
    }

    /**
     * Check is MSRP applicable for the current product.
     *
     * @return bool
     */
    protected function isMsrpPriceApplicable()
    {
        try {
            /** @var MsrpPrice $msrpPriceType */
            $msrpPriceType = $this->getSaleableItem()->getPriceInfo()->getPrice('msrp_price');
        } catch (\InvalidArgumentException $e) {
            $this->_logger->critical($e);
            return false;
        }

        $product = $this->getSaleableItem();
        return $msrpPriceType->canApplyMsrp($product) && $msrpPriceType->isMinimalPriceLessMsrp($product);
    }

    /**
     * Wrap with standard required container
     *
     * @param string $html
     * @return string
     */
    protected function wrapResult($html)
    {
        return '<div class="price-box ' . $this->getData('css_classes') . '" ' .
            'data-role="priceBox" ' .
            'data-product-id="' . $this->getSaleableItem()->getId() . '" ' .
            'data-price-box="product-id-' . $this->getSaleableItem()->getId() . '"' .
            '>' . $html . '</div>';
    }

    /**
     * Render minimal amount
     *
     * @return string
     */
    public function renderAmountMinimal()
    {
        $id = $this->getPriceId() ? $this->getPriceId() : 'product-minimal-price-' . $this->getSaleableItem()->getId();

        $amount = $this->minimalPriceCalculator->getAmount($this->getSaleableItem());
        if ($amount === null) {
            return '';
        }

        return $this->renderAmount(
            $amount,
            [
                'display_label'     => __('As low as'),
                'price_id'          => $id,
                'include_container' => false,
                'skip_adjustments' => true
            ]
        );
    }

    /**
     * Define if the special price should be shown
     *
     * @return bool
     */
    public function hasSpecialPrice()
    {
        $displayRegularPrice = $this->getPriceType(Price\RegularPrice::PRICE_CODE)->getAmount()->getValue();
        $displayFinalPrice = $this->getPriceType(Price\FinalPrice::PRICE_CODE)->getAmount()->getValue();
        return $displayFinalPrice < $displayRegularPrice;
    }

    /**
     * Define if the minimal price should be shown
     *
     * @return bool
     */
    public function showMinimalPrice()
    {
        $minTierPrice = $this->minimalPriceCalculator->getValue($this->getSaleableItem());

        /** @var Price\FinalPrice $finalPrice */
        $finalPrice = $this->getPriceType(Price\FinalPrice::PRICE_CODE);
        $finalPriceValue = $finalPrice->getAmount()->getValue();

        return $this->getDisplayMinimalPrice()
            && $minTierPrice !== null
            && $minTierPrice < $finalPriceValue;
    }

    /**
     * Get Key for caching block content
     *
     * @return string
     */
    public function getCacheKey()
    {
        return parent::getCacheKey()
            . ($this->getData('list_category_page') ? '-list-category-page': '')
            . ($this->getSaleableItem()->getCustomerGroupId() ?? '');
    }

    /**
     * @inheritdoc
     */
    public function getCacheKeyInfo()
    {
        $cacheKeys = parent::getCacheKeyInfo();
        $cacheKeys['display_minimal_price'] = $this->getDisplayMinimalPrice();
        $cacheKeys['is_product_list'] = $this->isProductList();
        $cacheKeys['customer_group_id'] = $this->getSaleableItem()->getCustomerGroupId();
        $cacheKeys['zone'] = $this->getZone();
        return $cacheKeys;
    }

    /**
     * Get flag that price rendering should be done for the list of products.
     *
     * By default (if flag is not set) is false.
     *
     * @return bool
     */
    public function isProductList()
    {
        $isProductList = $this->getData('is_product_list');
        return $isProductList === true;
    }

    public function ZaverRate($ZV_Amount)
    {
        
        if (!$this->config->getZVApiKey()) { 
            return ''; 
            } 
                        
        $finalPrice = $this->getPriceType(Price\FinalPrice::PRICE_CODE);
        $finalPriceValue = $this->getPriceType(Price\FinalPrice::PRICE_CODE)->getAmount()->getValue();
 
        $ZV_Rate = new \Zaver\SDK\Checkout($this->config->getZVApiKey(), $this->config->getZVTestMode());

        if ($finalPriceValue > 180)
        {
        $ZaverRate = $ZV_Rate->getInstallmentRate($finalPriceValue);
        
        if (isset($ZaverRate['pricing']['24']))

        {
            $LowestZaverRate = $ZaverRate['pricing']['24'];
        }

        elseif (isset($ZaverRate['pricing']['18']))

        {
            $LowestZaverRate = $ZaverRate['pricing']['18'];
        }

        elseif (isset($ZaverRate['pricing']['12']))

        {
            $LowestZaverRate = $ZaverRate['pricing']['12'];
        }

        elseif (isset($ZaverRate['pricing']['9']))

        {
            $LowestZaverRate = $ZaverRate['pricing']['9'];    
        }

        elseif (isset($ZaverRate['pricing']['6']))

        {
            $LowestZaverRate = $ZaverRate['pricing']['6'];
        }

        elseif (isset($ZaverRate['pricing']['3']))
        {
            $LowestZaverRate = $ZaverRate['pricing']['3'];
        }
        else 
        {
            return;
        }

        if ($LowestZaverRate['currency'] = 'EUR')
            {
                $RateCurrency = '€';
            }

        $ZaverRateHTML = '<div style="font-size:10px;">';
        $ZaverRateHTML .= 'ab ' . $LowestZaverRate['monthlyAmount'] . ' ' . $RateCurrency . '/Mo. bei ' . $LowestZaverRate['numberOfInstallments'] . ' Raten'; 
        $ZaverRateHTML .= '</br>';
        $ZaverRateHTML .= '(eff. Zins. P.a. ' . ($LowestZaverRate['effectiveAnnualInterestRate']*100) . '%)';
        $ZaverRateHTML .= '</br>';
        $ZaverRateHTML .= '<a href="https://www.zaver.com/terms-de/ratenzahlung"> <img src="' . $ZaverRate["zaverAssets"]["logo-svg"] . '" width="45%" > </a>';
        $ZaverRateHTML .= '</br>';
        //$ZaverRateHTML .= 'Gesamtpreis ' . $LowestZaverRate['totalAmount'] . ' ' . $LowestZaverRate['currency'];
        $ZaverRateHTML .= '</div>';
        
        return $ZaverRateHTML;
        //return $this->config->getZVRate($price);
        }
            return ' ';
    }
}
