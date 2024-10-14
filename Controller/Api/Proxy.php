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

        $method = $this->getRequest()->getMethod();
        $endpoint = $this->getRequest()->getParam('endpoint');
        $userAgent = $this->getRequest()->getHeader('User-Agent');
        $data = [];

        // Update data if post content and no files
        if ($this->getRequest()->getContent()) {
            $data = json_decode($this->getRequest()->getContent(), true);
        }       

        try {
            // Call API
            if ($this->getRequest()->getFiles() && strpos($endpoint, 'image') !== false) {
                $response = $this->handleImageUpload($userAgent, $endpoint);
            } else {
                $response = $this->apiHelper->callApi($userAgent, $endpoint, $method, $data);
            }
            
            // Return response
            return $resultJson->setData($response);
        } catch (\Exception $e) {
            return $resultJson->setData(['error' => $e->getMessage()])->setHttpResponseCode(500);
        }
    }


    private function handleImageUpload($userAgent, $endpoint)
    {
        $files = $this->getRequest()->getFiles() ?? null;

        if (!$files || !isset($files['file']) || empty($files['file']['tmp_name'])) {
            return ['error' => "Aucun fichier n'a été téléchargé"];
        }

        try {
            $response = $this->apiHelper->uploadImage($userAgent, $endpoint, $files['file']);
            return $response;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}