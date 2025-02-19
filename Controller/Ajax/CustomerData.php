<?php
namespace Contestio\Connect\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Contestio\Connect\Helper\Data as ApiHelper;

class CustomerData extends Action
{
    protected $jsonFactory;
    protected $customerSession;
    protected $apiHelper;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        ApiHelper $apiHelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->customerSession = $customerSession;
        $this->apiHelper = $apiHelper;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $customer = $this->customerSession->getCustomer();
        $accessToken = $this->scopeConfig->getValue('contestio_connect/api_settings/access_token');

        if ($customer->getId()) {
            return $result->setData([
                'customer_id' => $this->apiHelper->encryptDataBase64($customer->getId(), $accessToken),
                'customer_email' => $this->apiHelper->encryptDataBase64($customer->getEmail(), $accessToken)
            ]);
        }

        return $result->setData([]);
    }
}
