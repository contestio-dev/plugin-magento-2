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
        
        // ✅ CORRECTION 1: Headers spécifiques pour Safari iOS
        $this->getResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->getResponse()->setHeader('Pragma', 'no-cache');
        $this->getResponse()->setHeader('Expires', '0');
        
        // ✅ CORRECTION 2: Debug logging
        $userAgent = $this->getRequest()->getHeader('User-Agent');
        $isSafariIOS = strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false;
        
        if ($isSafariIOS) {
            error_log('Contestio - Safari iOS detected: ' . $userAgent);
        }
        
        // ✅ CORRECTION 3: Vérification robuste de la session
        $customer = $this->customerSession->getCustomer();
        $isLoggedIn = $this->customerSession->isLoggedIn();
        $customerId = $customer ? $customer->getId() : null;
        $customerEmail = $customer ? $customer->getEmail() : null;
        
        // Debug logging
        error_log('Contestio - Session state: ' . json_encode([
            'isLoggedIn' => $isLoggedIn,
            'customerId' => $customerId,
            'customerEmail' => $customerEmail,
            'sessionId' => $this->customerSession->getSessionId(),
            'userAgent' => substr($userAgent, 0, 50) . '...'
        ]));
        
        // ✅ CORRECTION 4: Vérification multiple pour Safari iOS
        if ($isSafariIOS && $isLoggedIn && $customerId) {
            // Pour Safari iOS, double vérification avec un délai si nécessaire
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
        
        // ✅ CORRECTION 5: Validation plus stricte
        if ($isLoggedIn && $customerId && $customerEmail && $accessToken) {
            $responseData = [
                'customer_id' => $this->apiHelper->encryptDataBase64($customerId, $accessToken),
                'customer_email' => $this->apiHelper->encryptDataBase64($customerEmail, $accessToken)
            ];
            
            error_log('Contestio - Returning encrypted data for customer: ' . $customerId);
            
            return $result->setData($responseData);
        }
        
        // ✅ CORRECTION 6: Debug pour comprendre pourquoi pas de données
        $debugInfo = [
            'isLoggedIn' => $isLoggedIn,
            'hasCustomerId' => !empty($customerId),
            'hasCustomerEmail' => !empty($customerEmail),
            'hasAccessToken' => !empty($accessToken),
            'isSafariIOS' => $isSafariIOS
        ];
        
        error_log('Contestio - Returning empty data. Debug: ' . json_encode($debugInfo));
        
        return $result->setData([]);
    }
}