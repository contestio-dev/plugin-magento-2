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

class Index extends Action implements CsrfAwareActionInterface
{
    protected $resultJsonFactory;
    protected $customerSession;
    protected $accountManagement;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Session $customerSession,
        AccountManagementInterface $accountManagement
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->accountManagement = $accountManagement;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $origin = $this->getRequest()->getHeader('Origin') ?: 'Unknown';
        error_log('Login request from origin in plugin : ' . $origin);
        error_log('HEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEERE !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!');

        // Ajouter les en-têtes CORS
        $resultJson->setHeader('Access-Control-Allow-Origin', $origin);
        $resultJson->setHeader('Access-Control-Allow-Credentials', 'true');
        $resultJson->setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $resultJson->setHeader('Access-Control-Allow-Headers', 'Access-Control-Allow-Headers, Origin, Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers, Authorization');
        
        // Gérer la requête OPTIONS (preflight)
        if ($this->getRequest()->getMethod() === 'OPTIONS') {
            return $resultJson;
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
            $requestHeaders = $this->getRequest()->getHeaders()->toArray();
            error_log('=== REQUEST HEADERS ===');
            foreach ($requestHeaders as $name => $value) {
                error_log($name . ': ' . (is_array($value) ? implode(', ', $value) : $value));
            }
            return $resultJson->setData([
                'success' => true,
                'message' => __('Login successful.')
            ]);
        } catch (EmailNotConfirmedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('This account is not confirmed. Please check your email for confirmation link.')
            ])->setHttpResponseCode(401);
        } catch (AuthenticationException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Invalid login or password.')
            ])->setHttpResponseCode(401);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred during login. Please try again later.')
            ])->setHttpResponseCode(500);
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