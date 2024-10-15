<?php 
namespace Contestio\Connect\Plugin;
use Magento\Framework\Data\Tree\NodeFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Topmenu
{
    protected $nodeFactory;
    protected $_storeManager;
    protected $_pageFactory;
    protected $_urlBuilder;
    protected $scopeConfig;

    public function __construct(
        NodeFactory $nodeFactory,
        \Magento\Cms\Model\PageFactory $pageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->nodeFactory = $nodeFactory;
        $this->_pageFactory = $pageFactory;
        $this->_storeManager = $storeManager;
        $this->_urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
    }

    public function beforeGetHtml(
        \Magento\Theme\Block\Html\Topmenu $subject,
        $outermostClass = '',
        $childrenWrapClass = '',
        $limit = 0
    ) {
        $customText = $this->scopeConfig->getValue(
            'contestio_connect_navigation/nav_button/text',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $showButton = $this->scopeConfig->getValue(
            'contestio_connect_navigation/nav_button/show',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Not print the button if disabled from admin
        if ($showButton === 'hide') {
            return;
        }

        // Is active if routes starts with 'contestio'
        $isActive = strpos($this->_urlBuilder->getCurrentUrl(), '/contestio') !== false;

        $activeClass = $isActive ? 'active' : '';

        // Add Custom Menu
        $node = $this->nodeFactory->create([
            'data' => [
                'name' => $customText ?? __('Le Club'),
                'class' => 'contestio-link ' . $activeClass . ' ' . $outermostClass,
                'url' => $this->_urlBuilder->getUrl(null, ['_direct' =>'contestio']),
                'has_active' => false,
                'is_active' => false
            ],
            'idField' => 'id',
            'tree' => $subject->getMenu()->getTree()
        ]);

        $subject->getMenu()->addChild($node);
    }
}
