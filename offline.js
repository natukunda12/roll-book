// ============================================================
// Cashbook Pro — Offline Manager
// Handles IndexedDB storage + sync when back online
// ============================================================

const OfflineDB = (() => {
  const DB_NAME    = 'cashbook_offline';
  const DB_VERSION = 2;
  const STORES     = {
    queue:        'sync_queue',       // pending actions
    transactions: 'transactions',     // cached transactions
    books:        'books',            // cached books
    categories:   'categories',       // cached categories
    clients:      'clients',          // cached clients
    meta:         'meta',             // last sync time etc.
  };

  let db = null;

  function open() {
    return new Promise((resolve, reject) => {
      if (db) return resolve(db);
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = e => {
        const d = e.target.result;
        if (!d.objectStoreNames.contains(STORES.queue))
          d.createObjectStore(STORES.queue, { keyPath: 'client_key' });
        if (!d.objectStoreNames.contains(STORES.transactions))
          d.createObjectStore(STORES.transactions, { keyPath: 'id' });
        if (!d.objectStoreNames.contains(STORES.books))
          d.createObjectStore(STORES.books, { keyPath: 'id' });
        if (!d.objectStoreNames.contains(STORES.categories))
          d.createObjectStore(STORES.categories, { keyPath: 'id' });
        if (!d.objectStoreNames.contains(STORES.clients))
          d.createObjectStore(STORES.clients, { keyPath: 'id' });
        if (!d.objectStoreNames.contains(STORES.meta))
          d.createObjectStore(STORES.meta, { keyPath: 'key' });
      };
      req.onsuccess = e => { db = e.target.result; resolve(db); };
      req.onerror   = e => reject(e.target.error);
    });
  }

  function tx(storeName, mode = 'readonly') {
    return db.transaction([storeName], mode).objectStore(storeName);
  }

  function put(storeName, obj) {
    return open().then(d => new Promise((res, rej) => {
      const r = d.transaction([storeName], 'readwrite').objectStore(storeName).put(obj);
      r.onsuccess = () => res(r.result);
      r.onerror   = e => rej(e.target.error);
    }));
  }

  function getAll(storeName) {
    return open().then(d => new Promise((res, rej) => {
      const r = d.transaction([storeName], 'readonly').objectStore(storeName).getAll();
      r.onsuccess = () => res(r.result);
      r.onerror   = e => rej(e.target.error);
    }));
  }

  function del(storeName, key) {
    return open().then(d => new Promise((res, rej) => {
      const r = d.transaction([storeName], 'readwrite').objectStore(storeName).delete(key);
      r.onsuccess = () => res();
      r.onerror   = e => rej(e.target.error);
    }));
  }

  function clearStore(storeName) {
    return open().then(d => new Promise((res, rej) => {
      const r = d.transaction([storeName], 'readwrite').objectStore(storeName).clear();
      r.onsuccess = () => res();
      r.onerror   = e => rej(e.target.error);
    }));
  }

  return { open, put, getAll, del, clearStore, STORES };
})();

// ── Unique key generator ──────────────────────────────────────
function genKey() {
  return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
}

// ── Queue an offline action ───────────────────────────────────
async function queueOfflineAction(action, payload) {
  const item = {
    client_key: genKey(),
    action,
    payload,
    queued_at: new Date().toISOString(),
    synced: false,
  };
  await OfflineDB.put(OfflineDB.STORES.queue, item);
  updateSyncBadge();
  return item.client_key;
}

// ── Count pending ─────────────────────────────────────────────
async function getPendingCount() {
  const all = await OfflineDB.getAll(OfflineDB.STORES.queue);
  return all.filter(i => !i.synced).length;
}

// ── Sync all pending to server ────────────────────────────────
async function syncToServer() {
  if (!navigator.onLine) return { synced: 0, offline: true };

  const all     = await OfflineDB.getAll(OfflineDB.STORES.queue);
  const pending = all.filter(i => !i.synced);
  if (!pending.length) return { synced: 0, nothing: true };

  setSyncStatus('syncing');

  try {
    const res = await fetch('sync.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ items: pending }),
    });

    if (!res.ok) throw new Error('Server error ' + res.status);
    const data = await res.json();

    // Mark synced items
    for (const item of pending) {
      const failed = (data.errors || []).find(e => e.key === item.client_key);
      if (!failed) {
        item.synced = true;
        await OfflineDB.put(OfflineDB.STORES.queue, item);
      }
    }

    setSyncStatus('synced');
    updateSyncBadge();
    return data;
  } catch (err) {
    setSyncStatus('error');
    console.warn('Sync failed:', err);
    return { synced: 0, error: err.message };
  }
}

