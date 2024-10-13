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

        $username = $this->getRequest()->getParam('username');
        $password = $this->getRequest()->getParam('password');

        return $resultJson->setData([
            'username' => $username,
            'password' => $password,
            'post' => $this->getRequest()->getPostValue()
        ]);

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