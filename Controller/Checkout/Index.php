<?php

namespace Zaver\Payment\Controller\Checkout;

use Zaver\SDK\Checkout;
use Zaver\SDK\Object\MerchantUrls;
use Zaver\SDK\Object\PaymentCreationRequest;
use Zaver\SDK\Object\LineItem;
use Zaver\SDK\Config\ItemType;
use Zaver\SDK\Object\PayerData;
use Zaver\SDK\Config\PaymentStatus;
use Zaver\SDK\Object\Address;

/**
 * Front Controller for Checkout Method
 * it does a redirect to checkout
 * Class Index
 * @package Zaver\Payment\Controller\Checkout
 */
class Index extends \Zaver\Payment\Controller\AbstractCheckoutAction
{
  const TYP_DOWNLOAD = "DOWNLOADABLE";
  const TYP_VIRTUAL = "VIRTUAL";

  /**
   * Redirect to checkout
   *
   * @return void
   */
  public function execute() {

    $order = $this->getOrder();
    $helper = $this->getHelper();

    if (isset($order)) {
      try {
        // Sends the request to Zaver.
        $zaverApiKey = $helper->getZVApiKey();
        $zaverTestMode = $helper->getZVTestMode();
        $languageCode = $helper->getLocale();
        $logger = $this->getLogger();

        $api = new \Zaver\SDK\Checkout($zaverApiKey, $zaverTestMode);

        $aZaverItems = array();

        $roundPow = pow(10, 0);

        $paymentMethodCode = $order->getPayment()->getMethod();
        $paymentMethodTitle = $order->getPayment()->getMethodInstance()->getTitle();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $orderId = $order->getIncrementId();

        $urlSuccess = $helper->getSuccessUrl($orderId);
        $urlCancel = $helper->getCancelUrl($orderId);
        $urlCallback = $helper->getCallbackUrl($orderId);

        $amount = $order->getGrandTotal() * $roundPow;
        $amount = round($amount, 2);

        $shippingName = $order->getShippingDescription();
        $shippingVatRate = 1;
        $shippingVatAmount = 1;
        $shippingAmountTax = $order->getBaseShippingInclTax() * $roundPow;

        if ($shippingAmountTax > 0) {
          $shippingAmount = $order->getBaseShippingInclTax() * $roundPow;
        }
        else {
          $shippingAmount = $order->getBaseShippingAmount() * $roundPow;
        }

        $shippingAmount = round($shippingAmount, 2);

        $items = [];
        foreach ($order->getItems() as $item) {

          if (strtoupper($item->getProductType()) == self::TYP_DOWNLOAD ||
            strtoupper($item->getProductType()) == self::TYP_VIRTUAL
          ) {
            $itemType = \Zaver\SDK\Config\ItemType::DIGITAL;
          }
          else {
            $itemType = \Zaver\SDK\Config\ItemType::PHYSICAL;
          }

          // Is child: product and atributte: Jeans Red
          if (!empty($item->getParentItemId())) {
            $parentItem = $item->getParentItem();
            $itemId = $parentItem->getItemId();
            $productPriceTax = $parentItem->getBasePriceInclTax() * $roundPow;

            if ($productPriceTax > 0) {
              $productPrice = $parentItem->getBasePriceInclTax() * $roundPow;
            }
            else {
              $productPrice = $parentItem->getBasePrice() * $roundPow;
            }

            $iPrice = round($productPrice, 2);
            $iQty = (int)$item->getQtyOrdered();
            $iPriceTax = $parentItem->getTaxAmount() * $roundPow;
            $iVatRate = $parentItem->getTaxPercent();

            $item = \Zaver\SDK\Object\LineItem::create()
              ->setName($item->getName())
              ->setMerchantReference($item->getSku())
              ->setQuantity($iQty)
              ->setUnitPrice($iPrice)
              ->setTotalAmount($iQty * $iPrice)
              ->setTaxRatePercent($iVatRate)
              ->setTaxAmount($iPriceTax)
              ->setItemType($itemType)
              ->setMerchantMetadata(array("itemId" => $itemId));

            $aZaverItems[$itemId] = $item;

            // Remove parent from the array
            if (isset($items[$parentItem->getItemId()])) {
              unset($items[$parentItem->getItemId()]);
            }
          }
          else {
            $itemId = $item->getItemId();
            $productPriceTax = $item->getBasePriceInclTax() * $roundPow;

            if ($productPriceTax > 0) {
              $productPrice = $item->getBasePriceInclTax() * $roundPow;
            }
            else {
              $productPrice = $item->getBasePrice() * $roundPow;
            }

            $productPrice = round($productPrice, 2);
            $iPrice = round($productPrice, 2);
            $iQty = (int)$item->getQtyOrdered();
            $iPriceTax = $item->getTaxAmount() * $roundPow;
            $iVatRate = $item->getTaxPercent();

            $item = \Zaver\SDK\Object\LineItem::create()
              ->setName($item->getName())
              ->setMerchantReference($item->getSku())
              ->setQuantity($iQty)
              ->setUnitPrice($iPrice)
              ->setTotalAmount($iQty * $iPrice)
              ->setTaxRatePercent($iVatRate)
              ->setTaxAmount($iPriceTax)
              ->setItemType($itemType)
              ->setMerchantMetadata(array("itemId" => $item->getItemId()));

            $aZaverItems[$itemId] = $item;
          }
        }

        $shipping = \Zaver\SDK\Object\LineItem::create()
          ->setName($shippingName)
          ->setMerchantReference(ItemType::SHIPPING)
          ->setQuantity(1)
          ->setUnitPrice($shippingAmount)
          ->setTotalAmount($shippingAmount)
          ->setTaxRatePercent($shippingVatRate)
          ->setTaxAmount($shippingVatAmount)
          ->setItemType(\Zaver\SDK\Config\ItemType::SHIPPING);

        $urls = \Zaver\SDK\Object\MerchantUrls::create()
          ->setSuccessUrl($urlSuccess)
          ->setCancelUrl($urlCancel)
          ->setCallbackUrl($urlCallback);

        $payer = \Zaver\SDK\Object\PayerData::create()
          ->setEmail($order->getCustomerEmail());


        if (!empty($order->getBillingAddress())) {
          $strAddress1 = $order->getBillingAddress()->getStreetLine(1);
          $strAddress2 = "";
          if (!empty($order->getBillingAddress()->getStreetLine(2))) {
            $strAddress2 = " " . $order->getBillingAddress()->getStreetLine(2);
          }

          $strPostcode = $order->getBillingAddress()->getPostcode();
          $strCity = $order->getBillingAddress()->getCity();
          $strCountry = $order->getBillingAddress()->getCountryId();

          $billAdrName = $order->getBillingAddress()->getFirstname() . " " . $order->getBillingAddress()->getLastname();

          $billAdress = \Zaver\SDK\Object\Address::create()
            ->setName($billAdrName)
            ->setPostalCode($strPostcode)
            ->setStreetName($strAddress1)
            ->setHouseNumber($strAddress2)
            ->setCity($strCity)
            ->setCountry($strCountry);

          $payer->setBillingAddress($billAdress);
        }

        if (!empty($order->getShippingAddress())) {
          $shipAddressAddress1 = $order->getShippingAddress()->getStreetLine(1);
          $shipAddressAddress2 = "";

          if (!empty($order->getShippingAddress()->getStreetLine(2))) {
            $shipAddressAddress2 = " " . $order->getShippingAddress()->getStreetLine(2);
          }

          $shipAddressPostcode = $order->getShippingAddress()->getPostcode();
          $shipAddressCity = $order->getShippingAddress()->getCity();
          $shipAddressCountry = $order->getShippingAddress()->getCountryId();

          $shippAdrName = $order->getShippingAddress()->getFirstname() . " " . $order->getShippingAddress()->getLastname();

          $shippAdress = \Zaver\SDK\Object\Address::create()
            ->setName($shippAdrName)
            ->setPostalCode($shipAddressPostcode)
            ->setStreetName($shipAddressAddress1)
            ->setHouseNumber($shipAddressAddress2)
            ->setCity($shipAddressCity)
            ->setCountry($shipAddressCountry);

          $payer->setShippingAddress($shippAdress);
        }

        $paymentTitle = "Order #" . $orderId;

        $request = \Zaver\SDK\Object\PaymentCreationRequest::create()
          ->setMerchantPaymentReference($orderId)
          ->setAmount($amount)
          ->setCurrency($orderCurrencyCode)
          ->setMarket('DE')
          ->setTitle($paymentTitle)
          ->setMerchantUrls($urls)
          ->setPayerData($payer)
          ->addLineItem($shipping);

        if (!empty($aZaverItems)) {
          foreach ($aZaverItems as $oItem) {
            $request->addLineItem($oItem);
          }
        }

        $payment = $api->createPayment($request);

        if ($payment->getPaymentStatus() == \Zaver\SDK\Config\PaymentStatus::CREATED) {
          $strUrlRedirect = $payment->getPaymentLink();
          $paymentsData = $payment->getSpecificPaymentMethodData();
          $paymentSel = $helper->getZaverMethodCode($paymentMethodCode);
          $paymentZvId = $payment->getPaymentId();

          foreach ($paymentsData as $oPayment) {
            if ($paymentSel == $oPayment["paymentMethod"]) {
              $strUrlRedirect = $oPayment["paymentLink"];
            }
          }

          // Add the zaver payment id
          $order->setData('zaver_payment_id', $paymentZvId);

          $aPaymentZvItems = (array)$payment->getLineItems();
          $strParKey = "";
          $targetKey = null;

          // For shipping
          foreach ($aPaymentZvItems as $keyZv => $item) {
            $targetKey = ($item['merchantReference'] === ItemType::SHIPPING) ? $keyZv : $targetKey;
          }

          if ($targetKey !== null) {
            $strParKey .= ItemType::SHIPPING . ":" . $aPaymentZvItems[$targetKey]["id"] . ";";
          }


          if (!empty($aZaverItems)) {
            foreach ($aZaverItems as $key => $oItem) {
              $itemSku = $oItem["merchantReference"];
              $targetKey = null;

              foreach ($aPaymentZvItems as $keyZv => $item) {
                $targetKey = ($item['merchantReference'] === $itemSku) ? $keyZv : $targetKey;
              }

              if ($targetKey !== null) {
                $strParKey .= "$key:" . $aPaymentZvItems[$targetKey]["id"] . ";";
              }
            }
          }

          $order->setData('zaver_order_items_id', $strParKey);

          $paymentData = array("id" => $paymentZvId, "authorizeAmount" => $payment->getAmount(),
            "paymentStatus" => $payment->getPaymentStatus());
          $this->createTransaction($order, $paymentData, false);

          // Change the order status
          $order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
            ->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
            ->addStatusToHistory(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, "Order waiting for payment");
          $order->save();
        }

        if (isset($strUrlRedirect)) {
          $this->getResponse()->setRedirect($strUrlRedirect);
        }
        else {
          $this->redirectToCheckoutFragmentPayment();
        }
      }
      catch (Exception $e) {
        $this->redirectToCheckoutFragmentPayment();
      }
    }
  }

  public function createTransaction($order = null, $paymentData = array(), $bTranClosed = false) {
    try {
      // Get payment object from order object
      $payment = $order->getPayment();
      $payment->setLastTransId($paymentData['id']);
      $payment->setTransactionId($paymentData['id']);
      $payment->setIsTransactionClosed($bTranClosed);
      $payment->setAdditionalInformation(
        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
      );
      $formatedPrice = $order->getBaseCurrency()->formatTxt(
        $paymentData["authorizeAmount"]
      );

      $paymentData["authorizeAmount"] = $formatedPrice;

      $message = __('The authorized amount is %1.', $formatedPrice);
      // Get the object of builder class
      $trans = $this->getTransactionBuilder();
      $transaction = $trans->setPayment($payment)
        ->setOrder($order)
        ->setTransactionId($paymentData['id'])
        ->setAdditionalInformation(
          [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array)$paymentData]
        )
        ->setFailSafe(true)
        // Build method creates the transaction and returns the object
        ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

      $payment->addTransactionCommentsToOrder(
        $transaction,
        $message
      );
      $payment->setParentTransactionId(null);
      $payment->save();
      $order->save();
      return $transaction->save()->getTransactionId();
    }
    catch (Exception $e) {
    }
  }
}