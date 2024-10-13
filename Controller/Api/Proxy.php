<?php
namespace Contestio\Connect\Controller\Api;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http; 

class Proxy extends Action implements HttpPostActionInterface
{
    protected $resultJsonFactory;

    public function __construct(Context $context, JsonFactory $resultJsonFactory)
    {
        $this->resultJsonFactory = $resultJsonFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $method = $this->getRequest()->getMethod();
        
        $data = [];
        $data = $this->getRequest()->getPostValue();
        // if ($method === 'POST') {
        // } elseif ($method === 'GET') {
        //     $data = $this->getRequest()->getParams();
        // }

        return $result->setData([
            'success' => true,
            'message' => $method,
            'data' => $data
        ]);
    }
}
