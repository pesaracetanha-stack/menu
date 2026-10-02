<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════════════════
   آپلود رسانه — فقط مدیر کافه (v5.0.3-test.3)
   · تصویر → WebP/PNG با حفظ شفافیت؛ ویدیو → فایل خام
   · SVG ضدعفونی می‌شود (حذف اسکریپت/رویداد/ارجاع خارجی/DOCTYPE)
   · سهمیهٔ حجم (limits.upload_mb از config پلتفرم) + سقف تعداد فایل
   · خطاهای PHP آپلود (upload_max_filesize و...) با پیام فارسی روشن
   ═══════════════════════════════════════════════════════════════ */

const UP_MAX_FILES = 1000;   // حداکثر تعداد فایل در پوشهٔ آپلود هر کافه

/* ── سهمیهٔ حجم از config پلتفرم (روی نصب مستقل، config ریشه) ── */
function up_quota_mb(): int {
  $cands = [T_ROOT . '/config.php', dirname(T_ROOT, 2) . '/config.php'];
  foreach ($cands as $cf) {
    if (!is_file($cf)) continue;
    $cfg = require $cf;
    $mb  = (int)(is_array($cfg) ? ($cfg['limits']['upload_mb'] ?? 0) : 0);
    if ($mb > 0) return $mb;
  }
  return 200;   /* فالبک عاقلانه اگر config پیدا نشد یا مقدار نداشت */
}

/* ── وضعیت فعلی پوشهٔ آپلود ── */
function up_usage(string $dir): array {
  $bytes = 0; $count = 0;
  if (is_dir($dir)) {
    foreach (scandir($dir) ?: [] as $fn) {
      if ($fn === '.' || $fn === '..') continue;
      $p = "$dir/$fn";
      if (!is_file($p)) continue;
      $bytes += (int)@filesize($p);
      $count++;
    }
  }
  return ['bytes' => $bytes, 'count' => $count];
}

/* ── گیت سهمیه: قبل از ذخیرهٔ هر فایل — مجموع موجود + فایل جدید ──
   نکته: دو آپلود هم‌زمان می‌توانند چند کیلوبایت از سقف رد شوند؛
   در مقیاس یک کافه قابل‌چشم‌پوشی است و پیچیدگی قفل نمی‌ارزد. */
function up_check_quota(string $dir, int $incoming): void {
  $u = up_usage($dir);
  if ($u['count'] + 1 > UP_MAX_FILES)
    throw new Exception('تعداد فایل‌های آپلودشدهٔ کافه به سقف (' . UP_MAX_FILES . ' فایل) رسیده است — فایل‌های استفاده‌نشده را حذف کنید یا با پشتیبانی تماس بگیرید');
  $mb  = up_quota_mb();
  $quota = $mb * 1048576;
  if ($quota > 0 && $u['bytes'] + $incoming > $quota) {
    $free = max(0, (int)(($quota - $u['bytes']) / 1048576));
    throw new Exception('سقف فضای آپلود کافه (' . $mb . 'MB) پر شده است — فضای آزاد فعلی: ' . $free . 'MB. رسانه‌های استفاده‌نشده را حذف کنید یا با پشتیبانی هماهنگ کنید');
  }
}

/* ── پیام فارسی برای کدهای خطای PHP ── */
function up_err_msg(int $code): string {
  switch ($code) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
      $lim = (string)(ini_get('upload_max_filesize') ?: '?');
      return 'حجم فایل از حد مجاز سرور بزرگ‌تر است (حد فعلی سرور: ' . $lim . ') — فایل کوچک‌تر انتخاب کنید یا از هاست بخواهید «upload_max_filesize» را به 64M برساند';
    case UPLOAD_ERR_PARTIAL:    return 'آپلود ناقص ماند — لطفاً دوباره تلاش کنید';
    case UPLOAD_ERR_NO_FILE:    return 'هیچ فایلی انتخاب نشده است';
    case UPLOAD_ERR_NO_TMP_DIR: return 'پوشهٔ موقت سرور در دسترس نیست — با پشتیبانی تماس بگیرید';
    case UPLOAD_ERR_CANT_WRITE: return 'ذخیرهٔ موقت فایل روی سرور ممکن نشد';
    default:                    return 'خطای سرور در دریافت فایل (کد ' . $code . ')';
  }
}

