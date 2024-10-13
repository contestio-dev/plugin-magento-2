<?php

namespace Contestio\Connect\Controller\Like;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Contestio\Connect\Helper\Data as Helper;

class Index extends Action
{
	protected $curlClient;
	protected $scopeConfig;
	protected $httpContext;
	protected $resultJsonFactory;
	protected $customerSession;
	
	/**
	 * @var Helper
	 */
	protected $helper;

	public function __construct(
		Context $context,
		\Magento\Framework\App\Http\Context $httpContext,
		Curl $curlClient,
		ScopeConfigInterface $scopeConfig,
		JsonFactory $resultJsonFactory,
		Helper $helper,
		CustomerSession $customerSession
	) {
		parent::__construct($context);
		$this->httpContext = $httpContext;
		$this->curlClient = $curlClient;
		$this->scopeConfig = $scopeConfig;
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helper = $helper;
		$this->customerSession = $customerSession;
	}

	public function execute() {
		$postdata = $this->getRequest()->getPostValue();
	
        return $this->resultJsonFactory->create()->setData(['success' => true, 'message' => $this->getRequest()->getMethod(), 'data' => $postdata]);

		$apiUrl = $this->helper->getApiBaseUrl() . '/v1/participation/like/' . $postdata['participationId'];
		
		$clientKey = $this->scopeConfig->getValue('authkeys/clientkey/clientpubkey');
		$clientSecret = $this->scopeConfig->getValue('authkeys/clientkey/clientsecret');

		/** @var CustomerInterface $customer */
		$customer = $this->customerSession->getCustomer();

		if (!$customer) {
			return $this->resultJsonFactory->create()->setData(['success' => false]);
		}
		
		$headers = [
			"Content-Type: application/json",
			"clientKey: " . $clientKey,
			"clientSecret: " . $clientSecret,
			"externalId: " . $customer->getId()
		];
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
		  CURLOPT_URL => $apiUrl,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>'',//$jsonBody,
		  CURLOPT_HTTPHEADER =>$headers,
		));

		$response = curl_exec($curl);
		$httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		$data = null;
		
		if ($httpStatus === 201) {
			$data = array(
				'success' => true
			);
		} else {
			$decoded = json_decode($response, true);
			$data = array(
				'success' => false,
				'message' => isset($decoded['message']) ? $decoded['message'] : 'Une erreur est survenue'
			);
		}

		if ($httpStatus === 201 || json_last_error() === JSON_ERROR_NONE) {
			return $this->resultJsonFactory->create()->setData($data);
		}

		// Return false if unable to decode JSON
		return $this->resultJsonFactory->create()->setData(['success' => false]);
	}
}
