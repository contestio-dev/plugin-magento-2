<?php
namespace Contestio\Connect\Block;

use Magento\Framework\View\Element\Template;
use Contestio\Connect\Helper\Data as ApiHelper;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Customer\Model\Session as CustomerSession;

class React extends Template
{
    protected $apiHelper;
    protected $componentRegistrar;
    protected $directoryList;
    protected $scopeConfig;
    protected $customerSession;

    public function __construct(
        Template\Context $context,
        ApiHelper $apiHelper,
        ComponentRegistrar $componentRegistrar,
        DirectoryList $directoryList,
        CustomerSession $customerSession,
        array $data = []
    ) {
        $this->apiHelper = $apiHelper;
        $this->componentRegistrar = $componentRegistrar;
        $this->directoryList = $directoryList;
        $this->scopeConfig = $context->getScopeConfig();
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getIframeUrl()
    {
        $baseUrl = $this->scopeConfig->getValue('contestio_connect/api_settings_advanced/base_url_iframe');
        return $baseUrl ? $baseUrl : "https://plugin.contestio.fr";
    }

    public function getQueryParams()
    {
        // Get shop and access token
        $shop =  $this->scopeConfig->getValue('contestio_connect/api_settings/api_key');
        $accessToken =  $this->scopeConfig->getValue('contestio_connect/api_settings/access_token');
        
        // Get customer data
        $customer = $this->customerSession->getCustomer();
        $customerId = $customer->getId();
        $customerEmail = $customer->getEmail();

        $params = "";

        // Get l parameter from the current url
        $l = $this->getRequest()->getParam('l');
        if ($l && strlen($l) > 0 && $l !== "/") {
            // Check if $l starts with /
            if ($l[0] !== "/") {
                $params .= "/";
            }

            $params .= urlencode($l);
        }

        $params .= "?";

        if ($shop) {
            $params .= "shop=" . urlencode($shop);
        }

        if ($customerId && $customerEmail && $accessToken) {
            // Hash customer id and email with access token
            $params .= "&customer_id=" . urlencode($this->apiHelper->encryptDataBase64($customerId, $accessToken));
            $params .= "&customer_email=" . urlencode($this->apiHelper->encryptDataBase64($customerEmail, $accessToken));
        }

        // Get current query params
        $currentQueryParams = $this->getRequest()->getParams();
        if ($currentQueryParams) {
            // Retirer le debug
            foreach ($currentQueryParams as $key => $value) {
                if ($key !== 'l' && $key !== 'shop' && $key !== 'customer_id' && $key !== 'customer_email') {
                    $params .= "&" . urlencode($key) . "=" . urlencode($value);
                }
            }
        }

        // Return the encoded params
        return $params === "?" ? "" : $params;
    }

    public function getMetaTags()
    {
        // Get current url
        $currentUrl = $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);
        $userAgent = $this->getRequest()->getHeader('User-Agent');

        // Default meta data
        $metaData = array(
            'title' => null,
            'image' => null,
            'siteName' => null,
            'description' => $currentUrl,
            'version' => null,
            'currentUrl' => $currentUrl,
        );

        try {
            // Get module version from composer.json
            $modulePath = $this->componentRegistrar->getPath(
                ComponentRegistrar::MODULE,
                'Contestio_Connect'
            );
            $composerJson = json_decode(file_get_contents($modulePath . '/composer.json'), true);
            $metaData['version'] = $composerJson['version'] ?? null;

            // Get meta tags from Contestio
            $response = $this->apiHelper->callApi(
                $userAgent,
                'v1/org/meta-tags/' . urlencode($currentUrl),
                'GET',
                null
            );

            return $response;
    
            if ($response && is_array($response)) {
                $metaData = array_merge($metaData, $response);
            }
        } catch (Exception $e) {
            // echo $e->getMessage();
        }

        return $metaData;
    }
}
