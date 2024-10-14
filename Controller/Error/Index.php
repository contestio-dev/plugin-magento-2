<?php
namespace Contestio\Connect\Controller\Error;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Contestio\Connect\Controller\Api\MetaTags;
use Contestio\Connect\Helper\Data as ApiHelper;

class Index extends MetaTags
{
    protected $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ApiHelper $apiHelper
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context, $apiHelper);
    }

    public function execute()
    {
        parent::printMetaTags();
        
        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}