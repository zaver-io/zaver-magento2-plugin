<?php

namespace Zaver\Payment\Model;

use Zaver\SDK\Object\PaymentMethodsRequest;

/**
 * The Zaver payment methods offered for a cart, with their texts, as the Zaver API returns them. The checkout offers
 * them as the choices of the Zaver payment method (zaver_checkout); a merchant with several variants of a method
 * (e.g. Pay later in 14, 30 or 90 days) gets one choice per variant.
 *
 * The market is the country of the billing address. Zaver returns the methods for that market, currency and amount;
 * their texts come from the localization for the market, e.g. "de-DE" for DE. Before a billing address is entered
 * there is no market: Zaver is asked by currency (and amount) only, and the English localizations are used.
 */
class PaymentMethods
{
  /**
   * @var \Zaver\Payment\Helper\Data
   */
  private $helper;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  private $logger;

  /**
   * API responses of this request, by market, currency and amount
   *
   * @var array
   */
  private $offered = [];

  public function __construct(\Zaver\Payment\Helper\Data $helper,
                              \Psr\Log\LoggerInterface $logger) {
    $this->helper = $helper;
    $this->logger = $logger;
  }

  /**
   * The Zaver market (ISO 3166-1 country code) of a cart or an order: the country of the billing address, null
   * before one is entered. The shipping address is never used.
   *
   * @param \Magento\Quote\Model\Quote|\Magento\Sales\Model\Order $quoteOrOrder
   * @return string|null
   */
  public function getMarket($quoteOrOrder) {
    $billingAddress = $quoteOrOrder->getBillingAddress();

    if ($billingAddress && $billingAddress->getCountryId()) {
      return strtoupper($billingAddress->getCountryId());
    }

    return null;
  }

  /**
   * The Zaver payment methods offered for a cart, in the order Zaver returns them.
   *
   * @param \Magento\Quote\Model\Quote $quote
   * @return array[] ['method' => 'PAY_LATER', 'variant' => '30_DAYS' or '', 'title' => ..., 'description' => ...,
   *                 'logo' => ...]
   */
  public function getOffered($quote) {
    $market = $this->getMarket($quote);
    $currency = (string)($quote->getQuoteCurrencyCode() ?: $quote->getStore()->getCurrentCurrencyCode());
    $amount = (float)$quote->getGrandTotal();
    $key = (string)$market . '|' . $currency . '|' . $amount;

    if (!isset($this->offered[$key])) {
      $this->offered[$key] = $this->requestOffered($market, $currency, $amount);
    }

    return $this->offered[$key];
  }

  /**
   * @param string|null $market
   * @param string $currency
   * @param float $amount
   * @return array
   */
  private function requestOffered($market, $currency, $amount) {
    $offered = [];

    try {
      $request = PaymentMethodsRequest::create()
        ->setCurrency($currency);

      if ($market !== null) {
        $request->setMarket($market);
      }

      // Before the totals are collected the amount is 0, which Zaver would reject.
      if ($amount > 0) {
        $request->setAmount($amount);
      }

      $checkout = new \Zaver\SDK\Checkout($this->helper->getZVApiKey(), (bool)$this->helper->getZVTestMode());
      $response = $checkout->getPaymentMethods($request);

      foreach ($response["paymentMethods"] ?? [] as $method) {
        if (empty($method["paymentMethod"])) {
          continue;
        }

        $offered[] = [
            'method' => (string)$method["paymentMethod"],
            'variant' => (string)($method["paymentMethodVariant"] ?? '')
          ] + $this->getTexts($method, $market);
      }
    }
    catch (\Exception $e) {
      // Zaver answers markets it does not serve with an empty list or an error; offer no Zaver method then.
      $this->logger->warning('Zaver payment methods could not be loaded', [
        'market' => $market, 'currency' => $currency, 'error' => $e->getMessage()
      ]);
    }

    return $offered;
  }

  /**
   * Title, description and logo of a payment method in the localization of the market: the localization whose
   * region is the market (preferring the store's language), else one in the store's language, else the first
   * one. Without a market the English localizations are used. Missing texts fall back to the method's own texts.
   *
   * @param array $method
   * @param string|null $market
   * @return array
   */
  private function getTexts(array $method, $market) {
    $localizations = $method["localizations"] ?? [];
    $language = strtolower((string)$this->helper->getLocale());
    $chosen = null;

    $patterns = ($market === null)
      ? ['en-*']
      : [$language . '-' . $market, '*-' . $market, $language . '-*'];

    foreach ($patterns as $pattern) {
      foreach ($localizations as $tag => $localization) {
        if (fnmatch($pattern, (string)$tag, FNM_CASEFOLD)) {
          $chosen = $localization;
          break 2;
        }
      }
    }

    if ($chosen === null && !empty($localizations)) {
      $chosen = reset($localizations);
    }

    $chosen = (array)$chosen;

    return [
      'title' => (string)($chosen["title"] ?? $method["title"] ?? ''),
      'description' => (string)($chosen["description"] ?? $method["description"] ?? ''),
      'logo' => (string)($chosen["iconSvgSrc"] ?? $method["iconSvgSrc"] ?? '')
    ];
  }
}