/* ═══════════════ ضدعفونی SVG ═══════════════
   SVG وقتی مستقیم در مرورگر باز شود سند فعال است — اسکریپت و رویداد
   اجرا می‌شوند. این تابع فقط شکل/متن/رنگ نگه می‌دارد و بقیه را می‌برد:
   · <script>، <foreignObject>، <iframe>/<object>/<embed> و مشابه‌ها
   · هر صفت on* (onload، onclick، ...)
   · هر مقدار javascript:/vbscript: یا data: غیر از تصویر رستری
   · ارجاع خارجی در use/image/feImage (فقط #داخلی یا data:image)
   · @import و url(خارجی) و expression( و -moz-binding در <style>
   · DOCTYPE و xml-stylesheet (منبع entity خارجی) → کل فایل رد می‌شود
   خروجی: XML خوش‌فرم؛ اگر پارس نشود استثنا می‌دهد (آپلود رد می‌شود). */
function up_sanitize_svg(string $raw): string {
  if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
  if (stripos($raw, '<!DOCTYPE') !== false)
    throw new Exception('SVG دارای DOCTYPE پذیرفته نمی‌شود — فایل را بدون DOCTYPE ذخیره کنید');
  if (stripos($raw, '<?xml-stylesheet') !== false)
    throw new Exception('SVG دارای استایل خارجی (xml-stylesheet) پذیرفته نمی‌شود');

  $prev = libxml_use_internal_errors(true);
  $doc  = new DOMDocument();
  $ok   = $doc->loadXML($raw, LIBXML_NONET | LIBXML_COMPACT);
  libxml_clear_errors();
  libxml_use_internal_errors($prev);
  if (!$ok || !$doc->documentElement) throw new Exception('فایل SVG معتبر نیست');

  /* اگر فایل انکودینگ ننوشته بود → UTF-8 تا متن فارسی عیناً ذخیره شود (نه رشتهٔ عددی) */
  if (!$doc->encoding) $doc->encoding = 'UTF-8';

  $root = $doc->documentElement;
  if (strtolower((string)($root->localName ?? '')) !== 'svg'
      || stripos((string)$root->namespaceURI, 'http://www.w3.org/2000/svg') !== 0)
    throw new Exception('فایل SVG معتبر نیست (ریشهٔ svg با فضای‌نام استاندارد پیدا نشد)');

  /* پردازش‌گردها (به‌جز اعلان XML که در DOM نیست) حذف می‌شوند */
  foreach (iterator_to_array($doc->childNodes) as $cn)
    if ($cn instanceof DOMProcessingInstruction) $doc->removeChild($cn);

  $kill    = ['script', 'foreignobject', 'iframe', 'object', 'embed',
              'handler', 'annotation-xml', 'audio', 'video'];
  $refTags = ['use', 'image', 'feimage'];

  $list = $doc->getElementsByTagNameNS('*', '*');   /* لیست زندهٔ DOM — یک‌بار گرفته می‌شود */
  $all  = [];
  for ($i = 0; $i < $list->length; $i++) $all[] = $list->item($i);

  foreach ($all as $n) {
    if (!$n instanceof DOMElement || !$n->parentNode) continue;   /* ممکن است زیردرخت قبلاً حذف شده باشد */
    $ln = strtolower((string)$n->localName);

    /* ۱) عناصر خطرناک — با هر حروف‌کشی حذف کامل */
    if (in_array($ln, $kill, true)) {
      $n->parentNode->removeChild($n);
      continue;
    }

    /* ۲) صفات: on* و مقدارهای خطرناک و ارجاع خارجی */
    for ($i = $n->attributes->length - 1; $i >= 0; $i--) {
      $a   = $n->attributes->item($i);
      $aln = strtolower((string)$a->localName);
      $val = (string)$a->nodeValue;

      if (str_starts_with($aln, 'on')) { $n->removeAttributeNode($a); continue; }

      if (preg_match('/(javascript|vbscript|livescript|mocha)\s*:/i', $val)
          || preg_match('#^\s*data:\s*(?!image/(?:png|jpe?g|gif|webp|bmp|x-icon)\s*;)#i', $val)) {
        $n->removeAttributeNode($a);
        continue;
      }

      $isHref = in_array($aln, ['href', 'xlink:href'], true);
      if ($isHref && in_array($ln, $refTags, true)) {
        $t = trim($val);
        if ($t !== '' && $t[0] !== '#' && !preg_match('#^data:image/(?:png|jpe?g|gif|webp);#i', $t))
          $n->removeAttributeNode($a);          /* ارجاع خارجی در use/image */
      } elseif ($isHref && $ln === 'a') {
        $n->removeAttributeNode($a);            /* لینک در لوگو بی‌معنی است */
      }
    }
  }

  /* ۳) <style> — محتوای CSS هم اسکراب می‌شود (کلاس‌ها و رنگ‌ها می‌مانند) */
  $styles = $doc->getElementsByTagNameNS('*', 'style');
  $stylesAll = [];
  for ($i = 0; $i < $styles->length; $i++) $stylesAll[] = $styles->item($i);
  foreach ($stylesAll as $st) {
    if (!$st) continue;
    $css = (string)$st->textContent;
    $css = preg_replace('/@import[^;}]*(;|})?/i', '', $css) ?? '';
    $css = preg_replace('/(javascript|vbscript|livescript|mocha)\s*:[^;}"\']*/i', '', $css) ?? '';
    $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css) ?? '';
    $css = preg_replace('/-moz-binding[^;}]*(;|})?/i', '', $css) ?? '';
    $css = preg_replace('/url\(\s*([\'"]?)\s*(?!#|data:image\/(?:png|jpe?g|gif|webp)\s*;)[^)]*\)/i', 'none', $css) ?? '';
    while ($st->firstChild) $st->removeChild($st->firstChild);
    $st->appendChild($doc->createTextNode($css));
  }

  return (string)$doc->saveXML();
}

