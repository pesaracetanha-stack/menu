<?php
/* ═══════════════════════════════════════════════════════════════
   GitiStore — فروشگاه تراکنشی دودرایور کافه (نسخهٔ ۵)
   تغییر v5: دیتای هر کافه از فایل db.json به بانک اطلاعاتی واقعی رفت.
   · SQLite  → پیش‌فرض؛ خودکار ساخته می‌شود؛ صفرِ تنظیمات (WAL)
   · MySQL   → اگر data/db-config.json تنظیم شده باشد
   · امضای توابع قدیمی (t_db_get / t_db_txn) حفظ شده است.
   · سند کامل کافه در جدول doc(scope,k,v) — نوشتن اتمیک با تراکنش؛
     دیگر هیچ‌وقت فایل نصفه/خراب نمی‌شود.
   این فایل در دو نسخهٔ یکسان نگهداری می‌شود:
     api/lib/class-gstore.php            (پلتفرم)
     tenants/_template/api/lib/…         (کافه — قابل‌حمل)
   ═══════════════════════════════════════════════════════════════ */
declare(strict_types=1);

final class GStore
{
  /** @var array<string, GStore> */
  private static array $ins = [];
  private PDO $pdo;
  private string $driver;      // sqlite | mysql
  private string $root;
  private string $scope;
  private int $depth = 0;
  private bool $inTx = false;   /* v5.0.1: ردیابی دستی تراکنش SQLite */
  /** @var callable|null */
  private $norm;

  private const JF = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

  /* ── دسترسی singleton per root ─────────────────────────────── */
  public static function for(string $root, ?callable $norm = null): self {
    $root = rtrim($root, '/');
    $key  = $root . '|' . (is_string($norm) ? $norm : (is_object($norm) ? 'closure' : ''));
    if (!isset(self::$ins[$key])) self::$ins[$key] = new self($root, $norm);
    return self::$ins[$key];
  }

  private function __construct(string $root, ?callable $norm) {
    $this->root  = $root;
    $this->scope = basename($root);
    $this->norm  = $norm;
    $dir = $root . '/data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!is_dir($dir)) throw new RuntimeException('پوشهٔ data ساخته نشد — دسترسی نوشتن را بررسی کنید');
    $cfg = $this->dbConfig();
    if ($cfg['driver'] === 'mysql') $this->connectMySQL($cfg);
    else                            $this->connectSQLite();
    $this->ensureSchema();
  }

  /* ── تشخیص درایور ──────────────────────────────────────────── */
  private function dbConfig(): array {
    $f = $this->root . '/data/db-config.json';
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (is_array($j) && ($j['driver'] ?? '') === 'mysql'
        && !empty($j['host']) && !empty($j['name'])
        && extension_loaded('pdo_mysql')) {
      return ['driver'=>'mysql','host'=>(string)$j['host'],'port'=>(int)($j['port'] ?? 3306),
              'name'=>(string)$j['name'],'user'=>(string)$j['user'],'pass'=>(string)($j['pass'] ?? '')];
    }
    if (!extension_loaded('pdo_sqlite') && !extension_loaded('sqlite3'))
      throw new RuntimeException('هیچ درایور بانک اطلاعاتی در دسترس نیست (pdo_sqlite/pdo_mysql)');
    return ['driver'=>'sqlite'];
  }

