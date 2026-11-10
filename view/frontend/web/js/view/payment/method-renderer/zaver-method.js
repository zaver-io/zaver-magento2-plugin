define(
    [
        'jquery',
        'ko',
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
        'Magento_Checkout/js/model/payment/method-list',
        'mage/translate'
    ],
    function ($,
              ko,
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
              url,
              methodList,
              $t) {
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

            /**
             * The Zaver payment method with the Zaver payment methods the Zaver API offers for the cart as choices
             * (title, description and icon in the localization of the billing country's market). The choices the
             * checkout page was loaded with are reloaded whenever Magento refreshes the payment methods after an
             * address change, as the market follows the billing country.
             */
            initialize: function () {
                this._super();
                this.options = ko.observableArray([]);
                this.selectedKey = ko.observable('');
                this.setOptions(this.getConfiguredOptions());
                this.loadOptions();
                methodList.subscribe(this.loadOptions.bind(this));

                return this;
            },

            getConfiguredOptions: function () {
                var config = window.checkoutConfig.payment.zaver || {};

                return config.methods || [];
            },

            loadOptions: function () {
                var self = this,
                    config = window.checkoutConfig.payment.zaver || {};

                if (!config.methodsUrl) {
                    return;
                }

                $.getJSON(config.methodsUrl).done(function (response) {
                    self.setOptions(((response || {}).methods) || []);
                });
            },

            /**
             * Keeps the customer's choice while it is still offered; a single choice is preselected.
             */
            setOptions: function (options) {
                var self = this,
                    stillOffered = options.some(function (option) {
                        return self.getOptionKey(option) === self.selectedKey();
                    });

                this.options(options);

                if (!stillOffered) {
                    this.selectedKey(options.length === 1 ? this.getOptionKey(options[0]) : '');
                }
            },

            getOptionKey: function (option) {
                return option.method + '|' + (option.variant || '');
            },

            getSelectedOption: function () {
                var self = this;

                return this.options().filter(function (option) {
                    return self.getOptionKey(option) === self.selectedKey();
                })[0] || null;
            },

            selectOption: function (option) {
                this.selectedKey(this.getOptionKey(option));
                this.selectPaymentMethod();

                return true;
            },

            getData: function () {
                var option = this.getSelectedOption();

                return {
                    method: this.getCode(),
                    'additional_data': option ? {
                        'zaver_payment_method': option.method,
                        'zaver_payment_method_variant': option.variant || '',
                        'zaver_payment_method_title': option.title
                    } : null
                };
            },

            validate: function () {
                if (!this.getSelectedOption()) {
                    this.messageContainer.addErrorMessage({message: $t('Please choose a Zaver payment method.')});

                    return false;
                }

                return this._super();
            }

        });
    }
);
