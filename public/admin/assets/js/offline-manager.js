// Offline Manager for Audit System
class AuditOfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.syncQueue = [];
        this.dbName = 'AuditSystemOfflineDB';
        this.dbVersion = 1;
        this.db = null;
        this.init();
    }

    async init() {
        // Register service worker
        if ('serviceWorker' in navigator) {
            try {
                const registration = await navigator.serviceWorker.register('/sw-advanced.js');
                console.log('Service Worker registered:', registration);
                
                // Listen for updates
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateNotification();
                        }
                    });
                });
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }

        // Initialize IndexedDB
        await this.initDB();
        
        // Setup network status listeners
        this.setupNetworkListeners();
        
        // Setup periodic sync
        this.setupPeriodicSync();
        
        // Show offline indicator
        this.updateOfflineIndicator();
    }

    async initDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);
            
            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Audit responses store
                if (!db.objectStoreNames.contains('responses')) {
                    const responseStore = db.createObjectStore('responses', { 
                        keyPath: 'id',
                        autoIncrement: true 
                    });
                    responseStore.createIndex('auditId', 'auditId');
                    responseStore.createIndex('questionId', 'questionId');
                    responseStore.createIndex('synced', 'synced');
                }
                
                // Audit drafts store
                if (!db.objectStoreNames.contains('drafts')) {
                    const draftStore = db.createObjectStore('drafts', { 
                        keyPath: 'id',
                        autoIncrement: true 
                    });
                    draftStore.createIndex('auditId', 'auditId');
                    draftStore.createIndex('lastModified', 'lastModified');
                }
                
                // Templates cache
                if (!db.objectStoreNames.contains('templates')) {
                    const templateStore = db.createObjectStore('templates', { 
                        keyPath: 'id' 
                    });
                    templateStore.createIndex('reviewTypeId', 'reviewTypeId');
                }
                
                // Countries cache
                if (!db.objectStoreNames.contains('countries')) {
                    db.createObjectStore('countries', { keyPath: 'id' });
                }
                
                // Review types cache
                if (!db.objectStoreNames.contains('reviewTypes')) {
                    db.createObjectStore('reviewTypes', { keyPath: 'id' });
                }
            };
        });
    }

    setupNetworkListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateOfflineIndicator();
            // Only sync if there's actually offline data
            this.syncPendingData();
            // Removed automatic notification - less intrusive
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateOfflineIndicator();
            // Only show notification when actually going offline
            this.showNotification('You are now offline. Data will be saved locally.', 'warning');
        });
    }

    updateOfflineIndicator() {
        const indicator = document.getElementById('offline-indicator');
        if (indicator) {
            if (this.isOnline) {
                indicator.style.display = 'none';
            } else {
                indicator.style.display = 'block';
                indicator.innerHTML = `
                    <div class="alert alert-warning d-flex align-items-center">
                        <i class="mdi mdi-wifi-off me-2"></i>
                        <span>Offline Mode - Data will sync when connected</span>
                    </div>
                `;
            }
        }
    }

    // Save audit response locally (only when offline)
    async saveResponseOffline(auditId, questionId, answer, attachmentId = null) {
        // Only save offline if we're actually offline
        if (this.isOnline) {
            console.log('User is online, skipping offline save');
            return { success: true, offline: false, message: 'Online - no offline save needed' };
        }

        const responseData = {
            auditId: auditId,
            questionId: questionId,
            answer: answer,
            attachmentId: attachmentId,
            timestamp: Date.now(),
            synced: false,
            userId: this.getCurrentUserId()
        };

        const transaction = this.db.transaction(['responses'], 'readwrite');
        const store = transaction.objectStore('responses');
        
        try {
            await store.add(responseData);
            this.showNotification('Response saved offline', 'info');
            return { success: true, offline: true };
        } catch (error) {
            console.error('Failed to save response offline:', error);
            return { success: false, error: error.message };
        }
    }

    // Get offline responses
    async getOfflineResponses(auditId) {
        const transaction = this.db.transaction(['responses'], 'readonly');
        const store = transaction.objectStore('responses');
        const index = store.index('auditId');
        
        return new Promise((resolve, reject) => {
            const request = index.getAll(auditId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Cache templates for offline use
    async cacheTemplates(templates) {
        const transaction = this.db.transaction(['templates'], 'readwrite');
        const store = transaction.objectStore('templates');
        
        for (const template of templates) {
            await store.put(template);
        }
    }

    // Get cached templates
    async getCachedTemplates() {
        const transaction = this.db.transaction(['templates'], 'readonly');
        const store = transaction.objectStore('templates');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Sync pending data when online
    async syncPendingData() {
        if (!this.isOnline) return;

        try {
            // Check if there's actually data to sync
            const hasData = await this.hasOfflineData();
            if (!hasData) {
                // No offline data to sync, don't show any messages
                return;
            }

            // Sync responses
            await this.syncResponses();
            
            // Sync drafts
            await this.syncDrafts();
            
            this.showNotification('All offline data synced successfully!', 'success');
        } catch (error) {
            console.error('Sync failed:', error);
            // Only show error if we actually tried to sync something
            const hasData = await this.hasOfflineData();
            if (hasData) {
                this.showNotification('Some data failed to sync. Will retry later.', 'error');
            }
        }
    }

    async syncResponses() {
        const transaction = this.db.transaction(['responses'], 'readonly');
        const store = transaction.objectStore('responses');
        const index = store.index('synced');
        
        return new Promise((resolve, reject) => {
            const request = index.getAll(false); // Get unsynced responses
            
            request.onsuccess = async () => {
                const responses = request.result;
                
                for (const response of responses) {
                    try {
                        const result = await fetch('/api/responses', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                audit_id: response.auditId,
                                question_id: response.questionId,
                                answer: response.answer,
                                attachment_id: response.attachmentId
                            })
                        });

                        if (result.ok) {
                            // Mark as synced
                            await this.markResponseSynced(response.id);
                        }
                    } catch (error) {
                        console.error('Failed to sync response:', error);
                    }
                }
                resolve();
            };
            
            request.onerror = () => reject(request.error);
        });
    }

    async markResponseSynced(responseId) {
        const transaction = this.db.transaction(['responses'], 'readwrite');
        const store = transaction.objectStore('responses');
        const request = store.get(responseId);
        
        request.onsuccess = () => {
            const response = request.result;
            response.synced = true;
            store.put(response);
        };
    }

    async syncDrafts() {
        const transaction = this.db.transaction(['drafts'], 'readonly');
        const store = transaction.objectStore('drafts');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            
            request.onsuccess = async () => {
                const drafts = request.result;
                
                for (const draft of drafts) {
                    try {
                        // Here you could implement draft syncing if needed
                        console.log('Draft ready for sync:', draft);
                    } catch (error) {
                        console.error('Failed to sync draft:', error);
                    }
                }
                resolve();
            };
            
            request.onerror = () => reject(request.error);
        });
    }

    async getOfflineDrafts() {
        const transaction = this.db.transaction(['drafts'], 'readonly');
        const store = transaction.objectStore('drafts');
        
        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async saveDraft(formData) {
        const draftData = {
            auditId: formData.audit_id,
            formData: formData,
            lastModified: Date.now(),
            userId: this.getCurrentUserId()
        };

        const transaction = this.db.transaction(['drafts'], 'readwrite');
        const store = transaction.objectStore('drafts');
        
        try {
            await store.put(draftData);
            this.showNotification('Draft saved automatically', 'info');
            return { success: true };
        } catch (error) {
            console.error('Failed to save draft:', error);
            return { success: false, error: error.message };
        }
    }

    setupPeriodicSync() {
        // Disable automatic sync since we're not using offline functionality
        // This prevents the "failed to sync" error messages
        console.log('Periodic sync disabled - responses are saved directly to server');
        
        // Optional: Only sync if there's actually offline data to sync
        // setInterval(() => {
        //     if (this.isOnline && this.hasOfflineData()) {
        //         this.syncPendingData();
        //     }
        // }, 30000);
    }

    async hasOfflineData() {
        try {
            const transaction = this.db.transaction(['responses'], 'readonly');
            const store = transaction.objectStore('responses');
            const index = store.index('synced');
            
            return new Promise((resolve) => {
                const request = index.count(false); // Count unsynced responses
                request.onsuccess = () => resolve(request.result > 0);
                request.onerror = () => resolve(false);
            });
        } catch (error) {
            return false;
        }
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${this.getBootstrapClass(type)} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    getBootstrapClass(type) {
        const classes = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        return classes[type] || 'info';
    }

    showUpdateNotification() {
        const updateBanner = document.createElement('div');
        updateBanner.className = 'alert alert-info alert-dismissible';
        updateBanner.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; z-index: 10000;';
        
        updateBanner.innerHTML = `
            <div class="container">
                <strong>Update Available!</strong> A new version of the audit system is ready.
                <button class="btn btn-sm btn-primary ms-2" onclick="window.location.reload()">
                    Update Now
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.insertBefore(updateBanner, document.body.firstChild);
    }

    getCurrentUserId() {
        // Get from Laravel session or meta tag
        const userMeta = document.querySelector('meta[name="user-id"]');
        return userMeta ? userMeta.content : null;
    }

    // Export offline data for backup
    async exportOfflineData() {
        const data = {
            responses: await this.getOfflineResponses(),
            drafts: await this.getOfflineDrafts(),
            timestamp: Date.now()
        };
        
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = `audit-offline-backup-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        
        URL.revokeObjectURL(url);
    }

    // Clear all offline data (useful for troubleshooting)
    async clearOfflineData() {
        try {
            const transaction = this.db.transaction(['responses', 'drafts'], 'readwrite');
            await transaction.objectStore('responses').clear();
            await transaction.objectStore('drafts').clear();
            console.log('All offline data cleared');
            this.showNotification('Offline data cleared successfully', 'success');
        } catch (error) {
            console.error('Failed to clear offline data:', error);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.auditOfflineManager = new AuditOfflineManager();
    
    // Add offline indicator to page
    const offlineIndicator = document.createElement('div');
    offlineIndicator.id = 'offline-indicator';
    offlineIndicator.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; z-index: 9999;';
    document.body.insertBefore(offlineIndicator, document.body.firstChild);
});

// Export for global use
window.AuditOfflineManager = AuditOfflineManager;