  private function connectSQLite(): void {
    $path = $this->root . '/data/db.sqlite';
    try {
      $this->pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]);
    } catch (Throwable $e) {
      throw new RuntimeException('بانک SQLite باز نشد: ' . $e->getMessage());
    }
    $this->pdo->exec('PRAGMA journal_mode=WAL');
    $this->pdo->exec('PRAGMA synchronous=NORMAL');
    $this->pdo->exec('PRAGMA busy_timeout=6000');
    $this->pdo->exec('PRAGMA foreign_keys=ON');
    $this->driver = 'sqlite';
  }

  private function connectMySQL(array $c): void {
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    try {
      $this->pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 6,
      ]);
    } catch (Throwable $e) {
      throw new RuntimeException('اتصال MySQL برقرار نشد — اطلاعات data/db-config.json را بررسی کنید');
    }
    $this->driver = 'mysql';
  }

  /* ── ساخت جدول + مهاجرت خودکار از db.json ──────────────────── */
  public function ensureSchema(): void {
    if ($this->driver === 'mysql') {
      $this->pdo->exec("CREATE TABLE IF NOT EXISTS doc (
        scope VARCHAR(64) NOT NULL,
        k VARCHAR(32) NOT NULL,
        v LONGTEXT NOT NULL,
        updated_at BIGINT NULL,
        PRIMARY KEY (scope,k)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    } else {
      $this->pdo->exec("CREATE TABLE IF NOT EXISTS doc (
        scope TEXT NOT NULL,
        k TEXT NOT NULL,
        v TEXT NOT NULL,
        updated_at INTEGER NULL,
        PRIMARY KEY (scope,k)
      )");
    }
    // اگر جدول خالی است و db.json قدیمی هست → مهاجرت خودکار
    $st = $this->pdo->prepare("SELECT COUNT(*) c FROM doc WHERE scope = ?");
    $st->execute([$this->scope]);
    if ((int)($st->fetch()['c'] ?? 0) > 0) return;

    $legacy = $this->root . '/data/db.json';
    if (is_file($legacy)) {
      $this->importLegacy($legacy);
    } else {
      // سند خالیِ معتبر بساز تا get() هرگز بدون ردیف نماند
      $this->txBegin();
      try {
        $q = $this->pdo->prepare("INSERT OR IGNORE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
        if ($this->driver === 'mysql') $q = $this->pdo->prepare("INSERT IGNORE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
        $q->execute([$this->scope, 'db', '{}', time()]);
        $this->txCommit();
      } catch (Throwable $e) { $this->txRollback(); throw $e; }
    }
  }

  private function importLegacy(string $legacy): void {
    $raw = (string)file_get_contents($legacy);
    try {
      $doc = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
      throw new RuntimeException('db.json قدیمی خراب است — مهاجرت متوقف شد (فایل دست‌نخورده ماند)');
    }
    if (!is_array($doc)) throw new RuntimeException('db.json قدیمی معتبر نیست');
    $this->txBegin();
    try {
      $q = $this->pdo->prepare("INSERT OR REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      if ($this->driver === 'mysql') $q = $this->pdo->prepare("REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      $q->execute([$this->scope, 'db', json_encode($doc, self::JF), time()]);
      $m = $this->pdo->prepare("INSERT OR REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      if ($this->driver === 'mysql') $m = $this->pdo->prepare("REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      $m->execute([$this->scope, 'meta', json_encode(['imported_at'=>time(),'from'=>'db.json'], self::JF), time()]);
      $this->txCommit();
    } catch (Throwable $e) { $this->txRollback(); throw $e; }
    @rename($legacy, $legacy . '.imported-' . date('ymd_His'));   // بکاپ فایل قدیمی
  }

  /* ── تراکنش پایه ───────────────────────────────────────────── */
  private function txBegin(): void {
    /* v5.0.1: SQLite با exec('BEGIN') پرچم inTransaction() را روشن نمی‌کند —
       وضعیت تراکنش را خودمان نگه می‌داریم تا ROLLBACK در خطا واقعاً اجرا شود
       (قبلاً اولین exception داخل تراکنش، اتصال را برای تراکنش‌های بعدی می‌شکست). */
    if ($this->driver === 'sqlite') { $this->pdo->exec('BEGIN IMMEDIATE'); $this->inTx = true; }
    else $this->pdo->beginTransaction();
  }
  private function txCommit(): void {
    if ($this->driver === 'sqlite') { $this->pdo->exec('COMMIT'); $this->inTx = false; }
    else $this->pdo->commit();
  }
  private function txRollback(): void {
    try {
      if ($this->driver === 'sqlite') {
        if ($this->inTx) { $this->pdo->exec('ROLLBACK'); $this->inTx = false; }
      }
      else { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }
    } catch (Throwable $e) { $this->inTx = false; /* بی‌صدا */ }
  }

  /* ── API عمومی ─────────────────────────────────────────────── */

  /** سند کامل کافه (نرمال‌شده) */
  public function get(): array {
    $doc = $this->fetchDoc();
    $fn  = $this->norm;
    return $fn ? $fn($doc) : $doc;
  }

  /** JSON خام سند (برای اسکن/خروجی) */
  public function rawJson(): string {
    return json_encode($this->fetchDoc(), self::JF);
  }

  /** خواندن + تغییر + نوشتن اتمیک — هیچ فراخوانی تودرتو ممنوع */
  public function txn(callable $fn): array {
    if ($this->depth > 0) throw new RuntimeException('تراکنش تودرتو مجاز نیست');
    $this->depth++;
    $tries = 0;
    while (true) {
      try {
        $this->txBegin();
        $doc = $this->fetchDoc(true);
        $fn2 = $this->norm;
        $db  = $fn2 ? $fn2($doc) : $doc;
        /* v5.0.1 — تفکیک «سند ماندگار» از «پاسخ کلاینت»:
           کلوژرهای استاندارد (&$db) تغییرات را از طریق ارجاع داخل سند می‌گذارند؛
           هرچه برگردانند فقط «پاسخ کلاینت» است (مثلاً t_state_admin بدون هش رمز).
           قبلاً مقدار برگشتی به‌جای سند ذخیره می‌شد — یعنی هر save_* هش رمز را
           از بانک پاک می‌کرد و order_create کل سند را با ۴ فیلد جایگزین می‌کرد!
           سبک تابعی (بدون ارجاع) مثل قبل: خروجی = سند ماندگار (سازگاری عقب). */
        $byRef = false;
        if ($fn instanceof Closure) {
          try {
            $ps = (new ReflectionFunction($fn))->getParameters();
            $byRef = (bool)(!empty($ps) && $ps[0]->isPassedByReference());
          } catch (Throwable $e) { $byRef = false; }
        }
        if ($byRef) {
          $ret = $fn($db);                 /* تغییرات از طریق ارجاع داخل $db */
        } else {
          $ret = $db = $fn($db);           /* رفتار قدیمی */
        }
        if (!is_array($db)) throw new RuntimeException('سند دادهٔ کافه معتبر نیست');
        $q = $this->pdo->prepare("UPDATE doc SET v = ?, updated_at = ? WHERE scope = ? AND k = 'db'");
        $q->execute([json_encode($db, self::JF), time(), $this->scope]);
        $this->txCommit();
        $this->depth--;
        return is_array($ret) ? $ret : $db;
      } catch (Throwable $e) {
        $this->txRollback();
        $busy = ($this->driver === 'sqlite' && stripos($e->getMessage(), 'locked') !== false)
             || ($this->driver === 'mysql' && stripos($e->getMessage(), 'Lock wait timeout') !== false);
        if ($busy && $tries < 3) { $tries++; usleep(250000); continue; }
        $this->depth--;
        throw $e;
      }
    }
  }

  /** جایگزینی کامل سند (برای نصب/مهاجرت دستی) */
  public function importJson(string $json): void {
    try { $doc = json_decode($json, true, 512, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException('JSON ورودی معتبر نیست'); }
    if (!is_array($doc)) throw new RuntimeException('JSON ورودی باید یک شیء باشد');
    $this->depth++;
    try {
      $this->txBegin();
      $q = $this->pdo->prepare("INSERT OR REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      if ($this->driver === 'mysql') $q = $this->pdo->prepare("REPLACE INTO doc(scope,k,v,updated_at) VALUES (?,?,?,?)");
      $q->execute([$this->scope, 'db', json_encode($doc, self::JF), time()]);
      $this->txCommit();
      $this->depth--;
    } catch (Throwable $e) { $this->txRollback(); $this->depth--; throw $e; }
  }

  /** اطلاعات درایور فعلی (برای ویزارد/پنل) */
  public function info(): array {
    return ['driver'=>$this->driver, 'scope'=>$this->scope,
            'sqlite_path'=>$this->root.'/data/db.sqlite'];
  }

  /* ── داخلی ─────────────────────────────────────────────────── */
  private function fetchDoc(bool $forUpdate = false): array {
    $sql = "SELECT v FROM doc WHERE scope = ? AND k = 'db'";
    if ($forUpdate && $this->driver === 'mysql') $sql .= ' FOR UPDATE';
    $st = $this->pdo->prepare($sql);
    $st->execute([$this->scope]);
    $row = $st->fetch();
    if (!$row || !isset($row['v'])) {
      // خودشفایی: اگر db.json تازه گذاشته شده، وارد کن
      if (is_file($this->root . '/data/db.json')) {
        $this->importLegacy($this->root . '/data/db.json');
        $st->execute([$this->scope]);
        $row = $st->fetch();
      }
      if (!$row || !isset($row['v'])) throw new RuntimeException('بانک دادهٔ کافه خالی است — نصب ناتمام است');
    }
    $doc = json_decode((string)$row['v'], true);
    if (!is_array($doc)) throw new RuntimeException('سند دادهٔ کافه معتبر نیست');
    return $doc;
  }
}
