<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { session_name('TEN_SESS'); session_start(); }
unset($_SESSION['tenant'], $_SESSION['tenant_user'], $_SESSION['tenant_role']);
header('Location: ../../');   /* قابل‌حمل: روی پلتفرم و در بستهٔ مستقل هر دو درست کار می‌کند */
