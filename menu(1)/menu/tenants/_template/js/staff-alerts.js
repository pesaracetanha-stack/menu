'use strict';
/* ════════════════════════════════════════════════════════════
   staff-alerts.js — Real-time Audio/Visual Notifications for POS & KDS
   ════════════════════════════════════════════════════════════ */
let __lastOrdersSnapshot = null;
let __lastReadySnapshot = null;

function playStaffAlert(type) {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        
        if (type === 'new_order') {
            // صدای زنگ دو ضربه‌ای تند برای سفارش جدید
            [0, 0.2].forEach(delay => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'square';
                osc.frequency.setValueAtTime(880, ctx.currentTime + delay); 
                gain.gain.setValueAtTime(0, ctx.currentTime + delay);
                gain.gain.linearRampToValueAtTime(0.2, ctx.currentTime + delay + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.3);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + delay);
                osc.stop(ctx.currentTime + delay + 0.4);
            });
        } else if (type === 'ready') {
            // صدای زنگ ملایم‌تر برای آماده شدن غذا
            [0, 0.3, 0.6].forEach(delay => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(1046.50, ctx.currentTime + delay); // C6
                gain.gain.setValueAtTime(0, ctx.currentTime + delay);
                gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + delay + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.5);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(ctx.currentTime + delay);
                osc.stop(ctx.currentTime + delay + 0.6);
            });
        }
    } catch (e) {
        console.log('Audio blocked by browser. User interaction needed first.');
    }
}

function showStaffToast(title, msg, type) {
    // ایجاد یک توست بزرگ و واضح در بالای صفحه
    const el = document.createElement('div');
    el.className = 'staff-alert-banner ' + (type === 'new' ? 'alert-new' : 'alert-ready');
    el.innerHTML = `<div class="sa-icon">${type === 'new' ? '🔔' : '🍳'}</div>
                    <div class="sa-text"><b>${title}</b><span>${msg}</span></div>`;
    document.body.appendChild(el);
    
    // انیمیشن ورود
    setTimeout(() => el.classList.add('show'), 10);
    
    // حذف خودکار بعد از ۱۰ ثانیه
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 300);
    }, 10000);
}

function checkStaffAlerts() {
    if (!Store.db || !Store.db.orders) return;
    
    // اگر اولین باره که لود میشه، فقط اسنپ‌شات بگیر و برو
    const isCashier = App.role === 'cashier';
    const isKds = App.role === 'kds';
    
    if (!isCashier && !isKds) return;

    const currentOrders = Store.db.orders;
    
    // اگر اسنپ‌شات نداریم، یعنی تازه لود شدیم
    if (!__lastOrdersSnapshot) {
        __lastOrdersSnapshot = currentOrders.map(o => o.id);
        __lastReadySnapshot = currentOrders.filter(o => o.ready).map(o => o.id);
        return; 
    }

    if (isKds) {
        // در آشپزخانه: اگر سفارش جدیدی با وضعیت preparing اضافه شده
        const prepOrders = currentOrders.filter(o => o.status === 'preparing' && !o.ready);
        const prepIds = prepOrders.map(o => o.id);
        
        prepIds.forEach(id => {
            if (!__lastOrdersSnapshot.includes(id)) {
                const order = prepOrders.find(o => o.id === id);
                playStaffAlert('new_order');
                showStaffToast('سفارش جدید در آشپزخانه!', `سفارش #${fa(order.num)} اضافه شد.`, 'new');
            }
        });
        
        // آپدیت اسنپ‌شات
        __lastOrdersSnapshot = currentOrders.map(o => o.id);
    } 
    else if (isCashier) {
        // در صندوق: اگر سفارش آنلاین جدیدی اومده (status pending)
        // یا اگر آشپزخانه سفارشی رو "آماده" کرده (ready = true)
        
        const pendingOrders = currentOrders.filter(o => o.status === 'pending');
        const pendingIds = pendingOrders.map(o => o.id);
        
        pendingIds.forEach(id => {
            if (!__lastOrdersSnapshot.includes(id)) {
                const order = pendingOrders.find(o => o.id === id);
                playStaffAlert('new_order');
                showStaffToast('سفارش آنلاین جدید!', `سفارش #${fa(order.num)} در انتظار پرداخت/تایید است.`, 'new');
            }
        });

        const readyOrders = currentOrders.filter(o => o.ready && o.status === 'preparing');
        const readyIds = readyOrders.map(o => o.id);
        
        readyIds.forEach(id => {
            if (!__lastReadySnapshot.includes(id)) {
                const order = readyOrders.find(o => o.id === id);
                playStaffAlert('ready');
                showStaffToast('غذا آماده تحویل است!', `سفارش #${fa(order.num)} توسط آشپزخانه آماده شد.`, 'ready');
            }
        });

        // آپدیت اسنپ‌شات‌ها
        __lastOrdersSnapshot = currentOrders.map(o => o.id);
        __lastReadySnapshot = currentOrders.filter(o => o.ready).map(o => o.id);
    }
}

// هر ۵ ثانیه وضعیت سفارشات را چک کن (در پس‌زمینه)
setInterval(() => {
    // اگر تابع رندر اصلی در حال اجراست یا دیتا لود نشده، کاری نکن
    if (typeof Store !== 'undefined' && Store.db && Store.db.orders) {
        checkStaffAlerts();
    }
}, 5000);

// از داخل فایل app.js بعد از هر رندر، این تابع صدا زده می‌شود تا اسنپ‌شات اولیه گرفته شود