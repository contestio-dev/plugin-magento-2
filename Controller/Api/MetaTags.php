<?php
namespace Contestio\Connect\Controller\Api;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Contestio\Connect\Helper\Data as ApiHelper;

abstract class MetaTags extends Action
{
    protected $apiHelper;

    public function __construct(
        Context $context,
        ApiHelper $apiHelper
    ) {
        $this->apiHelper = $apiHelper;
        parent::__construct($context);
    }

    protected function printMetaTags()
    {
        // Get current url
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\UrlInterface::class);
        $currentUrl = $urlInterface->getCurrentUrl();
        $userAgent = $this->getRequest()->getHeader('User-Agent');

        // Default meta data
        $metaData = array(
            'title' => null,
            'image' => null,
            'siteName' => null,
            'description' => $currentUrl,
        );

        try {
            // Get composer.json version
            // $composerJson = json_decode(file_get_contents('composer.json'), true);
            // $version = $composerJson['version'];

            // echo "<meta name='contestio-version-plugin' content='".$version."'>";

            // Utiliser le helper API pour faire l'appel
            $endpoint = 'v1/org/meta-tags/' . urlencode($currentUrl);
            $method = 'GET';
    
            $response = $this->apiHelper->callApi($userAgent, $endpoint, $method, null);
    
            if ($response && is_array($response)) {
                $metaData = array_merge($metaData, $response);
            }
        } catch (Exception $e) {
            // echo $e->getMessage();
        }

        echo "<meta name='viewport' content='width=device-width, user-scalable=no' />";

        // Og url
        echo "<meta property='og:url' content='".$currentUrl."'>";
        echo "<meta property='twitter:url' content='".$currentUrl."'>";

        // Card type
        echo "<meta name='twitter:card' content='summary'>";

        // Open graph and twitter
        if ($metaData['title']) {
            echo "<meta property='og:title' content='".$metaData['title']."'>";
            echo "<meta property='twitter:title' content='".$metaData['title']."'>";
        }
        if ($metaData['description']) {
            echo "<meta property='og:description' content='".$metaData['description']."'>";
            echo "<meta property='twitter:description' content='".$metaData['description']."'>";
        }
        if ($metaData['image']) {
            echo "<meta property='og:image' content='".$metaData['image']."'>";
            echo "<meta property='twitter:image' content='".$metaData['image']."'>";
        }
        if ($metaData['siteName']) {
            echo "<meta property='og:site_name' content='".$metaData['siteName']."'>";
            echo "<meta property='twitter:site' content='".$metaData['siteName']."'>";
        }
    }
}