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
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if user is from Contestio
        $checkUser = $this->apiHelper->callApi($userAgent, 'v1/users/final/me', "GET");

        if (!$checkUser) {
            return;
        }

        $orderData = [
            'order_id' => $order->getIncrementId(),
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
        ];

        // Send order to Contestio
        $this->apiHelper->callApi($userAgent, 'v1/users/final/new-order', "POST", $orderData);
    }
}
