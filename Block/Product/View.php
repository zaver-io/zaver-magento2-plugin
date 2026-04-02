<?php
namespace Zaver\Payment\Catalog\Block\Product;
use Magento\Catalog\Block\Product\View as ProductView;
class View
{
    private $displayBlocks = ['product.info.price'];
    // you can add layout references as per your need to display like: product.info.price, product.info.review, etc.
    public function afterToHtml(ProductView $subject, $html)
    {
        if (in_array($subject->getNameInLayout(), $this->displayBlocks)) {
            return $html . '<div class="custom-text">Test 1</div>';
        }
        return $html;
    }
}