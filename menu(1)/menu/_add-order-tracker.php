<?php
declare(strict_types=1);
/* _add-order-tracker.php — Adds floating order tracker and sound notification */

$dirs = ['_template', 'gitikhale'];

$trackerJs = <<<'JS'

/* ════════════════════════════════════════════════════════════
   ORDER TRACKER (Floating Widget + Sound + Notification)
   ════════════════════════════════════════════════════════════ */
let __lastOrderStatus = null;
let __trackerInterval = null;

function initOrderTracker() {
    if (__trackerInterval) clearInterval(__trackerInterval);
    
    __trackerInterval = setInterval(async () => {
        // اگر مشتری نیستیم یا سفارش فعالی نداریم، کاری نکن
        if (App.role !== 'customer' || !Store.db) return;
        
        const o = activeOrder();
        if (!o) {
            __lastOrderStatus = null;
            const el = document.getElementById('floating-tracker');
            if (el) el.remove();
            return;
        }

        // ساخت یا آپدیت ویجت
        renderTrackerUI(o);

        // چک کردن تغییر وضعیت
        if (__lastOrderStatus && __lastOrderStatus !== o.status) {
            if (o.status === 'ready') {
                playReadySound();
                ui.toast('🍳 غذای شما آماده است! لطفاً به صندوق مراجعه کنید.', { ttl: 8000 });
            } else if (o.status === 'done') {
                ui.toast('✅ سفارش شما تحویل داده شد. نوش جان!', { ttl: 5000 });
                const el = document.getElementById('floating-tracker');
                if (el) el.remove();
            }
        }
        __lastOrderStatus = o.status;
        
        // آپدیت تایمر هر ثانیه (بدون نیاز به رفرش کل ویجت)
        const timeEl = document.getElementById('tracker-timer');
        if (timeEl && o.ts) {
            timeEl.textContent = fmtDur(Date.now() - o.ts);
        }

    }, 5000); // Polling هر 5 ثانیه (می‌توانی به 10 تغییر دهی)
}

function renderTrackerUI(o) {
    let el = document.getElementById('floating-tracker');
    if (!el) {
        el = document.createElement('div');
        el.id = 'floating-tracker';
        el.className = 'floating-tracker';
        document.body.appendChild(el);
    }
    
    const st = ST[o.status] || ST.pending;
    let statusText = st.t;
    let icon = '⏳';
    let extraClass = '';
    
    if (o.status === 'preparing') {
        statusText = o.ready ? 'آماده تحویل' : 'در حال آماده‌سازی';
        icon = o.ready ? '🔔' : '👨‍🍳';
        if (o.ready) extraClass = 'ready';
    } else if (o.status === 'done') {
        statusText = 'تحویل شد';
        icon = '✅';
        extraClass = 'done';
    } else if (o.status === 'pending') {
        statusText = 'در انتظار تایید';
        icon = '⏳';
    }

    // اگر ویجت از قبل هست، فقط کلاس و متن‌ها رو عوض کن تا تایمر نپره
    if (el.innerHTML.trim() !== '') {
        el.className = 'floating-tracker ' + extraClass;
        el.querySelector('.ft-icon').textContent = icon;
        el.querySelector('.ft-status').textContent = statusText;
        return;
    }

    el.className = 'floating-tracker ' + extraClass;
    el.innerHTML = `
        <div class="ft-icon">${icon}</div>
        <div class="ft-info">
            <div class="ft-title">سفارش #${fa(o.num)}</div>
            <div class="ft-status">${statusText}</div>
        </div>
        <div class="ft-timer" id="tracker-timer">${fmtDur(Date.now() - o.ts)}</div>
    `;
}

function playReadySound() {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        
        // پخش یک صدای زنگ دوتایی (Ding-Dong)
        [0, 0.3].forEach(delay => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(delay === 0 ? 880 : 1046.50, ctx.currentTime + delay); // A5 و C6
            gain.gain.setValueAtTime(0, ctx.currentTime + delay);
            gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + delay + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.4);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime + delay);
            osc.stop(ctx.currentTime + delay + 0.5);
        });
    } catch (e) {
        console.log('Audio blocked by browser', e);
    }
}

// Hook into customer render to start tracking
const __originalCustomerHTML = customerHTML;
customerHTML = function() {
    setTimeout(initOrderTracker, 100);
    return __originalCustomerHTML.apply(this, arguments);
};
JS;

$trackerCss = <<<'CSS'

/* ════════════════════════════════════════════════════════════
   Floating Order Tracker
   ════════════════════════════════════════════════════════════ */
.floating-tracker {
    position: fixed;
    bottom: 85px; /* بالای نوار تب‌بار */
    left: 50%;
    transform: translateX(-50%);
    background: #fff;
    border: 2px solid #EFE7D6;
    border-radius: 50px;
    padding: 8px 16px 8px 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 24px rgba(39,29,18,0.15);
    z-index: 9000;
    direction: rtl;
    font-family: Vazirmatn, sans-serif;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    width: max-content;
    max-width: 90vw;
}
.floating-tracker.ready {
    border-color: #4CAF50;
    background: #F8FDF8;
    box-shadow: 0 8px 24px rgba(76,175,80,0.25);
    animation: pulse-tracker 2s infinite;
}
.floating-tracker.done {
    border-color: #B4531F;
    background: #FBF6EB;
}
.ft-icon {
    font-size: 24px;
    background: #F4F1EA;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    flex-shrink: 0;
}
.floating-tracker.ready .ft-icon {
    background: #E8F5E9;
}
.ft-info {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}
.ft-title {
    font-size: 13px;
    font-weight: 900;
    color: #271D12;
}
.ft-status {
    font-size: 11px;
    color: #7D6C54;
    font-weight: 700;
}
.ft-timer {
    font-family: monospace;
    font-size: 15px;
    font-weight: 900;
    color: #B4531F;
    background: #FFF;
    padding: 4px 8px;
    border-radius: 8px;
    border: 1px solid #EFE7D6;
    min-width: 50px;
    text-align: center;
}
@keyframes pulse-tracker {
    0% { transform: translateX(-50%) scale(1); }
    50% { transform: translateX(-50%) scale(1.02); }
    100% { transform: translateX(-50%) scale(1); }
}
CSS;

foreach ($dirs as $dir) {
    if (!is_dir("tenants/$dir")) continue;
    
    // 1. Append to customer.js
    $jsFile = "tenants/$dir/js/customer.js";
    if (file_exists($jsFile)) {
        $c = file_get_contents($jsFile);
        if (strpos($c, 'ORDER TRACKER (Floating Widget') === false) {
            file_put_contents($jsFile, $c . "\n" . $trackerJs);
            echo "✅ Added Tracker JS to: $jsFile\n";
        }
    }
    
    // 2. Append to style.css
    $cssFile = "tenants/$dir/css/style.css";
    if (file_exists($cssFile)) {
        $c = file_get_contents($cssFile);
        if (strpos($c, 'Floating Order Tracker') === false) {
            file_put_contents($cssFile, $c . "\n" . $trackerCss);
            echo "✅ Added Tracker CSS to: $cssFile\n";
        }
    }
}
echo "\n🎉 Done! Please Ctrl+F5 in browser.\n";