// ── Auto-sync when online ─────────────────────────────────────
window.addEventListener('online',  () => { setSyncStatus('online');  syncToServer().then(showSyncResult); });
window.addEventListener('offline', () => { setSyncStatus('offline'); });

// ── UI: sync status bar ───────────────────────────────────────
function ensureSyncBar() {
  if (document.getElementById('syncBar')) return;
  const bar = document.createElement('div');
  bar.id = 'syncBar';
  bar.style.cssText = `
    position:fixed;bottom:0;left:0;right:0;z-index:800;
    padding:10px 20px;padding-bottom:calc(10px + env(safe-area-inset-bottom));
    font-size:13px;font-weight:500;font-family:'DM Sans',sans-serif;
    display:none;align-items:center;justify-content:space-between;gap:12px;
    transition:all .3s ease;
  `;
  document.body.appendChild(bar);
}

function setSyncStatus(state) {
  ensureSyncBar();
  const bar = document.getElementById('syncBar');
  const configs = {
    offline: { bg:'#1e2333', border:'#f43f5e', color:'#fca5a5', icon:'📵', text:'You are offline — changes will sync when reconnected', btn:false },
    syncing: { bg:'#1e2333', border:'#3b82f6', color:'#93c5fd', icon:'🔄', text:'Syncing offline changes…', btn:false },
    synced:  { bg:'#1e2333', border:'#10b981', color:'#6ee7b7', icon:'✅', text:'All changes synced successfully', btn:false, auto:3000 },
    error:   { bg:'#1e2333', border:'#f43f5e', color:'#fca5a5', icon:'⚠️', text:'Sync failed — will retry when online', btn:'Retry', btnFn:'syncToServer()' },
    online:  { bg:'#1e2333', border:'#10b981', color:'#6ee7b7', icon:'🌐', text:'Back online — syncing…', btn:false, auto:2000 },
  };
  const cfg = configs[state];
  if (!cfg) return;
  bar.style.background    = cfg.bg;
  bar.style.borderTop     = `2px solid ${cfg.border}`;
  bar.style.color         = cfg.color;
  bar.style.display       = 'flex';
  bar.innerHTML = `
    <span>${cfg.icon} ${cfg.text}</span>
    ${cfg.btn ? `<button onclick="${cfg.btnFn}" style="padding:5px 12px;background:${cfg.border};color:#fff;border:none;border-radius:7px;font-size:12px;cursor:pointer;font-family:inherit">${cfg.btn}</button>` : ''}
  `;
  if (cfg.auto) setTimeout(() => { bar.style.display = 'none'; }, cfg.auto);
}

// ── Sync badge (pending count) ────────────────────────────────
async function updateSyncBadge() {
  const count = await getPendingCount();
  let badge = document.getElementById('syncBadge');
  if (!badge) {
    badge = document.createElement('div');
    badge.id = 'syncBadge';
    badge.style.cssText = `
      position:fixed;bottom:70px;right:16px;z-index:700;
      background:#3b82f6;color:#fff;border-radius:20px;
      font-size:12px;font-weight:500;padding:6px 14px;
      cursor:pointer;display:none;align-items:center;gap:6px;
      box-shadow:0 4px 16px rgba(59,130,246,.4);font-family:'DM Sans',sans-serif;
    `;
    badge.onclick = () => syncToServer().then(showSyncResult);
    document.body.appendChild(badge);
  }
  if (count > 0) {
    badge.innerHTML = `🔄 ${count} pending — tap to sync`;
    badge.style.display = 'flex';
  } else {
    badge.style.display = 'none';
  }
}

function showSyncResult(result) {
  if (result && result.synced > 0) {
    setSyncStatus('synced');
    setTimeout(() => location.reload(), 1500);
  }
}

// ── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await OfflineDB.open();
  if (!navigator.onLine) setSyncStatus('offline');
  updateSyncBadge();
  // Auto-sync if online and has pending
  if (navigator.onLine) {
    const count = await getPendingCount();
    if (count > 0) syncToServer().then(showSyncResult);
  }
});

// Register background sync if supported
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.ready.then(reg => {
    if ('sync' in reg) {
      window.addEventListener('online', () => reg.sync.register('sync-transactions').catch(()=>{}));
    }
  });
  // Listen for SW messages
  navigator.serviceWorker.addEventListener('message', e => {
    if (e.data && e.data.type === 'SYNC_START') syncToServer();
  });
}
