/**
 * ERPSaaS PWA & Offline Synchronization Engine
 * Handles service worker registration, offline draft queuing,
 * background reconnect synchronization, and cross-device pulse polling.
 */
(function () {
    const STORAGE_KEY = 'erpsaas_offline_drafts';
    const PULSE_INTERVAL_MS = 60000; // 60 seconds

    window.ErpSync = {
        isOnline: navigator.onLine,
        isSyncing: false,

        init() {
            this.registerServiceWorker();
            this.bindNetworkEvents();
            this.updateUiBanner();
            this.startPulsePolling();

            // Attempt sync on load if online and drafts exist
            if (this.isOnline && this.getDrafts().length > 0) {
                this.syncDrafts();
            }
        },

        registerServiceWorker() {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then((reg) => {
                            console.log('[PWA] Service Worker registered:', reg.scope);
                        })
                        .catch((err) => {
                            console.warn('[PWA] Service Worker registration failed:', err);
                        });
                });
            }
        },

        bindNetworkEvents() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.updateUiBanner();
                console.log('[PWA] Back online. Synchronizing local drafts...');
                this.syncDrafts();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
                this.updateUiBanner();
                console.warn('[PWA] Offline mode engaged. All drafts will be stored locally.');
            });
        },

        getDrafts() {
            try {
                const stored = localStorage.getItem(STORAGE_KEY);
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        },

        saveDraft(type, payload) {
            const drafts = this.getDrafts();
            const draft = {
                id: 'draft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                type: type, // 'SALES_ORDER', 'LEAD', 'EXPENSE', 'NOTE'
                payload: payload,
                createdAt: new Date().toISOString(),
                synced: false
            };

            drafts.push(draft);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(drafts));
            this.updateUiBanner();

            // If online, immediately attempt to send
            if (this.isOnline) {
                this.syncDrafts();
            }

            return draft;
        },

        removeDraft(id) {
            const drafts = this.getDrafts().filter(d => d.id !== id);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(drafts));
            this.updateUiBanner();
        },

        clearDrafts() {
            localStorage.removeItem(STORAGE_KEY);
            this.updateUiBanner();
        },

        async syncDrafts() {
            const drafts = this.getDrafts();
            if (drafts.length === 0 || this.isSyncing || !this.isOnline) {
                return;
            }

            this.isSyncing = true;
            this.updateUiBanner();

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch('/api/sync/offline-drafts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token || ''
                    },
                    body: JSON.stringify({ drafts: drafts })
                });

                if (response.ok) {
                    const result = await response.json();
                    if (result.success) {
                        this.clearDrafts();
                        console.log('[PWA] Successfully synchronized ' + (result.synced_count || drafts.length) + ' offline drafts.');
                        window.dispatchEvent(new CustomEvent('pwa:synced', { detail: result }));
                    }
                }
            } catch (err) {
                console.warn('[PWA] Draft synchronization deferred:', err);
            } finally {
                this.isSyncing = false;
                this.updateUiBanner();
            }
        },

        startPulsePolling() {
            setInterval(async () => {
                if (!this.isOnline || document.hidden) {
                    return;
                }

                try {
                    const res = await fetch('/api/sync/pulse', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const pulse = await res.json();
                        window.dispatchEvent(new CustomEvent('pwa:pulse', { detail: pulse }));
                    }
                } catch (e) {
                    // Suppress pulse error on transient connection hiccup
                }
            }, PULSE_INTERVAL_MS);
        },

        updateUiBanner() {
            const banner = document.getElementById('pwa-connection-banner');
            const queueBadge = document.getElementById('pwa-queue-count');
            const drafts = this.getDrafts();

            if (!banner) return;

            if (!this.isOnline) {
                banner.classList.remove('hidden');
                banner.className = 'w-full bg-amber-500 text-neutral-950 px-4 py-1.5 text-xs font-bold flex items-center justify-between shadow-md transition-all';
                banner.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-neutral-950 animate-ping"></span>
                        <span>OFFLINE MODE &mdash; Drafts saved locally on this device (${drafts.length} pending sync)</span>
                    </div>
                    <button onclick="window.ErpSync.syncDrafts()" class="px-2.5 py-0.5 rounded bg-neutral-950 text-white text-[10px] font-bold">Retry Sync</button>
                `;
            } else if (drafts.length > 0) {
                banner.classList.remove('hidden');
                banner.className = 'w-full bg-[#D7FF53] text-neutral-950 px-4 py-1.5 text-xs font-bold flex items-center justify-between shadow-md transition-all';
                banner.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                        <span>Connected &mdash; ${drafts.length} draft(s) ready to sync</span>
                    </div>
                    <button onclick="window.ErpSync.syncDrafts()" class="px-2.5 py-0.5 rounded bg-neutral-950 text-white text-[10px] font-bold">Sync Now</button>
                `;
            } else {
                banner.classList.add('hidden');
            }

            if (queueBadge) {
                queueBadge.innerText = drafts.length;
            }
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => window.ErpSync.init());
    } else {
        window.ErpSync.init();
    }
})();
