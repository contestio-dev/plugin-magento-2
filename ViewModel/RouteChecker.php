<?php
namespace Contestio\Connect\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class RouteChecker implements ArgumentInterface
{
    private $request;

    public function __construct(Http $request)
    {
        $this->request = $request;
    }

    public function isContestioRoute(): bool
    {
        return strpos($this->request->getPathInfo(), '/contestio') === 0;
    }
}