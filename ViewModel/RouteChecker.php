<?php
namespace Contestio\Connect\ViewModel;

use Magento\Framework\App\Request\Http;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class RouteChecker implements ArgumentInterface
{
    protected $request;

    public function __construct(Http $request)
    {
        $this->request = $request;
    }

    public function isContestioRoute(): bool
    {
        $pathInfo = trim($this->request->getPathInfo(), '/');
        $pathParts = explode('/', $pathInfo);
        
        // Check if the last part of the path is 'contestio'
        if (end($pathParts) !== 'contestio') {
            return false;
        }
        
        // Check if 'contestio' is present only once in the path
        $counts = array_count_values($pathParts);
        return ($counts['contestio'] ?? 0) === 1;
    }
}
