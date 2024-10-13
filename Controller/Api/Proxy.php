<?php
namespace Contestio\Connect\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Contestio\Connect\Helper\Data as ApiHelper;

class Proxy extends Action
{
    protected $resultJsonFactory;
    protected $apiHelper;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ApiHelper $apiHelper
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->apiHelper = $apiHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $endpoint = $this->getRequest()->getParam('endpoint');
        $method = $this->getRequest()->getMethod();
        $data = json_decode($this->getRequest()->getContent(), true);
        $userAgent = $this->getRequest()->getHeader('User-Agent');

        try {
            $response = $this->apiHelper->callApi($userAgent, $endpoint, $method, $data);
            return $resultJson->setData($response);
        } catch (\Exception $e) {
            return $resultJson->setData(['error' => $e->getMessage()])->setHttpResponseCode(500);
        }
    }
}