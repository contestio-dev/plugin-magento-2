<?php
namespace Contestio\Connect\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Contestio\Connect\Helper\Data as ApiHelper;

class ContestioObserver implements ObserverInterface
{
    protected $apiHelper;

    public function __construct(ApiHelper $apiHelper)
    {
        $this->apiHelper = $apiHelper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
    
        if ($order && $order->getId()) {
            $this->notifyApi($order);
        }
    }

    private function notifyApi($order)
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Get customer data from order
            $customerId = $order->getCustomerId();
            $customerEmail = $order->getCustomerEmail();
            
            // Get user id and check if we store order
            $checkUser = $this->apiHelper->callApi($userAgent, 'v1/users/me', "GET", null, $customerId, $customerEmail);

            // If storeOrder === true, send order to Contestio
            if ($checkUser && isset($checkUser['storeOrder']) && $checkUser['storeOrder'] === true) {
                $orderData = array(
                    'order_id' => $order->getIncrementId(),
                    'amount' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                );

                // Send order to Contestio
                $this->apiHelper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData, $customerId, $customerEmail);
            }
        } catch (\Exception $e) {
            return false;
        }
    }
}
