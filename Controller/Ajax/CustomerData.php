<?php
namespace Contestio\Connect\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Contestio\Connect\Helper\Data as ApiHelper;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class CustomerData extends Action implements CsrfAwareActionInterface
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
        
        // Handle OPTIONS request (CORS preflight)
        if ($this->getRequest()->getMethod() === 'OPTIONS') {
            $this->getResponse()->setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $this->getResponse()->setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');
            return $result->setHttpResponseCode(200);
        }
        
        // Ensure the method is POST
        if ($this->getRequest()->getMethod() !== 'POST') {
            return $result->setData([
                'success' => false,
                'message' => __('Method not allowed.')
            ])->setHttpResponseCode(405);
        }
        
        // Headers spécifiques pour Safari iOS
        $this->getResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->getResponse()->setHeader('Pragma', 'no-cache');
        $this->getResponse()->setHeader('Expires', '0');
        
        $userAgent = $this->getRequest()->getHeader('User-Agent');
        $isSafariIOS = strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false;
        
        // Vérification de la session
        $customer = $this->customerSession->getCustomer();
        $isLoggedIn = $this->customerSession->isLoggedIn();
        $customerId = $customer ? $customer->getId() : null;
        $customerEmail = $customer ? $customer->getEmail() : null;
        
        // Vérification multiple pour Safari iOS
        if ($isSafariIOS && $isLoggedIn && $customerId) {
            if (!$customerEmail) {
                // Recharger le customer depuis la DB si l'email est manquant
                $customerModel = $this->customerSession->getCustomerDataObject();
                if ($customerModel) {
                    $customerEmail = $customerModel->getEmail();
                    error_log('Contestio - Reloaded email from CustomerDataObject: ' . $customerEmail);
                }
            }
        }
        
        $accessToken = $this->scopeConfig->getValue('contestio_connect/api_settings/access_token');
        
        if ($isLoggedIn && $customerId && $customerEmail && $accessToken) {
            $responseData = [
                'customer_id' => $this->apiHelper->encryptDataBase64($customerId, $accessToken),
                'customer_email' => $this->apiHelper->encryptDataBase64($customerEmail, $accessToken)
            ];            
            return $result->setData($responseData);
        }
        return $result->setData([]);
    }
    
    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}