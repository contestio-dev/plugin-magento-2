(function () {
  'use strict';

  console.log('contestio.js loaded');

  const verbose = true;
  
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

  // Remplacer ces fonctions par une seule initialisation
  function init() {
    console.log('111111111111');
    // Au lieu de bloquer complètement la réinitialisation, on va la forcer si nécessaire
    if (window.contestioInitialized) {
      // On vérifie si les éléments nécessaires sont présents
      const container = document.querySelector('.contestio-container');
      const iframe = document.querySelector('.contestio-iframe');
      
      if (container && iframe) {
        // Si les éléments sont présents, on force la réinitialisation
        logger.log('contestio.js - reinitializing');
        window.contestioInitialized = false;
      } else {
        logger.log('contestio.js - skipping initialization (no elements found)');
        return;
      }
    }
    window.contestioInitialized = true;
    console.log('222222222222');

    logger.log('contestio.js - init');
    const container = document.querySelector('.contestio-container');
    const iframe = document.querySelector('.contestio-iframe');

    if (!container || !iframe) {
      logger.warn('contestio.js - container or iframe not found');
      return;
    }

    // Initialize keyboard manager
    new KeyboardManager(iframe);

    function adjustHeight() {
      const mainContentElt = document.querySelector('#maincontent');
      const containerElt = document.querySelector('.contestio-container');

      if (!mainContentElt || !containerElt) {
        logger.warn('contestio.js - mainContentElt or containerElt not found');
        return;
      }

      let offset = mainContentElt.offsetTop || 0;

      const windowHeight = window.innerHeight;
      const newHeight = windowHeight - offset; // Remove the header/navbar height
      
      // Update the iframe height
      containerElt.style.height = `${newHeight}px`;
    }

    // Ajuster la hauteur immédiatement
    adjustHeight();

    // Ajuster la hauteur lors du redimensionnement
    window.addEventListener('resize', adjustHeight);

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
          clipboardText,
          cookie
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

            case 'createCookie':
              // Create cookie with SameSite=None for Safari iOS iframe support
              const isHttps = window.location.protocol === 'https:';
              const cookieStr = `${cookie.name}=${cookie.value}; expires=${cookie.expires}; path=${cookie.path || '/'}; SameSite=None${isHttps ? '; Secure' : ''}`;
              document.cookie = cookieStr;
              logger.log('Create cookie:', cookie, cookieStr, document.cookie);
              break;

            case 'getCookie':
              // Get cookie
              const cookieValue = document.cookie.split('; ').find(row => row.startsWith(`${cookie.name}=`))?.split('=')[1];
              logger.log('Get cookie:', cookie, cookieValue);
              // Send response to the iframe
              event.source.postMessage({
                getCookieResponse: {
                  success: cookieValue ? true : false,
                  cookieName: cookie.name,
                  cookieValue: cookieValue
                }
              }, iframeOrigin);
              break;

            case 'deleteCookie':
              // Delete cookie with same SameSite attributes
              const isHttpsDelete = window.location.protocol === 'https:';
              const deleteCookieStr = `${cookie.name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${cookie.path || '/'}; SameSite=None${isHttpsDelete ? '; Secure' : ''}`;
              document.cookie = deleteCookieStr;
              logger.log('Delete cookie:', cookie, deleteCookieStr, document.cookie);
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
  }

  // Modifier la partie d'initialisation pour écouter aussi les changements de navigation
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Ajouter un écouteur pour le changement de page avec History API
  window.addEventListener('popstate', init);
})();
