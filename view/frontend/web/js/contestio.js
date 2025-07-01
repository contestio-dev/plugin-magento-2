(function () {
  'use strict';

  console.log('contestio.js loaded');

  const verbose = true;

  const logger = {
    log: function (message, data) {
      if (verbose) {
        console.log('Contestio - ' + message, data ?? '');
      }
    },
    warn: function (message, data) {
      if (verbose) {
        console.warn('Contestio - ' + message, data ?? '');
      }
    },
    error: function (message, data) {
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

      if (heightDiff > 20 && currentHeight > this.lastHeight) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }

      this.lastHeight = currentHeight;
    }
  }

  function init() {
    logger.log('contestio.js - init starting');
    
    // ✅ COORDINATION: Attendre que l'iframe soit prête
    const waitForIframe = () => {
      if (!window.contestioGlobal || !window.contestioGlobal.iframeReady) {
        logger.log('contestio.js - waiting for iframe to be ready');
        
        // Ajouter à la queue des callbacks
        if (window.contestioGlobal) {
          window.contestioGlobal.callbacks.push(initContestioFeatures);
        }
        
        // Fallback: réessayer dans 500ms
        setTimeout(waitForIframe, 500);
        return;
      }
      
      initContestioFeatures();
    };

    const initContestioFeatures = () => {
      if (window.contestioInitialized) {
        logger.log('contestio.js - already initialized');
        return;
      }
      
      window.contestioInitialized = true;
      logger.log('contestio.js - initializing features');

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
        const newHeight = windowHeight - offset;

        containerElt.style.height = `${newHeight}px`;
        logger.log('contestio.js - height adjusted to:', newHeight);
      }

      // ✅ DÉLAI SAFARI iOS: Attendre un peu avant ajustement hauteur
      setTimeout(() => {
        adjustHeight();
      }, 300);

      window.addEventListener('resize', adjustHeight);

      // Message listener setup (votre code existant)
      function createMessageListener() {
        const messageHandler = async (event) => {
          const iframeElt = document.querySelector('.contestio-iframe');
          const iframeOrigin = new URL(iframeElt.src).origin;
          
          if (!event.origin || event.origin !== iframeOrigin) {
            logger.warn('Message received from unauthorized origin:', event.origin);
            return;
          }

          if (!event.data || typeof event.data !== 'object') {
            logger.warn('Invalid message received:', event.data);
            return;
          }

          const { type, loginCredentials, pathname, redirectUrl, clipboardText, cookie } = event.data;

          try {
            switch (type) {
              case 'login':
                const url = window.location.href.includes('?')
                  ? window.location.href.split('?')[0]
                  : window.location.href;

                const loginUrl = url.endsWith('/') ? url + 'login' : url + '/login';
                logger.log('Attempting login to:', loginUrl);

                const response = await fetch(loginUrl, {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
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
                const isHttps = window.location.protocol === 'https:';
                let cookieStr;
                if (isHttps) {
                  cookieStr = `${cookie.name}=${cookie.value}; expires=${cookie.expires}; path=${cookie.path || '/'}; SameSite=None; Secure`;
                } else {
                  cookieStr = `${cookie.name}=${cookie.value}; expires=${cookie.expires}; path=${cookie.path || '/'}; SameSite=Lax`;
                }
                document.cookie = cookieStr;
                logger.log('Create cookie:', cookie, cookieStr);
                break;

              case 'getCookie':
                const cookieValue = document.cookie.split('; ').find(row => row.startsWith(`${cookie.name}=`))?.split('=')[1];
                logger.log('Get cookie:', cookie, cookieValue);
                event.source.postMessage({
                  getCookieResponse: {
                    success: cookieValue ? true : false,
                    cookieName: cookie.name,
                    cookieValue: cookieValue
                  }
                }, iframeOrigin);
                break;

              case 'deleteCookie':
                const isHttpsDelete = window.location.protocol === 'https:';
                let deleteCookieStr;
                if (isHttpsDelete) {
                  deleteCookieStr = `${cookie.name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${cookie.path || '/'}; SameSite=None; Secure`;
                } else {
                  deleteCookieStr = `${cookie.name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=${cookie.path || '/'}; SameSite=Lax`;
                }
                document.cookie = deleteCookieStr;
                logger.log('Delete cookie:', cookie, deleteCookieStr);
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

        window.addEventListener('message', messageHandler);

        return () => {
          logger.log('Cleaning up message listener');
          window.removeEventListener('message', messageHandler);
        };
      }

      let cleanup = null;

      function setupListener() {
        logger.log('Setting up message listener');
        if (cleanup) {
          cleanup();
        }
        cleanup = createMessageListener();
      }

      setupListener();

      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          logger.log('Visibility changed to visible, reconfiguring listener');
          setupListener();
        }
      });
    };

    // ✅ COORDINATION: Démarrer l'attente
    waitForIframe();
  }

  // Initialisation
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.addEventListener('popstate', init);
})();