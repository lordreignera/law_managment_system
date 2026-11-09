(function () {
    'use strict';

    var deferredInstallPrompt = null;
    var installDismissKey = 'kfms-pwa-install-dismissed';

    var isInstalled = function () {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    };

    var isIos = function () {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent);
    };

    var isAndroid = function () {
        return /android/i.test(window.navigator.userAgent);
    };

    var installPanel = function () {
        return document.querySelector('[data-kfms-pwa-install]');
    };

    var showInstallPanel = function () {
        var panel = installPanel();
        if (panel && window.isSecureContext && !isInstalled() && !window.sessionStorage.getItem(installDismissKey)) {
            panel.hidden = false;
        }
    };

    var dismissInstallPanel = function () {
        var panel = installPanel();
        if (panel) {
            panel.hidden = true;
        }
        window.sessionStorage.setItem(installDismissKey, '1');
    };

    var decodeVapidKey = function (encodedKey) {
        var padding = '='.repeat((4 - encodedKey.length % 4) % 4);
        var base64 = (encodedKey + padding).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(window.atob(base64), function (character) { return character.charCodeAt(0); });
    };

    var sendSubscription = function (url, method, subscription) {
        return fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(subscription),
        }).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () { return {}; }).then(function (result) {
                    throw new Error(result.message || 'Could not update phone alert settings.');
                });
            }
            return response;
        });
    };

    var setPushStatus = function (button, message) {
        var status = button.parentElement?.querySelector('[data-kfms-push-status]');
        if (status) {
            status.textContent = message;
        }
    };

    var setPushLabel = function (button, label) {
        var target = button.querySelector('[data-kfms-push-label]');
        if (target) {
            target.textContent = label;
        }
    };

    var setupPushButton = function (button) {
        if (button.dataset.kfmsPushBound === '1') {
            return;
        }

        button.dataset.kfmsPushBound = '1';
        button.addEventListener('click', async function () {
            if (!window.isSecureContext) {
                setPushStatus(button, 'Phone alerts require HTTPS or localhost.');
                return;
            }
            if (!button.dataset.vapidPublicKey || !('PushManager' in window) || !('Notification' in window) || !('serviceWorker' in navigator)) {
                setPushStatus(button, 'Phone alerts need VAPID setup and push support.');
                return;
            }

            button.disabled = true;
            try {
                var registration = await navigator.serviceWorker.ready;
                var subscription = await registration.pushManager.getSubscription();

                if (button.dataset.subscribed === 'true' && subscription) {
                    await sendSubscription(button.dataset.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
                    await subscription.unsubscribe();
                    button.dataset.subscribed = 'false';
                    setPushLabel(button, 'Enable phone alerts');
                    setPushStatus(button, 'Phone alerts disabled on this device.');
                    return;
                }

                var permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    throw new Error('Allow notifications in your browser settings to receive phone alerts.');
                }

                subscription = subscription || await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: decodeVapidKey(button.dataset.vapidPublicKey),
                });

                await sendSubscription(button.dataset.subscribeUrl, 'POST', subscription.toJSON());
                button.dataset.subscribed = 'true';
                setPushLabel(button, 'Phone alerts on');
                setPushStatus(button, 'Phone alerts enabled on this device.');
            } catch (error) {
                setPushStatus(button, error.message || 'Could not enable phone alerts.');
            } finally {
                button.disabled = false;
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        if ('serviceWorker' in navigator && window.isSecureContext) {
            navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            }).then(function (registration) {
                registration.update().catch(function () {});
            }).catch(function () {});
        }

        document.querySelectorAll('[data-kfms-push-notifications]').forEach(setupPushButton);

        document.querySelectorAll('[data-kfms-pwa-dismiss]').forEach(function (button) {
            button.addEventListener('click', dismissInstallPanel);
        });

        var action = document.querySelector('[data-kfms-pwa-action]');
        action?.addEventListener('click', async function () {
            var message = document.querySelector('[data-kfms-pwa-message]');
            if (deferredInstallPrompt) {
                deferredInstallPrompt.prompt();
                await deferredInstallPrompt.userChoice;
                deferredInstallPrompt = null;
                dismissInstallPanel();
                return;
            }
            if (isIos() && message) {
                message.textContent = 'In Safari, tap Share, then choose Add to Home Screen.';
                return;
            }
            if (isAndroid() && message) {
                message.textContent = 'Open your browser menu and choose Add to Home screen.';
            }
        });

        if (window.isSecureContext && (isIos() || isAndroid()) && !isInstalled()) {
            window.setTimeout(showInstallPanel, 2500);
        }
    });

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallPanel();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        dismissInstallPanel();
    });
})();
