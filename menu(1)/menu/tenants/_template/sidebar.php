<?php
// منوی کناری مشترک برای پنل کافه‌دار
// این فایل در تمام صفحات مدیریتی کافه فراخوانی می‌شود
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('TEN_SESS');
    session_start();
}
$__slug = basename(__DIR__);
$__currentPage = basename($_SERVER['PHP_SELF']);
$__role = $_SESSION['tenant_role'] ?? 'admin';
$__user = $_SESSION['tenant_user'] ?? 'مدیر';

// دریافت نام برند از دیتابیس (اگر وجود داشته باشد)
$__brandName = 'کافه من';
try {
    $__dbf = __DIR__ . '/data/db.sqlite';
    if (is_file($__dbf)) {
        $__pdo = new PDO('sqlite:' . $__dbf);
        $__stmt = $__pdo->prepare("SELECT v FROM doc WHERE scope=? AND k='db' LIMIT 1");
        $__stmt->execute([$__slug]);
        $__raw = $__stmt->fetchColumn();
        if ($__raw) { 
            $__j = json_decode($__raw, true); 
            if (!empty($__j['menu']['brand']['name'])) $__brandName = (string)$__j['menu']['brand']['name']; 
        }
    }
} catch (Throwable $e) {}
?>
<aside class="sidebar">
  <div class="brand">🌱 <?= htmlspecialchars($__brandName) ?></div>
  <ul class="nav-menu">
    <?php if ($__role === 'admin'): ?>
      <li class="nav-item <?= $__currentPage === 'panel-admin.php' ? 'active' : '' ?>" onclick="window.location.href='panel-admin.php'">
        <span class="icon">📊</span> داشبورد
      </li>
      <li class="nav-item" onclick="window.location.href='index.html'">
        <span class="icon">🎨</span> طراحی منو
      </li>
      <li class="nav-item" onclick="window.location.href='cashier.php'">
        <span class="icon">💳</span> صندوق
      </li>
      <li class="nav-item" onclick="window.location.href='kds.php'">
        <span class="icon">🍳</span> آشپزخانه
      </li>
      
      <div class="nav-divider"></div>
      
      <li class="nav-item <?= $__currentPage === 'tickets.php' ? 'active' : '' ?>" onclick="window.location.href='tickets.php'">
        <span class="icon">🎫</span> پشتیبانی
      </li>
      <li class="nav-item <?= $__currentPage === 'announcements.php' ? 'active' : '' ?>" onclick="window.location.href='announcements.php'">
        <span class="icon">📢</span> اعلانات
      </li>
      <li class="nav-item <?= $__currentPage === 'renew.php' ? 'active' : '' ?>" onclick="window.location.href='renew.php'">
        <span class="icon">💳</span> تمدید اشتراک
      </li>
    <?php else: ?>
      <li class="nav-item" onclick="window.location.href='cashier.php'">
        <span class="icon">💳</span> صندوق
      </li>
      <li class="nav-item" onclick="window.location.href='kds.php'">
        <span class="icon">🍳</span> آشپزخانه
      </li>
      <li class="nav-item" onclick="window.location.href='hub.php'">
        <span class="icon">🏠</span> صفحه اصلی (Hub)
      </li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-footer">
    <div style="font-size:12px; color:#CBBB99; margin-bottom:8px; text-align:center">خوش آمدید، <?= htmlspecialchars($__user) ?></div>
    <a href="logout.php" class="btn btn-outline" style="width:100%; color:white; border-color:rgba(255,255,255,0.2)">🚪 خروج از پنل</a>
  </div>
</aside>