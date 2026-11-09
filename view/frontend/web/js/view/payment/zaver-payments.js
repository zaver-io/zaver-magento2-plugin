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
                type: 'zaver_checkout',
                component: 'Zaver_Payment/js/view/payment/method-renderer/zaver-method'
            }

        );

        /** Add view logic here if needed */
        return Component.extend({});
    }
);
