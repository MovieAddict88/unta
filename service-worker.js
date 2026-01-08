const CACHE_NAME = 'conecraze-v3-netflix';
const urlsToCache = [
  '/',
  '/index.php',
  '/manifest.webmanifest',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://cdn.plyr.io/3.7.8/plyr.css',
  'https://cdn.plyr.io/3.7.8/plyr.js',
  'https://cdn.jsdelivr.net/npm/flag-icon-css@4.1.7/css/flag-icons.min.css',
  'https://cdn.dashjs.org/latest/dash.all.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/shaka-player/4.3.7/shaka-player.compiled.js',
  'https://movie-fcs.fwh.is/cinecraze/cinecraze.png'
];

// Install event - cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .catch(error => {
        console.error('Cache addAll failed:', error);
        // Continue even if some items fail to cache
      })
      .then(() => self.skipWaiting())
  );
});

// Fetch event - serve from cache, fall back to network
self.addEventListener('fetch', event => {
  // Always hit the network for dynamic API responses; app content is cached via IndexedDB instead.
  if (event.request.url.includes('api.php')) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Cache hit - return response
        if (response) {
          return response;
        }

        // Clone the request
        const fetchRequest = event.request.clone();

        return fetch(fetchRequest).then(response => {
          // Check if valid response
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clone the response
          const responseToCache = response.clone();

          caches.open(CACHE_NAME)
            .then(cache => {
              // Don't cache video streaming URLs or large files
              if (!event.request.url.includes('.m3u8') &&
                  !event.request.url.includes('.mp4') &&
                  !event.request.url.includes('.mpd') &&
                  !event.request.url.includes('api.php') &&
                  !event.request.url.includes('api/') &&
                  !event.request.url.includes('youtube.com') &&
                  !event.request.url.includes('googlevideo.com')) {
                cache.put(event.request, responseToCache);
              }
            });

          return response;
        }).catch(error => {
          console.error('Fetch failed:', error);
          // Return a custom offline page or fallback
          if (event.request.destination === 'document') {
            return caches.match('/index.php');
          }
        });
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Message event - handle messages from clients
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

// Push notification event (optional - for future use)
self.addEventListener('push', event => {
  const options = {
    body: event.data ? event.data.text() : 'New content available!',
    icon: 'https://movie-fcs.fwh.is/cinecraze/cinecraze.png',
    badge: 'https://movie-fcs.fwh.is/cinecraze/cinecraze.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    }
  };

  event.waitUntil(
    self.registration.showNotification('ConeCraze', options)
  );
});
