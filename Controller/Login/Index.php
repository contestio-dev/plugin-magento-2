<?php

namespace Contestio\Connect\Controller\Login;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Index extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $accountManagement;
    protected $cookieManager;
    protected $cookieMetadataFactory;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Session $customerSession,
        AccountManagementInterface $accountManagement,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->accountManagement = $accountManagement;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        // Headers CORS spécifiques au domaine
        $origin = $this->getRequest()->getHeader('Origin');
        $allowedOrigins = [
            'https://plugin.contestio.fr',
            'https://plugin.preprod.contestio.fr',
            'https://plugin.staging.contestio.fr',
            'https://app.contestio.fr',
            'https://app.preprod.contestio.fr',
            'https://app.staging.contestio.fr',
            'https://www.chemin-des-poulaillers.com',
            'https://chemin-des-poulaillers.com',
            'https://www.cabesto.com',
            'https://cabesto.com',
            'https://www.bysmaquillage.fr',
            'https://bysmaquillage.fr',
            'https://www.toptir.fr',
            'https://toptir.fr',
            'https://contestiotestshopi.myshopify.com',
            'https://pa82nm-9i.myshopify.com',
            'https://0jatjy-07.myshopify.com',
            'https://demo-1.contestio.fr',
            'https://demo-2.contestio.fr',
            'https://demo-3.contestio.fr',
            'https://demo-4.contestio.fr',
            'https://magento.contestio.fr',
            'https://beta.magento2.contestio.fr',
            'http://beta.magento1.contestio.fr',
            'https://contestio-beta-shopi.myshopify.com',
            'https://preprod.contestio.fr',
            'http://localhost:3000',
            'http://localhost:3001',
            'http://localhost:3002',        
        ];
        
        if (in_array($origin, $allowedOrigins)) {
            $resultJson->setHeader('Access-Control-Allow-Origin', $origin);
        }
        
        $resultJson->setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $resultJson->setHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization');
        $resultJson->setHeader('Access-Control-Allow-Credentials', 'true'); // Important pour Safari iOS
        
        // Headers anti-cache pour Safari iOS
        $resultJson->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $resultJson->setHeader('Pragma', 'no-cache');
        $resultJson->setHeader('Expires', '0');
        
        // Gérer la requête OPTIONS (preflight)
        if ($this->getRequest()->getMethod() === 'OPTIONS') {
            return $resultJson->setHttpResponseCode(200);
        }

        // Get method
        $method = $this->getRequest()->getMethod();

        // Ensure the method is POST
        if ($method !== 'POST') {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Method not allowed.')
            ])->setHttpResponseCode(405);
        }

        // Détection Safari iOS
        $userAgent = $this->getRequest()->getHeader('User-Agent');
        $isSafariIOS = strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false;

        // Get POST data as JSON and decode it
        $content = $this->getRequest()->getContent();
        $data = json_decode($content, true);

        // Check if the JSON decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid JSON data')
            ])->setHttpResponseCode(400);
        }

        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        try {
            $customer = $this->accountManagement->authenticate($username, $password);
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            
            // Forcer la régénération de l'ID de session pour Safari iOS
            if ($isSafariIOS) {
                $this->customerSession->regenerateId();
                error_log('Contestio Login - Session regenerated for Safari iOS');
            }
            
            // Cookies explicites pour Safari iOS
            if ($isSafariIOS) {
                $this->setSafariIOSCookies($customer);
            }
            
            // Délai pour Safari iOS avant de confirmer le login
            $responseData = [
                'success' => true,
                'message' => __('Login successful.'),
                'safari_ios' => $isSafariIOS,
                'customer_id' => $customer->getId(),
                'session_id' => $this->customerSession->getSessionId()
            ];
            
            if ($isSafariIOS) {
                $responseData['delay_recommended'] = 500; // ms
            }
            
            $requestHeaders = $this->getRequest()->getHeaders()->toArray();
            return $resultJson->setData($responseData);
            
        } catch (EmailNotConfirmedException $e) {
            error_log('Contestio Login - Email not confirmed: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('This account is not confirmed. Please check your email for confirmation link.')
            ])->setHttpResponseCode(401);
        } catch (AuthenticationException $e) {
            error_log('Contestio Login - Authentication failed: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid login or password.')
            ])->setHttpResponseCode(401);
        } catch (\Exception $e) {
            error_log('Contestio Login - Unexpected error: ' . $e->getMessage());
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred during login. Please try again later.')
            ])->setHttpResponseCode(500);
        }
    }
    
    /**
     * Méthode spécifique pour Safari iOS cookies
     */
    private function setSafariIOSCookies($customer)
    {
        try {
            // Cookie de confirmation login pour Safari iOS
            $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDurationOneYear()
                ->setPath('/')
                ->setDomain('.contestio.fr')
                ->setSecure(true)
                ->setHttpOnly(false) // Accessible via JavaScript
                ->setSameSite('None'); // Important pour iframe Safari iOS
            
            $this->cookieManager->setPublicCookie(
                'contestio_login_confirmed',
                'true',
                $cookieMetadata
            );
            
            // Cookie avec customer ID pour verification
            $cookieMetadata2 = $this->cookieMetadataFactory->createPublicCookieMetadata()
                ->setDurationOneYear()
                ->setPath('/')
                ->setDomain('.contestio.fr')
                ->setSecure(true)
                ->setHttpOnly(false)
                ->setSameSite('None');
            
            $this->cookieManager->setPublicCookie(
                'contestio_customer_id',
                $customer->getId(),
                $cookieMetadata2
            );            
        } catch (\Exception $e) {
            error_log('Contestio Login - Failed to set Safari iOS cookies: ' . $e->getMessage());
        }
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