/* ═══════════════ خودِ آپلود ═══════════════ */
const UP_IMG_KB = 200;   /* v5.0.5-test.3: سقف حجم هر تصویر پس از پردازش */

function up_upload(): array {
  t_require_admin();

  $f = $_FILES['file'] ?? null;
  if (!is_array($f)) {
    /* $_FILES خالی و POST حجیم = گذشتن از post_max_size سرور */
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES))
      throw new Exception('حجم ارسال از «post_max_size» سرور گذشت — فایل کوچک‌تر انتخاب کنید یا از هاست بخواهید این حد را بالا ببرد');
    throw new Exception('فایلی دریافت نشد');
  }
  if ((int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
    throw new Exception(up_err_msg((int)($f['error'] ?? UPLOAD_ERR_NO_FILE)));

  $dir = T_ROOT . '/uploads';
  if (!is_dir($dir)) mkdir($dir, 0755, true);

  up_check_quota($dir, (int)($f['size'] ?? 0));

  $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);

  /* ویدیو — فرمت خام با سقف ۶۰MB (سهمیهٔ کل هم اعمال شده) */
  if (in_array($mime, ['video/mp4', 'video/webm'], true)) {
    if ((int)$f['size'] > 60 * 1024 * 1024) throw new Exception('حجم ویدیو حداکثر ۶۰MB');
    $ext  = $mime === 'video/mp4' ? 'mp4' : 'webm';
    $name = 'v_' . date('ymd') . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new Exception('ذخیرهٔ ویدیو ناموفق');
    @chmod("$dir/$name", 0644);
    return ['url' => "uploads/$name"];
  }

  /* SVG — ضدعفونی‌شده ذخیره می‌شود (شفافیت وکتور حفظ می‌شود) */
  if (in_array($mime, ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml'], true)) {
    if ((int)$f['size'] > 2 * 1024 * 1024) throw new Exception('حجم SVG حداکثر ۲MB');
    $clean = up_sanitize_svg((string)file_get_contents($f['tmp_name']));
    $name  = 'l_' . date('ymd') . bin2hex(random_bytes(3)) . '.svg';
    if (!@file_put_contents("$dir/$name", $clean)) throw new Exception('ذخیره ناموفق');
    @chmod("$dir/$name", 0644);
    return ['url' => "uploads/$name"];
  }

  /* تصاویر معمولی و لوگو */
  if (!str_starts_with((string)$mime, 'image/')) throw new Exception('فرمت مجاز نیست');
  if ((int)$f['size'] > 8 * 1024 * 1024) throw new Exception('حجم تصویر حداکثر ۸MB');

  /* PNG کوچک (≤۲۰۰KB) دست‌نخورده ذخیره می‌شود — شفافیت بیت‌به‌بیت می‌ماند */
  if ($mime === 'image/png' && (int)$f['size'] <= UP_IMG_KB * 1024) {
    $name = 'u_' . date('ymd') . bin2hex(random_bytes(3)) . '.png';
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new Exception('ذخیره ناموفق');
    @chmod("$dir/$name", 0644);
    return ['url' => "uploads/$name"];
  }

  $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
  if (!$src) throw new Exception('تصویر قابل پردازش نیست');

  imagealphablending($src, false);
  imagesavealpha($src, true);

  $id  = 'u_' . date('ymd') . bin2hex(random_bytes(3));
  $out = up_resize($src, 800);
  /* v5.0.5-test.3: خروجی همیشه ≤۲۰۰KB تضمین می‌شود (نردبان کیفیت/ابعاد) */
  $p   = up_encode_capped($dir, $id, $out);

  imagedestroy($out);
  imagedestroy($src);
  @chmod($p, 0644);

  return ['url' => 'uploads/' . basename($p)];
}

function up_resize(GdImage $src, int $maxW): GdImage {
  $w = imagesx($src);
  $h = imagesy($src);
  if ($w <= $maxW) {
    $dst = imagecreatetruecolor($w, $h);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    return $dst;
  }

  $nh = max(1, (int)round($h * $maxW / $w));
  $dst = imagecreatetruecolor($maxW, $nh);
  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
  imagefilledrectangle($dst, 0, 0, $maxW, $nh, $transparent);
  imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $nh, $w, $h);
  return $dst;
}

