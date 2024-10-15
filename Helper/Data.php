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
    const XML_PATH_API_BASE_URL = 'contestio_connect/api/base_url';

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
    

    public function getApiBaseUrl()
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_API_BASE_URL,
            ScopeInterface::SCOPE_STORE
        );
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
            'clientkey' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_key'),
            'clientsecret' => $this->scopeConfig->getValue('contestio_connect/api_settings/api_secret'),
            'externalId' => $this->customerSession->getCustomerId(),
            'clientuseragent' => $userAgent
        ];

        $this->curl->setHeaders($headers);
        
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            $this->curl->setOption(CURLOPT_POSTFIELDS, json_encode($data));
        }

        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
        $this->curl->get($url);

        $response = $this->curl->getBody();
        $httpCode = $this->curl->getStatus();

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            throw new \Exception(
                $response,
                $httpCode
            );
        }
    }

    public function getMe()
    {
        $customerData = [
            'id' => null,
            'email' => null,
            'firstName' => null,
            'lastName' => null,
        ];
    
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            $customerData = [
                'id' => $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstname(),
                'lastName' => $customer->getLastname(),
            ];
        }
    
        return $customerData;
    }


    private function handlePseudoUpdate($data)
    {
        $userData = $this->getMe();

        if (!$userData) {
            return ['success' => false, 'message' => 'Vous devez être connecté pour modifier votre pseudo.'];
        }

        return [
            'success' => true,
            'data' => [
                'externalId' => $userData['id'],
                'email' => $userData['email'],
                'fname' => $userData['firstName'],
                'lname' => $userData['lastName'],
                'pseudo' => $data['pseudo'],
                'isFromContestio' => $data['isFromContestio'] ?? null,
            ]
        ];
    }

    public function uploadImage($userAgent, $endpoint, $file)
    {
        $url = $this->getApiBaseUrl() . '/' . $endpoint;
        $headers = [
            'clientkey: ' . $this->scopeConfig->getValue('contestio_connect/api_settings/api_key'),
            'clientsecret: ' . $this->scopeConfig->getValue('contestio_connect/api_settings/api_secret'),
            'externalId: ' . $this->customerSession->getCustomerId(),
            'clientuseragent: ' . $userAgent
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            $postFields = [
                'file' => new \CURLFile($file['tmp_name'], $file['type'], $file['name'])
            ];
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
    
            if ($httpCode >= 200 && $httpCode < 300) {
                if (strpos($contentType, 'image/webp') !== false) {
                    return base64_encode($response);
                }
                return json_decode($response, true);
            } else {
                throw new \Exception($response, $httpCode);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }
}
