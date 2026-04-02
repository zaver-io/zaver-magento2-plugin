<?php

 namespace Zaver\Payment\Helper\EmailStopper\Order;

 use Magento\Sales\Model\Order;

class Email implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getData('order');

        $this->stopNewOrderEmail($order);
        

}

public function stopNewOrderEmail(\Magento\Sales\Model\Order $order){
    $order->setCanSendNewEmailFlag(false);
    $order->setSendEmail(false);
    try{
        $order->save();
    }
    catch (\ErrorException $ee){

    }
    catch (\Exception $ex)
    {

    }
    catch (\Error $error){

    }
}
} 