define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';

        rendererList.push(

            {
                type: 'zaver_paylater',
                component: 'Zaver_Payment/js/view/payment/method-renderer/paylater-method'
            },
            {
                type: 'zaver_installments',
                component: 'Zaver_Payment/js/view/payment/method-renderer/installments-method'
            }

        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