/* ═══ v5.0.5-test.3: کدگذاری تصویر با تضمین سقف ۲۰۰ کیلوبایت ═══
   نردبان: اول ابعاد فعلی با کیفیت‌های کاهشی، بعد ابعاد کوچک‌تر (۶۴۰→۲۸۰).
   اولین خروجی زیر سقف ذخیره می‌شود؛ شفافیت با WebP حفظ می‌شود.
   اگر WebP در هیچ پله‌ای زیر سقف نرفت → PNG فشرده در ۳۴۰px (فالبک). */
function up_encode_capped(string $dir, string $id, GdImage $img): string {
  $cap  = UP_IMG_KB * 1024;
  $tmp  = "$dir/__cap_" . getmypid() . '.bin';
  $best = null; $bestSz = PHP_INT_MAX;
  $hasWebp  = function_exists('imagewebp');   /* هاست‌های بدون WebP مستقیم به فالبک PNG می‌روند */
  $qualities = [88, 74, 62, 50, 40, 30, 22];
  $widths    = [0, 640, 512, 420, 340, 280];   /* ۰ = ابعاد فعلی */

  if ($hasWebp) {
    foreach ($widths as $w) {
      $own = false;
      $im  = $img;
      if ($w > 0 && imagesx($img) > $w) { $im = up_resize($img, $w); $own = true; }
      foreach ($qualities as $q) {
        if (!@imagewebp($im, $tmp, $q)) continue;
        $sz = (int)@filesize($tmp);
        if ($sz > 0 && $sz < $bestSz) {
          if ($best && is_file($best)) @unlink($best);
          $best = "$dir/{$id}.webp"; $bestSz = $sz;
          @rename($tmp, $best);
        }
        if ($bestSz <= $cap) break;
      }
      if ($own) imagedestroy($im);
      if ($bestSz <= $cap) break;
    }
  }

  /* فالبک نادر: WebP در دسترس نبود یا زیر سقف نرفت → PNG فشرده با نردبان ابعاد */
  if (!$best || $bestSz > $cap) {
    if ($best && is_file($best)) { @unlink($best); $best = null; $bestSz = PHP_INT_MAX; }
    foreach ([340, 280, 220, 170, 120] as $w) {
      $im = up_resize($img, $w);
      $ok = @imagepng($im, $tmp, 9) && (int)@filesize($tmp) > 0;
      imagedestroy($im);
      if (!$ok) continue;
      if ($best && is_file($best)) @unlink($best);
      $best = "$dir/{$id}.png"; $bestSz = (int)@filesize($tmp);
      @rename($tmp, $best);
      if ($bestSz <= $cap) break;
    }
  }

  if (is_file($tmp)) @unlink($tmp);
  if (!$best) throw new Exception('پردازش تصویر ناموفق بود');
  return $best;
}
