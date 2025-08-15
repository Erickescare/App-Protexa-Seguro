// app-protexa-seguro/sw.js

const CACHE_NAME = 'protexa-seguro-v1.0.0';
const OFFLINE_URL = '/app-protexa-seguro/offline.html';

// Archivos que queremos cachear (solo los que sabemos que existen)
const STATIC_CACHE_URLS = [
  '/app-protexa-seguro/',
  '/app-protexa-seguro/index.php',
  '/app-protexa-seguro/dashboard.php',
  '/app-protexa-seguro/assets/css/app.css',
  '/app-protexa-seguro/assets/js/app.js',
  '/app-protexa-seguro/manifest.json'
];

// Instalación del Service Worker
self.addEventListener('install', event => {
  console.log('Service Worker: Instalando...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Cacheando archivos estáticos');
        
        // Cachear archivos uno por uno para manejar errores
        return Promise.allSettled(
          STATIC_CACHE_URLS.map(url => 
            cache.add(url).catch(error => {
              console.warn(`No se pudo cachear ${url}:`, error);
              return null;
            })
          )
        );
      })
      .then(() => self.skipWaiting())
      .catch(error => {
        console.error('Error durante la instalación del SW:', error);
      })
  );
});

// Activación del Service Worker
self.addEventListener('activate', event => {
  console.log('Service Worker: Activando...');
  
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Eliminando caché antigua:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Interceptar peticiones de red
self.addEventListener('fetch', event => {
  // Solo manejar peticiones GET de nuestro dominio
  if (event.request.method !== 'GET' || !event.request.url.includes('/app-protexa-seguro/')) {
    return;
  }
  
  // Estrategia diferente según el tipo de recurso
  if (event.request.url.includes('/assets/')) {
    // Cache First para recursos estáticos
    event.respondWith(
      caches.match(event.request)
        .then(response => {
          if (response) {
            return response;
          }
          
          return fetch(event.request)
            .then(response => {
              // Solo cachear respuestas exitosas
              if (response.status === 200) {
                const responseClone = response.clone();
                caches.open(CACHE_NAME)
                  .then(cache => cache.put(event.request, responseClone))
                  .catch(error => console.warn('Error cacheando:', error));
              }
              return response;
            })
            .catch(error => {
              console.warn('Error fetching:', event.request.url, error);
              return new Response('', { status: 404 });
            });
        })
    );
  } else if (event.request.url.includes('.php') || event.request.url.endsWith('/')) {
    // Network First para contenido dinámico
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cachear páginas exitosas
          if (response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME)
              .then(cache => cache.put(event.request, responseClone))
              .catch(error => console.warn('Error cacheando página:', error));
          }
          return response;
        })
        .catch(() => {
          // Si no hay conexión, buscar en caché
          return caches.match(event.request)
            .then(response => {
              if (response) {
                return response;
              }
              // Página offline de fallback
              return caches.match('/app-protexa-seguro/index.php')
                .then(fallback => fallback || new Response('Offline', { status: 503 }));
            });
        })
    );
  }
});

// Manejo de sincronización en segundo plano
self.addEventListener('sync', event => {
  if (event.tag === 'sync-recorrido') {
    console.log('Service Worker: Sincronizando recorridos pendientes...');
    event.waitUntil(syncPendingData());
  }
});

// Función para sincronizar datos pendientes
async function syncPendingData() {
  try {
    // Obtener datos pendientes del IndexedDB
    const pendingData = await getPendingFromIndexedDB();
    
    if (pendingData.length > 0) {
      for (const data of pendingData) {
        try {
          const response = await fetch('/app-protexa-seguro/api/save-progress.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
          });
          
          if (response.ok) {
            // Eliminar de IndexedDB si se envió correctamente
            await removePendingFromIndexedDB(data.id);
          }
        } catch (error) {
          console.error('Error sincronizando:', error);
        }
      }
    }
  } catch (error) {
    console.error('Error en sincronización:', error);
  }
}

// Funciones auxiliares para IndexedDB (implementar según necesidad)
async function getPendingFromIndexedDB() {
  // Implementar lógica para obtener datos pendientes
  return [];
}

async function removePendingFromIndexedDB(id) {
  // Implementar lógica para eliminar datos sincronizados
  console.log('Eliminando registro sincronizado:', id);
}

// Manejo de errores del Service Worker
self.addEventListener('error', event => {
  console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
  console.error('Service Worker unhandled promise rejection:', event.reason);
});