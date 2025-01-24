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
        $key = hash('sha256', $accessToken, true); // Génération de la clé
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method)); // Génération d'un IV
    
        // Chiffrement des données
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
    
        // Encodage des données et de l'IV en Base64
        return base64_encode(json_encode([
            'iv' => base64_encode($iv),
            'data' => $encrypted,
        ]));
    }

    public function callApi($userAgent, $endpoint, $method, $data = null)
    {
        $baseUrl = $this->getApiBaseUrl();

        if ($endpoint === 'me' && $method === 'GET') {
            return $this->getMe();
        }

        if ($endpoint === 'pseudo' && $method === 'POST') {
            $response = $this->handlePseudoUpdate($data);
            if (!$response['success']) {
                throw new \Exception($response['message']);
                return;
            }
            $endpoint = 'v1/users/final/upsert';
            $data = $response['data'];
        }

        $url = $baseUrl . '/' . $endpoint;
        
        $headers = [
            'Content-Type' => 'application/json',
            // 'clientkey' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_key'),
            // 'clientsecret' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_secret'),
            // 'externalId' => $this->customerSession->getCustomerId(),
            'client-shop' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_key'),
            'client-customer-id' => $this->encryptDataBase64(
                $this->customerSession->getCustomerId(),
                $this->scopeConfig->getValue('contestio_connect/api_settings/access_token')
            ),
            'client-customer-email' => $this->encryptDataBase64(
                $this->customerSession->getCustomer()->getEmail(),
                $this->scopeConfig->getValue('contestio_connect/api_settings/access_token')
            ),
            'clientuseragent' => $userAgent
        ];

        $this->curl->setHeaders($headers); // Headers
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            $this->curl->setOption(CURLOPT_POSTFIELDS, json_encode($data)); // Données POST
        }

        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method); // Méthode HTTP

        $this->curl->setOption(CURLOPT_TIMEOUT, 5); // Timeout après 5 secondes
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 3); // Timeout de connexion après 3 secondes

        $this->curl->get($url);

        $response = $this->curl->getBody(); // Réponse
        $httpCode = $this->curl->getStatus(); // Code HTTP

        if ($httpCode >= 200 && $httpCode < 300) {
            if ($endpoint === 'v1/users/final/generate-token') {
                $response = json_decode($response, true);
                // Add apiurl to response
                $response['apiurl'] = $this->getApiBaseUrl();

                return $response;
            }

            // Else, return json decoded response
            return json_decode($response, true);
        } else {
            throw new \Exception(
                $response,
                $httpCode
            );
        }
    }
}
