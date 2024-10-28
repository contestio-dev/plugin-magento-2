<?php
namespace Contestio\Connect\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Contestio\Connect\Helper\Data as ApiHelper;

class Index extends Action
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
        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}