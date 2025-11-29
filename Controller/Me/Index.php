<?php

namespace Contestio\Connect\Controller\Me;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\NoSuchEntityException;

class Index extends Action
{
    protected $resultJsonFactory;
    protected $customerRepository;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerRepository = $customerRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        // Prevent caching
        $this->getResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->getResponse()->setHeader('Pragma', 'no-cache');
        $this->getResponse()->setHeader('Expires', '0');
        
        try {
            // Vérifier le header Authorization
            $authHeader = $this->getRequest()->getHeader('Authorization');
            if (!$authHeader) {
                throw new AuthenticationException(__('Authorization header is missing'));
            }

            // Vérifier le token d'accès
            $configToken = $this->_objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class)
                ->getValue('contestio_connect/api_settings/access_token');
            $providedToken = str_replace('Bearer ', '', $authHeader);

            if ($providedToken !== $configToken) {
                throw new AuthenticationException(__('Invalid access token'));
            }

            // Récupérer l'ID client à partir de la requête (exemple : id passé en paramètre GET)
            $customerId = $this->getRequest()->getParam('id');
            if (!$customerId) {
                throw new \InvalidArgumentException(__('Customer ID is required'));
            }

            // Charger le client par ID
            try {
                $customer = $this->customerRepository->getById($customerId);
            } catch (NoSuchEntityException $e) {
                throw new \Exception(__('Customer not found'));
            }

            // Retourner les données du client
            return $this->resultJsonFactory->create()->setData([
                'success' => true,
                'data' => [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'firstName' => $customer->getFirstname(),
                    'lastName' => $customer->getLastname(),
                    'createdAt' => $customer->getCreatedAt()
                ]
            ])->setHttpResponseCode(200);

        } catch (AuthenticationException $e) {
            return $this->resultJsonFactory->create()
                ->setHttpResponseCode(401)
                ->setData([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
        } catch (\InvalidArgumentException $e) {
            return $this->resultJsonFactory->create()
                ->setHttpResponseCode(400)
                ->setData([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
        } catch (\Exception $e) {
            return $this->resultJsonFactory->create()
                ->setHttpResponseCode(500)
                ->setData([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
        }
    }
}
