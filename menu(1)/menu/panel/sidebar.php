<?php
// فایل منوی کناری مشترک با کنترل دسترسی (RBAC)
$__tkFile = __DIR__ . '/../data/tickets.json';
$__tkOpen = 0;
if (is_file($__tkFile)) {
  $__tkData = json_decode((string)@file_get_contents($__tkFile), true);
  if (is_array($__tkData) && isset($__tkData['tickets'])) {
    foreach ($__tkData['tickets'] as $__t) {
      if (($__t['status'] ?? '') === 'open') $__tkOpen++;
    }
  }
}

$isAdmin = ($_SESSION['plat_role'] ?? 'admin') === 'admin';
$perms = $_SESSION['plat_perms'] ?? [];
$currentView = $_GET['view'] ?? 'dashboard';

// تابع کمکی برای بررسی دسترسی
function has_access($perm, $isAdmin, $perms) {
    return $isAdmin || in_array($perm, $perms);
}
?>
<aside class="sidebar">
  <div class="brand">🌱 GitiArts</div>
  <ul class="nav-menu">
    <!-- داشبورد همیشه برای همه -->
    <li class="nav-item <?= $currentView === 'dashboard' ? 'active' : '' ?>" onclick="window.location.href='panel.php'">
      <span class="icon">📊</span> داشبورد
    </li>
    
    <!-- کافه‌ها -->
    <?php if (has_access('manage_cafes', $isAdmin, $perms)): ?>
    <li class="nav-item <?= $currentView === 'cafes' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=cafes'">
      <span class="icon">🏪</span> کافه‌ها
    </li>
    <?php endif; ?>

    <!-- تحلیل و آمار -->
    <?php if (has_access('view_analytics', $isAdmin, $perms)): ?>
    <li class="nav-item <?= $currentView === 'analytics' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=analytics'">
      <span class="icon">📈</span> تحلیل و آمار
    </li>
    <?php endif; ?>

    <!-- بخش‌های مخصوص ادمین (فقط مدیر کل) -->
    <?php if ($isAdmin): ?>
    <li class="nav-item <?= $currentView === 'system' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=system'">
      <span class="icon">⚙️</span> سیستم و بکاپ
    </li>
    <li class="nav-item <?= $currentView === 'security' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=security'">
      <span class="icon">🔒</span> امنیت و لاگ‌ها
    </li>
    <li class="nav-item <?= $currentView === 'plans' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=plans'">
      <span class="icon">💰</span> پلن‌ها و قیمت‌ها
    </li>
    <li class="nav-item <?= $currentView === 'sms' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=sms'">
      <span class="icon">📱</span> تنظیمات پیامک
    </li>
    <li class="nav-item <?= $currentView === 'support' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=support'">
      <span class="icon">👥</span> مدیریت پشتیبان‌ها
    </li>
    <?php endif; ?>

    <div class="nav-divider"></div>

    <!-- تیکت‌های پشتیبانی -->
    <?php if (has_access('manage_tickets', $isAdmin, $perms)): ?>
    <li class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : '' ?>" onclick="window.location.href='tickets.php'">
      <span class="icon">🎫</span> تیکت‌های پشتیبانی
      <?php if ($__tkOpen > 0): ?><span class="badge"><?= $__tkOpen ?></span><?php endif; ?>
    </li>
    <?php endif; ?>

    <!-- اعلانات -->
    <?php if (has_access('manage_announcements', $isAdmin, $perms)): ?>
    <li class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : '' ?>" onclick="window.location.href='announcements.php'">
      <span class="icon">📢</span> اعلانات
    </li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-footer">
    <form method="post" action="panel.php">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="logout">
      <button class="btn btn-outline" style="width:100%; color:white; border-color:rgba(255,255,255,0.2)">🚪 خروج از پنل</button>
    </form>
  </div>
</aside>