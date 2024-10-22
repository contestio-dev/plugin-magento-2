<?php
namespace Contestio\Connect\Block;

use Magento\Framework\View\Element\Template;

class React extends Template
{
    public function __construct(Context $context, array $data = [])
    {
        $this->_isScopePrivate = true;
    }

    public function getReactAppUrl()
    {
        return "https://d36h2ac42341sx.cloudfront.net";
    }
}