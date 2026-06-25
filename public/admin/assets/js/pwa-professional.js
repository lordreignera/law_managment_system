// Professional PWA Manager for ERA Audit System
class ProfessionalPWAManager {
    constructor() {
        this.version = '2.0.0';
        this.isOnline = navigator.onLine;
        this.installPrompt = null;
        this.updateAvailable = false;
        this.syncInProgress = false;
        
        this.init();
    }

    async init() {
        console.log('🚀 ERA Professional PWA Manager v' + this.version);
        
        // Register advanced service worker
        await this.registerServiceWorker();
        
        // Setup PWA install handling
        this.setupInstallPrompt();
        
        // Setup update notifications
        this.setupUpdateHandling();
        
        // Setup network monitoring
        this.setupNetworkMonitoring();
        
        // Setup push notifications
        this.setupPushNotifications();
        
        // Setup periodic sync
        this.setupPeriodicSync();
        
        // Setup performance monitoring
        this.setupPerformanceMonitoring();
        
        // Initialize UI indicators
        this.initializeUI();
    }

    async registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw-advanced.js', {
                    scope: '/'
                });
                
                console.log('✅ Advanced Service Worker registered:', registration.scope);
                
                // Handle updates
                registration.addEventListener('updatefound', () => {
                    this.handleServiceWorkerUpdate(registration);
                });
                
                // Listen for messages from service worker
                navigator.serviceWorker.addEventListener('message', event => {
                    this.handleServiceWorkerMessage(event.data);
                });
                
                return registration;
            } catch (error) {
                console.error('❌ Service Worker registration failed:', error);
                throw error;
            }
        } else {
            throw new Error('Service Workers not supported');
        }
    }

    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('📱 PWA install prompt available');
            
            // Prevent default install prompt
            e.preventDefault();
            
            // Store the event for later use
            this.installPrompt = e;
            
            // Show custom install button
            this.showInstallButton();
        });

        // Handle successful installation
        window.addEventListener('appinstalled', () => {
            console.log('🎉 PWA installed successfully');
            this.hideInstallButton();
            this.showNotification('App installed successfully!', 'success');
            
            // Analytics tracking
            this.trackEvent('pwa_installed');
        });
    }

    setupUpdateHandling() {
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            console.log('🔄 Service Worker controller changed');
            
            if (this.updateAvailable) {
                this.showUpdateCompleteNotification();
                this.updateAvailable = false;
            }
        });
    }

    handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;
        
        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed') {
                if (navigator.serviceWorker.controller) {
                    // New version available
                    console.log('📦 New version available');
                    this.updateAvailable = true;
                    this.showUpdateAvailableNotification();
                } else {
                    // First install
                    console.log('✅ App ready for offline use');
                    this.showOfflineReadyNotification();
                }
            }
        });
    }

    handleServiceWorkerMessage(data) {
        switch (data.type) {
            case 'SYNC_COMPLETE':
                this.handleSyncComplete(data.count);
                break;
            case 'CACHE_UPDATED':
                console.log('📦 Cache updated:', data.url);
                break;
            case 'OFFLINE_REQUEST_STORED':
                this.showOfflineRequestStored();
                break;
        }
    }

    setupNetworkMonitoring() {
        // Enhanced network status monitoring
        const updateOnlineStatus = () => {
            const wasOnline = this.isOnline;
            this.isOnline = navigator.onLine;
            
            if (wasOnline !== this.isOnline) {
                this.updateConnectionStatus();
                
                if (this.isOnline) {
                    this.handleOnline();
                } else {
                    this.handleOffline();
                }
            }
        };

        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        // Periodic connectivity check
        setInterval(() => {
            this.checkConnectivity();
        }, 30000);
    }

    async setupPushNotifications() {
        if ('Notification' in window && 'serviceWorker' in navigator) {
            try {
                // Request notification permission
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    console.log('🔔 Notification permission granted');
                    
                    // Subscribe to push notifications
                    await this.subscribeToPushNotifications();
                } else {
                    console.log('🔕 Notification permission denied');
                }
            } catch (error) {
                console.error('❌ Push notification setup failed:', error);
            }
        }
    }

    async subscribeToPushNotifications() {
        try {
            const registration = await navigator.serviceWorker.ready;
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(
                    'your-vapid-public-key-here' // Replace with actual VAPID key
                )
            });
            
            console.log('📡 Push subscription created:', subscription);
            
            // Send subscription to server
            await this.sendSubscriptionToServer(subscription);
            
        } catch (error) {
            console.error('❌ Push subscription failed:', error);
        }
    }

    setupPeriodicSync() {
        // Setup periodic background sync for data updates
        navigator.serviceWorker.ready.then(registration => {
            if ('periodicSync' in registration) {
                registration.periodicSync.register('era-audit-data-sync', {
                    minInterval: 24 * 60 * 60 * 1000 // 24 hours
                }).then(() => {
                    console.log('🔄 Periodic sync registered');
                }).catch(error => {
                    console.log('❌ Periodic sync failed:', error);
                });
            }
        });
    }

    setupPerformanceMonitoring() {
        // Monitor app performance
        if ('PerformanceObserver' in window) {
            // Monitor largest contentful paint
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                console.log('📊 LCP:', lastEntry.startTime);
                this.trackPerformance('lcp', lastEntry.startTime);
            });
            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });

            // Monitor first input delay
            const fidObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach(entry => {
                    console.log('📊 FID:', entry.processingStart - entry.startTime);
                    this.trackPerformance('fid', entry.processingStart - entry.startTime);
                });
            });
            fidObserver.observe({ entryTypes: ['first-input'] });
        }
    }

    initializeUI() {
        // Create PWA status indicators
        this.createStatusBar();
        this.createInstallButton();
        this.createUpdateNotification();
        
        // Update initial connection status
        this.updateConnectionStatus();
    }

    createStatusBar() {
        const statusBar = document.createElement('div');
        statusBar.id = 'pwa-status-bar';
        statusBar.className = 'pwa-status-bar';
        statusBar.innerHTML = `
            <div class="pwa-status-content">
                <span id="connection-indicator" class="connection-indicator">
                    <i class="mdi mdi-wifi"></i>
                </span>
                <span id="sync-indicator" class="sync-indicator" style="display: none;">
                    <i class="mdi mdi-sync"></i> Syncing...
                </span>
                <span id="offline-count" class="offline-count" style="display: none;">
                    <i class="mdi mdi-cloud-off-outline"></i> 
                    <span class="count">0</span> offline items
                </span>
            </div>
        `;
        
        document.body.appendChild(statusBar);
    }

    createInstallButton() {
        const installButton = document.createElement('button');
        installButton.id = 'pwa-install-button';
        installButton.className = 'btn btn-primary pwa-install-button';
        installButton.style.display = 'none';
        installButton.innerHTML = `
            <i class="mdi mdi-download"></i> Install App
        `;
        
        installButton.addEventListener('click', () => {
            this.promptInstall();
        });
        
        // Add to navbar or suitable location
        const navbar = document.querySelector('.navbar-nav-right');
        if (navbar) {
            const li = document.createElement('li');
            li.className = 'nav-item';
            li.appendChild(installButton);
            navbar.appendChild(li);
        }
    }

    createUpdateNotification() {
        const updateNotification = document.createElement('div');
        updateNotification.id = 'pwa-update-notification';
        updateNotification.className = 'pwa-update-notification';
        updateNotification.style.display = 'none';
        updateNotification.innerHTML = `
            <div class="update-content">
                <div class="update-message">
                    <i class="mdi mdi-update"></i>
                    <span>A new version is available!</span>
                </div>
                <div class="update-actions">
                    <button id="update-now" class="btn btn-sm btn-primary">Update Now</button>
                    <button id="update-later" class="btn btn-sm btn-secondary">Later</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(updateNotification);
        
        // Add event listeners
        document.getElementById('update-now').addEventListener('click', () => {
            this.applyUpdate();
        });
        
        document.getElementById('update-later').addEventListener('click', () => {
            this.hideUpdateNotification();
        });
    }

    showInstallButton() {
        const button = document.getElementById('pwa-install-button');
        if (button) {
            button.style.display = 'block';
        }
    }

    hideInstallButton() {
        const button = document.getElementById('pwa-install-button');
        if (button) {
            button.style.display = 'none';
        }
    }

    async promptInstall() {
        if (this.installPrompt) {
            try {
                const result = await this.installPrompt.prompt();
                console.log('📱 Install prompt result:', result.outcome);
                
                if (result.outcome === 'accepted') {
                    this.trackEvent('pwa_install_accepted');
                } else {
                    this.trackEvent('pwa_install_dismissed');
                }
                
                this.installPrompt = null;
                this.hideInstallButton();
            } catch (error) {
                console.error('❌ Install prompt failed:', error);
            }
        }
    }

    showUpdateAvailableNotification() {
        const notification = document.getElementById('pwa-update-notification');
        if (notification) {
            notification.style.display = 'block';
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                this.hideUpdateNotification();
            }, 10000);
        }
    }

    hideUpdateNotification() {
        const notification = document.getElementById('pwa-update-notification');
        if (notification) {
            notification.style.display = 'none';
        }
    }

    async applyUpdate() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration && registration.waiting) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                this.hideUpdateNotification();
                this.showNotification('Updating app...', 'info');
            }
        }
    }

    updateConnectionStatus() {
        const indicator = document.getElementById('connection-indicator');
        if (indicator) {
            if (this.isOnline) {
                indicator.innerHTML = '<i class="mdi mdi-wifi text-success"></i>';
                indicator.title = 'Online';
            } else {
                indicator.innerHTML = '<i class="mdi mdi-wifi-off text-danger"></i>';
                indicator.title = 'Offline';
            }
        }
    }

    handleOnline() {
        console.log('🌐 Back online');
        this.showNotification('Back online! Syncing data...', 'success');
        this.triggerSync();
    }

    handleOffline() {
        console.log('📴 Gone offline');
        this.showNotification('Offline mode active. Data will sync when reconnected.', 'warning');
    }

    async triggerSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('era-audit-sync');
                this.showSyncIndicator();
            } catch (error) {
                console.error('❌ Background sync failed:', error);
            }
        }
    }

    showSyncIndicator() {
        const indicator = document.getElementById('sync-indicator');
        if (indicator) {
            indicator.style.display = 'inline-block';
            this.syncInProgress = true;
        }
    }

    hideSyncIndicator() {
        const indicator = document.getElementById('sync-indicator');
        if (indicator) {
            indicator.style.display = 'none';
            this.syncInProgress = false;
        }
    }

    handleSyncComplete(count) {
        this.hideSyncIndicator();
        if (count > 0) {
            this.showNotification(`Synced ${count} items successfully!`, 'success');
        }
    }

    async checkConnectivity() {
        try {
            const response = await fetch('/api/ping', {
                method: 'HEAD',
                cache: 'no-cache'
            });
            
            const isOnline = response.ok;
            if (isOnline !== this.isOnline) {
                this.isOnline = isOnline;
                this.updateConnectionStatus();
            }
        } catch (error) {
            if (this.isOnline) {
                this.isOnline = false;
                this.updateConnectionStatus();
                this.handleOffline();
            }
        }
    }

    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `pwa-notification pwa-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="mdi ${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Add close functionality
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
        
        // Auto remove
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'mdi-check-circle',
            error: 'mdi-alert-circle',
            warning: 'mdi-alert',
            info: 'mdi-information'
        };
        return icons[type] || icons.info;
    }

    // Analytics and tracking
    trackEvent(eventName, properties = {}) {
        console.log('📊 Event tracked:', eventName, properties);
        
        // Send to analytics service
        if (window.gtag) {
            window.gtag('event', eventName, properties);
        }
        
        // Send to custom analytics
        this.sendAnalytics(eventName, properties);
    }

    trackPerformance(metric, value) {
        console.log(`📊 Performance ${metric}:`, value);
        
        // Send performance data
        this.sendAnalytics(`performance_${metric}`, { value });
    }

    async sendAnalytics(event, data) {
        try {
            await fetch('/api/analytics', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event,
                    data,
                    timestamp: Date.now(),
                    userAgent: navigator.userAgent
                })
            });
        } catch (error) {
            console.log('Analytics failed:', error);
        }
    }

    async sendSubscriptionToServer(subscription) {
        try {
            await fetch('/api/push-subscription', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(subscription)
            });
        } catch (error) {
            console.error('Failed to send subscription:', error);
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Public API methods
    async clearCache() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration && registration.active) {
                const messageChannel = new MessageChannel();
                
                return new Promise((resolve) => {
                    messageChannel.port1.onmessage = (event) => {
                        resolve(event.data);
                    };
                    
                    registration.active.postMessage(
                        { type: 'CLEAR_CACHE' },
                        [messageChannel.port2]
                    );
                });
            }
        }
    }

    async getVersion() {
        if ('serviceWorker' in navigator) {
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration && registration.active) {
                const messageChannel = new MessageChannel();
                
                return new Promise((resolve) => {
                    messageChannel.port1.onmessage = (event) => {
                        resolve(event.data.version);
                    };
                    
                    registration.active.postMessage(
                        { type: 'GET_VERSION' },
                        [messageChannel.port2]
                    );
                });
            }
        }
        return this.version;
    }
}

