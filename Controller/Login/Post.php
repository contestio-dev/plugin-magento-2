<?php

namespace Contestio\Connect\Controller\Login;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;

class Post extends Action
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

        // return $resultJson->setData([
        //     'username' => $username,
        //     'password' => $password,
        //     'method' => $this->getRequest()->getMethod(),
        //     'content' => $this->getRequest()->getContent()
        // ]);

        try {
            $customer = $this->accountManagement->authenticate($username, $password);
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
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
}