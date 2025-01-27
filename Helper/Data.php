<?php
namespace Contestio\Connect\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper
{
    protected $curl;
    protected $customerSession;
    protected $scopeConfig;

    public function __construct(
        Context $context,
        Curl $curl,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->curl = $curl;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }
    
    private function getApiBaseUrl()
    {
        $baseUrl = $this->scopeConfig->getValue('contestio_connect/api_settings_advanced/base_url');
        return $baseUrl ? $baseUrl : 'https://api.contestio.fr';
    }

    public function encryptDataBase64($data, $accessToken) {
        $method = 'AES-256-CBC';

        // Generate key and iv
        $key = hash('sha256', $accessToken, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    
        // Encrypt data
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    
        // Encode data and iv in Base64
        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'data' => $encrypted,
        ]));
    }

    public function callApi($userAgent, $endpoint, $method, $data = null)
    {
        $url = $this->getApiBaseUrl() . '/' . $endpoint;
        
        $headers = [
            'Content-Type' => 'application/json',
            'client-shop' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_key'),
            'clientuseragent' => $userAgent
        ];

        // Add customer id and email to headers
        if ($this->customerSession->isLoggedIn()) {
            $headers['client-customer-id'] = $this->encryptDataBase64(
                $this->customerSession->getCustomerId(),
                $this->scopeConfig->getValue('contestio_connect/api_settings/access_token')
            );
            $headers['client-customer-email'] = $this->encryptDataBase64(
                $this->customerSession->getCustomer()->getEmail(),
                $this->scopeConfig->getValue('contestio_connect/api_settings/access_token')
            );
        }

        // Set headers
        $this->curl->setHeaders($headers);
        
        // Set data (used for POST - final user order observer)
        if ($method === 'POST') {
            $this->curl->setOption(CURLOPT_POSTFIELDS, json_encode($data));
        }

        // Set method
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method);

        // Set timeout
        $this->curl->setOption(CURLOPT_TIMEOUT, 5);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 3);

        // Make request
        $this->curl->get($url);

        $response = $this->curl->getBody(); // Response
        $httpCode = $this->curl->getStatus(); // HTTP code

        if ($httpCode >= 200 && $httpCode < 300) {
            // Return json decoded response
            return json_decode($response, true);
        } else {
            // Throw error
            throw new \Exception(
                $response,
                $httpCode
            );
        }
    }
}
