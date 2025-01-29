(function () {
  'use strict';

  console.log('contestio.js loaded');

  const verbose = false;
  
  const logger = {
    log: function(message, data) {
      if (verbose) {
        console.log('Contestio - ' + message, data ?? '');
      }
    },
    warn: function(message, data) {
      if (verbose) {
        console.warn('Contestio - ' + message, data ?? '');
      }
    },
    error: function(message, data) {
      if (verbose) {
        console.error('Contestio - ' + message, data ?? '');
      }
    }
  }

  class KeyboardManager {
    constructor(iframe) {
      this.iframe = iframe;
      this.lastHeight = window.visualViewport?.height || window.innerHeight;

      if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', this.handleViewportResize.bind(this));
      }
    }

    handleViewportResize(event) {
      const currentHeight = window.visualViewport.height;
      const heightDiff = Math.abs(this.lastHeight - currentHeight);
      
      // Scroll to the top if the height change
      if (heightDiff > 20 && currentHeight > this.lastHeight) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
      
      this.lastHeight = currentHeight;
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.contestio-container');
    const iframe = document.querySelector('.contestio-iframe');

    // Initialize keyboard manager
    new KeyboardManager(iframe);

    function adjustHeight() {
      const mainContentElt = document.querySelector('#maincontent');
      const containerElt = document.querySelector('.contestio-container');
      let offset = 0;

      if (mainContentElt) {
        offset = mainContentElt.offsetTop;
      }

      const windowHeight = window.innerHeight;
      const newHeight = windowHeight - offset; // Remove the header/navbar height
      
      // Update the iframe height
      containerElt.style.height = `${newHeight}px`;
    }

    // Adjust the height initially
    if (container) {
      adjustHeight();

      // Adjust the height when the window is resized
      window.addEventListener('resize', adjustHeight);
    }

    // Function to create and configure the message listener
    function createMessageListener() {
      const messageHandler = async (event) => {
        const iframeElt = document.querySelector('.contestio-iframe');
        // Strict security check
        const iframeOrigin = new URL(iframeElt.src).origin;
        if (!event.origin || event.origin !== iframeOrigin) {
          logger.warn('Message received from unauthorized origin:', event.origin);
          return;
        }

        // Check that event.data exists and is an object
        if (!event.data || typeof event.data !== 'object') {
          logger.warn('Invalid message received:', event.data);
          return;
        }

        const {
          type,
          loginCredentials,
          pathname,
          redirectUrl,
          clipboardText
        } = event.data;

        try {
          switch (type) {
            case 'login':
              // Url = current url without query params
              const url = window.location.href.includes('?') 
                ? window.location.href.split('?')[0] 
                : window.location.href;
              
              const loginUrl = url.endsWith('/') ? url + 'login' : url + '/login';
              logger.log('Attempting login to:', loginUrl);

              const response = await fetch(loginUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                  username: loginCredentials.username,
                  password: loginCredentials.password
                }),
              });

              const data = await response.json();

              if (data.success) {
                window.location.reload();
              } else {
                event.source.postMessage({
                  loginResponse: {
                    success: false,
                    message: data.message,
                    data: data
                  }
                }, iframeOrigin);
              }
              break;

            case 'pathname':
              const currentUrl = new URL(window.location.href);
              currentUrl.search = '';
              currentUrl.searchParams.delete('l');
              currentUrl.searchParams.delete('u');

              let newUrl = currentUrl.toString();
              if (pathname !== '' && pathname !== '/') {
                newUrl += (newUrl.includes('?') ? '&' : '?') + 'l=' + pathname;
              }

              logger.log('Update URL to:', newUrl);
              history.pushState(null, null, newUrl);
              break;

            case 'redirect':
              logger.log('Redirect to:', redirectUrl);
              window.location.href = redirectUrl;
              break;

            case 'clipboard':
              logger.log('Copy to clipboard:', clipboardText);
              await navigator.clipboard.writeText(clipboardText);
              break;

            default:
              logger.warn('Unknown message type:', type);
          }
        } catch (error) {
          logger.error('Error processing message:', error);
          
          if (type === 'login') {
            event.source.postMessage({
              loginResponse: {
                success: false,
                message: "Erreur lors du traitement de la requête",
                error: error.message
              }
            }, iframeOrigin);
          }
        }
      };

      // Ajouter le listener
      window.addEventListener('message', messageHandler);

      // Retourner une fonction de cleanup
      return () => {
        logger.log('Cleaning up message listener');
        window.removeEventListener('message', messageHandler);
      };
    }

    // Gérer le cycle de vie du listener
    let cleanup = null;

    function setupListener() {
      logger.log('Setting up message listener');
      // Clean up old listener if it exists
      if (cleanup) {
        cleanup();
      }
      // Create a new listener
      cleanup = createMessageListener();
    }

    // Set up the listener initially
    setupListener();

    // Reconfigure the listener when the page becomes visible
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        logger.log('Visibility changed to visible, reconfiguring listener');
        setupListener();
      }
    });
  });
})();