// Enhanced CSS for PWA components
const pwaStyles = `
<style id="pwa-professional-styles">
.pwa-status-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 0;
    z-index: 10001;
    font-size: 12px;
    display: none;
}

.pwa-status-content {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
}

.connection-indicator,
.sync-indicator,
.offline-count {
    display: flex;
    align-items: center;
    gap: 4px;
}

.pwa-install-button {
    border-radius: 20px;
    font-size: 12px;
    padding: 6px 12px;
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border: none;
    transition: transform 0.2s ease;
}

.pwa-install-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.pwa-update-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border: 1px solid #dee2e6;
    border-left: 4px solid #007bff;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    max-width: 350px;
    animation: slideInRight 0.3s ease;
}

.update-content {
    padding: 16px;
}

.update-message {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    font-weight: 500;
    color: #333;
}

.update-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.pwa-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 10000;
    max-width: 350px;
    animation: slideInRight 0.3s ease;
}

.pwa-notification-success {
    border-left: 4px solid #28a745;
}

.pwa-notification-error {
    border-left: 4px solid #dc3545;
}

.pwa-notification-warning {
    border-left: 4px solid #ffc107;
}

.pwa-notification-info {
    border-left: 4px solid #17a2b8;
}

.notification-content {
    display: flex;
    align-items: center;
    padding: 16px;
    gap: 12px;
}

.notification-close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    margin-left: auto;
    color: #6c757d;
}

.notification-close:hover {
    color: #333;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .pwa-notification,
    .pwa-update-notification {
        right: 10px;
        left: 10px;
        max-width: none;
    }
    
    .pwa-install-button {
        font-size: 11px;
        padding: 4px 8px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .pwa-notification,
    .pwa-update-notification {
        background: #343a40;
        color: #fff;
        border-color: #495057;
    }
    
    .update-message {
        color: #fff;
    }
    
    .notification-close {
        color: #adb5bd;
    }
    
    .notification-close:hover {
        color: #fff;
    }
}
</style>
`;

// Initialize Professional PWA Manager
document.addEventListener('DOMContentLoaded', () => {
    // Add PWA styles
    document.head.insertAdjacentHTML('beforeend', pwaStyles);
    
    // Initialize PWA manager
    window.eraPWA = new ProfessionalPWAManager();
    
    // Expose global functions
    window.installPWA = () => window.eraPWA.promptInstall();
    window.clearPWACache = () => window.eraPWA.clearCache();
    window.getPWAVersion = () => window.eraPWA.getVersion();
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProfessionalPWAManager;
}
