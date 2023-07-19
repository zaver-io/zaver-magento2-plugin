define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
    ],
    function ($,
              quote,
              urlBuilder,
              storage,
              customerData,
              Component,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              checkoutData,
              additionalValidators,
              url) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Zaver_Payment/payment/method/checkout/form'
            },

            placeOrder: function (data, event) {


                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(this.afterPlaceOrder.bind(this));
                    return true;
                }
                return false;
            },

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function () {
                window.location.replace(url.build('zaver/checkout/index'));
            },

            getCode: function () {
                return 'zaver_installments';
            },

            getLogo: function () {
                return window.checkoutConfig.payment.zaver.installmentsLogoSrc;
            },

            getDescription: function () {
                return window.checkoutConfig.payment.zaver.installmentsDesc;
            },

            getTitle: function () {
                return window.checkoutConfig.payment.zaver.installmentsTitle;
            }

        });
    }
);
