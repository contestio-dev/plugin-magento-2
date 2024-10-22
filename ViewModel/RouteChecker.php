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
        
        // Vérifier si 'contestio' est présent dans l'URL, en ignorant le préfixe de langue potentiel
        return in_array('contestio', $pathParts);
    }
}
