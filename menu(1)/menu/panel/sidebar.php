<?php
// فایل منوی کناری مشترک برای تمام صفحات پنل ادمین
// رنگ‌بندی بر اساس پالت اختصاصی پروژه (کرم/قهوه‌ای/نارنجی)
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
?>
<aside class="sidebar">
  <div class="brand">🌱 GitiArts</div>
  <ul class="nav-menu">
    <li class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'panel.php' ? 'active' : '' ?>" onclick="window.location.href='panel.php'">
      <span class="icon">📊</span> داشبورد
    </li>
    <li class="nav-item" onclick="window.location.href='panel.php?view=cafes'">
      <span class="icon">🏪</span> کافه‌ها
    </li>
    <li class="nav-item" onclick="window.location.href='panel.php?view=system'">
      <span class="icon">⚙️</span> سیستم و بکاپ
    </li>
    <li class="nav-item" onclick="window.location.href='panel.php?view=security'">
      <span class="icon">🔒</span> امنیت و لاگ‌ها
    </li>
    
    <li class="nav-item <?= ($_GET['view'] ?? '') === 'plans' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=plans'">
      <span class="icon">💰</span> پلن‌ها و قیمت‌ها
    </li>

    <li class="nav-item <?= ($_GET['view'] ?? '') === 'sms' ? 'active' : '' ?>" onclick="window.location.href='panel.php?view=sms'">
      <span class="icon">📱</span> تنظیمات پیامک
    </li>

    <div class="nav-divider"></div>
    
    <li class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : '' ?>" onclick="window.location.href='tickets.php'">
      <span class="icon">🎫</span> تیکت‌های پشتیبانی
      <?php if ($__tkOpen > 0): ?><span class="badge"><?= $__tkOpen ?></span><?php endif; ?>
    </li>
    <li class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : '' ?>" onclick="window.location.href='announcements.php'">
      <span class="icon">📢</span> اعلانات
    </li>
  </ul>
  <div class="sidebar-footer">
    <form method="post" action="panel.php">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="act" value="logout">
      <button class="btn btn-outline" style="width:100%; color:white; border-color:rgba(255,255,255,0.2)">🚪 خروج از پنل</button>
    </form>
  </div>
</aside>