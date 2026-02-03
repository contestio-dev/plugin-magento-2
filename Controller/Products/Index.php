<?php

namespace Contestio\Connect\Controller\Products;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action
{
    protected $resultJsonFactory;
    protected $productRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;
    protected $imageHelper;
    protected $storeManager;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        ImageHelper $imageHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->imageHelper = $imageHelper;
        $this->storeManager = $storeManager;
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

            // Paramètres de recherche
            $search = $this->getRequest()->getParam('search');
            $ids = $this->getRequest()->getParam('ids');
            $pageSize = (int) ($this->getRequest()->getParam('limit') ?? 20);
            $currentPage = (int) ($this->getRequest()->getParam('page') ?? 1);

            // Limiter pour éviter les abus
            if ($pageSize > 100) {
                $pageSize = 100;
            }

            // Construire les critères de recherche
            // Pagination seulement si pas de filtre par IDs
            if (!$ids) {
                $this->searchCriteriaBuilder
                    ->setPageSize($pageSize)
                    ->setCurrentPage($currentPage);
            }

            // Filtrer par IDs si fournis (pour récupérer des produits spécifiques)
            if ($ids) {
                $productIds = array_filter(array_map('trim', explode(',', $ids)));
                if (!empty($productIds)) {
                    $this->searchCriteriaBuilder->addFilter('entity_id', $productIds, 'in');
                }
            }

            // Filtrer par nom si recherche
            if ($search) {
                $filter = $this->filterBuilder
                    ->setField('name')
                    ->setValue('%' . $search . '%')
                    ->setConditionType('like')
                    ->create();
                $this->searchCriteriaBuilder->addFilter($filter);
            }

            // Uniquement les produits actifs
            $this->searchCriteriaBuilder->addFilter('status', 1);

            // Uniquement les produits visibles (2=Catalog, 3=Search, 4=Catalog+Search)
            $this->searchCriteriaBuilder->addFilter('visibility', [2, 3, 4], 'in');

            $searchCriteria = $this->searchCriteriaBuilder->create();
            $productList = $this->productRepository->getList($searchCriteria);

            // Formater les produits
            $products = [];
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();

            foreach ($productList->getItems() as $product) {
                $imageUrl = null;
                try {
                    // Use 'small_image' attribute with 200px resize (matching Magento 1)
                    $imageUrl = $this->imageHelper->init($product, 'small_image')
                        ->resize(200)
                        ->getUrl();
                } catch (\Exception $e) {
                    // Image non disponible
                }

                $products[] = [
                    'id' => $product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice(),
                    'imageUrl' => $imageUrl,
                    'url' => $product->getProductUrl(),
                ];
            }

            return $this->resultJsonFactory->create()->setData([
                'success' => true,
                'data' => [
                    'products' => $products,
                    'totalCount' => $productList->getTotalCount(),
                    'pageSize' => $pageSize,
                    'currentPage' => $currentPage,
                ]
            ])->setHttpResponseCode(200);

        } catch (AuthenticationException $e) {
            return $this->resultJsonFactory->create()
                ->setHttpResponseCode(401)
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
