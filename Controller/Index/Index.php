<?php
namespace Contestio\Connect\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Contestio\Connect\Block\React as ReactBlock;
use Exception;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        
        // Prevent caching
        $this->getResponse()->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->getResponse()->setHeader('Pragma', 'no-cache');
        $this->getResponse()->setHeader('Expires', '0');

        try {
            $layout = $resultPage->getLayout();
            /** @var ReactBlock $metaBlock */
            $metaBlock = $layout->createBlock(ReactBlock::class);
            $metaData = $metaBlock ? $metaBlock->getMetaTags() : [];
        } catch (Exception $e) {
            $metaData = [];
        }

        if (is_array($metaData) && !empty($metaData)) {
            $pageConfig = $resultPage->getConfig();
            if (!empty($metaData['title'])) {
                $pageConfig->getTitle()->set($metaData['title']);
            }
            if (!empty($metaData['description'])) {
                $pageConfig->setDescription($metaData['description']);
            }
            if (!empty($metaData['canonicalUrl'])) {
                $pageConfig->addRemotePageAsset(
                    $metaData['canonicalUrl'],
                    'canonical',
                    ['attributes' => ['rel' => 'canonical']]
                );
            }
        }

        return $resultPage;
    }
}
