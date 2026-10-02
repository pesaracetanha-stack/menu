<?php
declare(strict_types=1);

/* ═══════════════════════════════════════════════════════════════
   ایمپورت اکسل منو (v5.0.4-test.1) — بدون هیچ کتابخانهٔ بیرونی
   · xlsx = zip استاندارد Office Open XML؛ با ZipArchive + DOM ساخته/خوانده می‌شود
   · ساخت: قالب خالی + منوی نمونهٔ کامل (هر دو با شیت راهنمای فارسی، راست‌به‌چپ)
   · خواندن: شیت‌ها با sharedStrings و inlineStr و سلول عددی — فایل اکسل واقعی
   · ایمپورت: پارس + اعتبارسنجی ردیف‌به‌ردیف با پیام فارسی، سپس پیش‌نمایش؛
     ذخیره فقط با excel_apply (جایگزینی کامل یا افزودن به منوی فعلی)
   · امنیت: فقط مدیر (t_require_admin)، سقف حجم/ردیف، همهٔ مقادیر سفید‌لیستی
     و بازسازی کامل ساختار داده — ورودی کاربر هرگز عیناً در سند ذخیره نمی‌شود
   ═══════════════════════════════════════════════════════════════ */

const XL_MENU_SHEET   = 'منو';
const XL_HELP_SHEET   = 'راهنما';
const XL_MAX_FILE_MB  = 5;
const XL_MAX_ROWS     = 500;
const XL_MAX_CATS     = 40;
const XL_MAX_PER_CAT  = 120;
const XL_MIME         = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

/* ── سرستون‌های شیت منو (ترتیب ثابت — مبنا هنگام خواندن هم همین است) ── */
function xl_headers(): array {
  return ['دسته', 'نام محصول', 'توضیح', 'قیمت (تومان)', 'تخفیف ٪',
          'برچسب‌ها', 'آپشن‌ها', 'موجودی', 'تصویر (نام فایل)'];
}

/* ═══════════════ ابزارهای پایه ═══════════════ */

function xl_esc(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/* ارقام لاتین → فارسی (برای پیام‌ها) */
function xl_fa(int $n): string {
  return strtr((string)$n, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/* A=1 ... برای ساخت مرجع سلول */
function xl_colref(int $col, int $row): string {
  $l = '';
  while ($col > 0) { $m = ($col - 1) % 26; $l = chr(65 + $m) . $l; $col = intdiv($col - 1, 26); }
  return $l . $row;
}

/* ارقام فارسی/عربی → لاتین + حذف جداکننده‌های هزارگان */
function xl_fa2en(string $s): string {
  return strtr($s, [
    '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
    '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
    '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    '٫' => '.', '٬' => '', '،' => ',',
  ]);
}

/* عدد صحیح از سلول (متن یا عدد) — پذیرش: 65000 ، ۶۵٬۰۰۰ ، 65,000 — ناموفق: null */
function xl_num($v): ?int {
  if ($v === null) return null;
  if (is_int($v)) return $v;
  if (is_float($v)) return (int)round($v);
  $s = trim((string)$v);
  if ($s === '') return null;
  $s = preg_replace('/[,\s_ـ]/u', '', xl_fa2en($s)) ?? '';
  /* فرم‌های «تومان»/«هزار تومان» حاشیه‌ای نادیده — فقط عدد خالص */
  if (!preg_match('/^\d{1,12}$/', $s)) return null;
  return (int)$s;
}

/* ═══════════════ نوشتن xlsx ═══════════════
   $sheets = [ ['name'=>.., 'widths'=>[..], 'rows'=>[ [cell,..], .. ]], .. ]
   cell = مقدار اسکالر | ['v'=>.., 's'=>'h'|'b']  (h=سربرگ رنگی، b=بولد) */

function xl_build(string $outPath, array $sheets): void {
  if (!class_exists('ZipArchive')) throw new Exception('افزونهٔ zip روی سرور در دسترس نیست — با پشتیبانی هاست تماس بگیرید');
  if (!$sheets) throw new Exception('شیدی برای ساخت وجود ندارد');

  $CT = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
      . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
      . '<Default Extension="xml" ContentType="application/xml"/>'
      . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
      . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
  foreach ($sheets as $i => $_) {
    $CT .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
  }
  $CT .= '</Types>';

  $RELS = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

  $WB = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
      . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
  $WBREL = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
  foreach ($sheets as $i => $sh) {
    $n     = $i + 1;
    $name  = mb_substr(trim((string)($sh['name'] ?? ('شیت' . $n))), 0, 31);
    $WB    .= '<sheet name="' . xl_esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
    $WBREL .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
  }
  $WB    .= '</sheets></workbook>';
  $WBREL .= '<Relationship Id="rId' . (count($sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

  $STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
      . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
      . '<fonts count="3">'
      . '<font><sz val="11"/><name val="Calibri"/></font>'
      . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
      . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
      . '</fonts>'
      . '<fills count="3">'
      . '<fill><patternFill patternType="none"/></fill>'
      . '<fill><patternFill patternType="gray125"/></fill>'
      . '<fill><patternFill patternType="solid"><fgColor rgb="FFB4531F"/><bgColor indexed="64"/></patternFill></fill>'
      . '</fills>'
      . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
      . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
      . '<cellXfs count="3">'
      . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
      . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
      . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
      . '</cellXfs>'
      . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
      . '</styleSheet>';

  $zip = new ZipArchive();
  $rc  = $zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
  if ($rc !== true) throw new Exception('ساخت فایل اکسل ناموفق بود (کد ' . $rc . ')');
  $zip->addFromString('[Content_Types].xml', $CT);
  $zip->addFromString('_rels/.rels', $RELS);
  $zip->addFromString('xl/workbook.xml', $WB);
  $zip->addFromString('xl/_rels/workbook.xml.rels', $WBREL);
  $zip->addFromString('xl/styles.xml', $STYLES);

  foreach ($sheets as $i => $sh) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
         . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
         . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews>';
    $widths = $sh['widths'] ?? [];
    if ($widths) {
      $xml .= '<cols>';
      foreach ($widths as $ci => $w) {
        $xml .= '<col min="' . ($ci + 1) . '" max="' . ($ci + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
      }
      $xml .= '</cols>';
    }
    $xml .= '<sheetData>';
    foreach (($sh['rows'] ?? []) as $ri => $row) {
      $xml .= '<row r="' . ($ri + 1) . '">';
      foreach (array_values((array)$row) as $ci => $cell) {
        $isArr = is_array($cell);
        $val   = $isArr ? ($cell['v'] ?? '') : $cell;
        $style = $isArr ? (string)($cell['s'] ?? '') : '';
        /* v5.0.4-test.5: ['v'=>..., 't'=>'s'] یعنی «حتماً متن» — برای شمارهٔ موبایل تا صفرِ اولش در اکسل حذف نشود */
        $forceStr = $isArr && (($cell['t'] ?? '') === 's');
        $sAttr = $style === 'h' ? ' s="2"' : ($style === 'b' ? ' s="1"' : '');
        $ref   = xl_colref($ci + 1, $ri + 1);
        if (!$forceStr && (is_int($val) || is_float($val) || (is_string($val) && $val !== '' && preg_match('/^\d+(\.\d+)?$/', $val)))) {
          $xml .= '<c r="' . $ref . '"' . $sAttr . '><v>' . (0 + $val) . '</v></c>';
        } elseif ($val !== '' && $val !== null) {
          $xml .= '<c r="' . $ref . '" t="inlineStr"' . $sAttr . '><is><t xml:space="preserve">' . xl_esc((string)$val) . '</t></is></c>';
        }
      }
      $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $xml);
  }

  $zip->close();
}

/* ═══════════════ خواندن xlsx ═══════════════ خروجی: [نام شیت => ردیف‌ها] */

function xl_read(string $path): array {
  if (!class_exists('ZipArchive')) throw new Exception('افزونهٔ zip روی سرور در دسترس نیست');
  $zip = new ZipArchive();
  $rc  = $zip->open($path, ZipArchive::RDONLY);
  if ($rc !== true) throw new Exception('فایل اکسل باز نشد — فایل خراب است یا اکسل واقعی (xlsx) نیست؛ فرمت‌های xls و csv پذیرفته نمی‌شوند');
  $err = function (string $m) use ($zip): never { $zip->close(); throw new Exception($m); };

  $wb = $zip->getFromName('xl/workbook.xml');
  if ($wb === false) $err('ساختار فایل اکسل معتبر نیست (workbook پیدا نشد)');

  /* رابطه‌ها: rId → مسیر فایل شیت */
  $relTargets = [];
  if (($relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels')) !== false) {
    $rels = @simplexml_load_string($relsXml);
    if ($rels) foreach ($rels->Relationship as $r) {
      $id = (string)($r['Id'] ?? ''); $t = (string)($r['Target'] ?? '');
      if ($id !== '' && $t !== '') {
        $t = ltrim($t, '/');
        if (!str_starts_with($t, 'xl/')) $t = 'xl/' . $t;
        $relTargets[$id] = $t;
      }
    }
  }

  /* شیت‌ها به ترتیب کتاب */
  $sheetList = [];
  $wbDoc = @simplexml_load_string($wb);
  if (!$wbDoc) $err('ساختار فایل اکسل معتبر نیست (workbook قابل خواندن نیست)');
  foreach ($wbDoc->sheets->sheet as $s) {
    $sheetList[] = ['name' => (string)($s['name'] ?? ''), 'rid' => (string)($s['id'] ?? '')];
  }

  /* sharedStrings */
  $shared = [];
  if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
    $ss = @simplexml_load_string($ssXml);
    if ($ss) foreach ($ss->si as $si) {
      $txt = '';
      if (isset($si->t))            $txt = (string)$si->t;
      elseif (isset($si->r))        foreach ($si->r as $run) $txt .= (string)($run->t ?? '');
      $shared[] = $txt;
    }
  }

  $out = [];
  foreach ($sheetList as $si => $meta) {
    $target = $relTargets[$meta['rid']] ?? ('xl/worksheets/sheet' . ($si + 1) . '.xml');
    $sx = $zip->getFromName($target);
    if ($sx === false) { $out[$meta['name']] = []; continue; }
    $doc = @simplexml_load_string($sx);
    if (!$doc) { $out[$meta['name']] = []; continue; }

    $rows = []; $rNo = 0;
    foreach ($doc->sheetData->row as $row) {
      $rNo = isset($row['r']) ? max($rNo + 1, (int)$row['r']) : $rNo + 1;
      $line = []; $cNo = 0;
      foreach ($row->c as $c) {
        $ref  = (string)($c['r'] ?? '');
        if ($ref !== '' && preg_match('/^([A-Z]+)(\d+)$/', $ref, $m)) {
          $col = 0; foreach (str_split(strtoupper($m[1])) as $ch) $col = $col * 26 + (ord($ch) - 64);
          $cNo = max($cNo + 1, $col);
        } else $cNo++;
        $t = (string)($c['t'] ?? '');
        $v = '';
        if ($t === 's') { $idx = (int)($c->v ?? 0); $v = $shared[$idx] ?? ''; }
        elseif ($t === 'inlineStr') { $v = (string)($c->is->t ?? ''); foreach ($c->is->r ?? [] as $run) $v .= (string)($run->t ?? ''); }
        elseif ($t === 'str' || $t === 'n' || $t === '') { $v = (string)($c->v ?? ''); }
        elseif ($t === 'b') { $v = ((string)($c->v ?? '')) === '1' ? '1' : '0'; }
        $line[$cNo - 1] = trim($v);
      }
      if ($line) {
        $maxI = max(array_keys($line));
        $full = array_pad([], $maxI + 1, '');
        foreach ($line as $ci => $val) $full[$ci] = $val;
        $rows[$rNo - 1] = $full;
      }
    }
    if ($rows) {
      $maxRow = max(array_keys($rows));
      $outRows = array_pad([], $maxRow + 1, []);
      foreach ($rows as $ri => $r) $outRows[$ri] = $r;
      /* همهٔ ردیف‌ها تا پرستونِ ستونِ کل شیت پر می‌شوند تا ایندکس‌ها همیشه باشند */
      $maxCol = 0;
      foreach ($outRows as $r) $maxCol = max($maxCol, count($r));
      foreach ($outRows as $ri => $r) $outRows[$ri] = array_pad($r, $maxCol, '');
      $out[$meta['name']] = $outRows;
    } else $out[$meta['name']] = [];
  }
  $zip->close();
  return $out;
}

/* ═══════════════ محتوای قالب و منوی نمونه ═══════════════ */

function xl_help_rows(): array {
  return [
    ['v' => 'راهنمای پر کردن فایل منو — گیت‌آرتس', 's' => 'b'],
    [],
    [['v' => 'ستون', 's' => 'h'], ['v' => 'قاعده', 's' => 'h']],
    ['دسته', 'اجباری. نام دسته (مثل: قهوه گرم). ردیف‌های پشت‌سرهم با دستهٔ یکسان، یک دسته می‌شوند.'],
    ['نام محصول', 'اجباری. تا ۱۲۰ حرف.'],
    ['توضیح', 'اختیاری. توضیح کوتاه زیر نام محصول.'],
    ['قیمت (تومان)', 'اجباری. عدد صحیح ≥ ۱۰۰۰. با رقم فارسی یا جداکنندهٔ هزار هم قابل نوشتن است (۶۵٬۰۰۰).'],
    ['تخفیف ٪', 'اختیاری. عدد ۰ تا ۹۰ (مثلاً ۱۵ = ۱۵٪ تخفیف). خالی = بدون تخفیف.'],
    ['برچسب‌ها', 'اختیاری. از این چهار برچسب فقط: محبوب · پیشنهاد سرآشپز · امروز · ناموجود (با ویرگول جدا کنید).'],
    ['آپشن‌ها', 'اختیاری. قالب «نام:قیمت» با جداکنندهٔ | — مثل: شیر سویا:15000|شات اضافه:20000 (قیمت ۰ یا خالی = رایگان).'],
    ['موجودی', 'اختیاری. عدد (تعداد باقی‌مانده). خالی = بدون محدودیت. صفر = ناموجود نمایش داده می‌شود.'],
    ['تصویر (نام فایل)', 'اختیاری. اگر پیش‌تر عکسی در پنل آپلود کرده‌اید، نام دقیق فایلش را بنویسید (مثل u_250913ab12cd.webp) تا وصل شود؛ وگرنه تصویر را بعداً در پنل روی محصول بگذارید.'],
    [],
    [['v' => 'نکته‌ها', 's' => 'h'], ['v' => '', 's' => 'h']],
    ['۱', 'فقط شیت «' . XL_MENU_SHEET . '» خوانده می‌شود؛ شیت «' . XL_HELP_SHEET . '» آزاد است — آن را حذف یا تغییر ندهید.'],
    ['۲', 'سرستون‌های ردیف اول را جابه‌جا یا تغییرنام ندهید؛ ستون اضافه نزنید.'],
    ['۳', 'فرمت فقط xlsx است (xls و csv پذیرفته نمی‌شود). حداکثر حجم ۵ مگابایت و ۵۰۰ ردیف.'],
    ['۴', 'هیچ چیزی تا تأیید شما ذخیره نمی‌شود: بعد از بارگذاری، خلاصه را می‌بینید و «جایگزینی کامل» یا «افزودن به منوی فعلی» را انتخاب می‌کنید.'],
    ['۵', '«جایگزینی کامل» همهٔ دسته‌ها و محصولات فعلی را عوض می‌کند (لوگو، رنگ‌ها و بنرها دست‌نخورده می‌مانند).'],
  ];
}

function xl_template_xlsx(string $outPath): void {
  $rows = [
    array_map(fn($h) => ['v' => $h, 's' => 'h'], xl_headers()),
    ['نوشیدنی گرم', 'چای ماسالا', 'با ادویهٔ هندی و شیر', 75000, 10, 'محبوب', 'شیر:5000', '', ''],
    ['کیک و دسر', 'کیک شکلات', 'برش همان روز', 135000, '', '', '', 8, ''],
  ];
  xl_build($outPath, [
    ['name' => XL_MENU_SHEET, 'widths' => [16, 24, 34, 14, 10, 20, 34, 10, 24], 'rows' => $rows],
    ['name' => XL_HELP_SHEET, 'widths' => [18, 110], 'rows' => xl_help_rows()],
  ]);
}

/* منوی نمونهٔ پیش‌فرض — هم برای آشنایی با فیلدها هم برای تست ایمپورت */
function xl_sample_rows(): array {
  return [
    array_map(fn($h) => ['v' => $h, 's' => 'h'], xl_headers()),
    ['قهوه گرم', 'اسپرسو', 'شات کلاسیک با کرمای عنابی', 65000, '', 'محبوب', 'شات اضافه:20000|شیر سویا:15000', '', ''],
    ['قهوه گرم', 'کاپوچینو', 'یک‌سوم اسپرسو، یک‌سوم شیر، یک‌سوم فوم مخملی', 95000, '', '', '', '', ''],
    ['قهوه گرم', 'لاته', 'شیر مخملی روی اسپرسو', 90000, '', '', '', '', ''],
    ['قهوه گرم', 'موکا', 'شکلات تلخ، اسپرسو و شیر', 110000, 15, '', '', '', ''],
    ['قهوه گرم', 'فیلتر V60', 'دانهٔ روز؛ دم‌آوری چهار دقیقه', 85000, '', 'پیشنهاد سرآشپز', '', '', ''],
    ['چای و دمنوش', 'چای لاهیجان', 'کیوری، با قندان رنگی', 45000, '', '', '', '', ''],
    ['چای و دمنوش', 'چای ماسالا', 'ادویه‌های هندی با شیر', 75000, 10, '', '', '', ''],
    ['چای و دمنوش', 'دمنوش به‌لیمو', 'تازه و آرام‌بخش', 55000, '', 'امروز', '', '', ''],
    ['نوشیدنی سرد', 'آیس لاته', 'اسپرسو، شیر و یخ', 95000, '', '', '', '', ''],
    ['نوشیدنی سرد', 'لیموناد نعنا', 'لیموی تازه با نعنا', 80000, '', 'محبوب', '', '', ''],
    ['نوشیدنی سرد', 'موهیتو انار', 'انار تازه، نعنا و سودا', 95000, '', 'ناموجود', '', '', ''],
    ['نوشیدنی سرد', 'آیس آمریکانو', 'دبل شات روی یخ', 75000, '', '', '', '', ''],
    ['کیک و دسر', 'کیک شکلات تلخ', 'لایه‌های گاناش؛ برش همان روز', 135000, '', 'محبوب', '', '', ''],
    ['کیک و دسر', 'چیزکیک نیویورکی', 'با سس توت‌فرنگی', 155000, 20, '', '', '', ''],
    ['کیک و دسر', 'کیک هویج', 'با کرم پنیر و گردو', 125000, '', '', '', '', ''],
    ['صبحانه', 'املت ویژه', 'قارچ و پنیر، با نان تست', 125000, '', 'پیشنهاد سرآشپز', '', '', ''],
    ['صبحانه', 'نان و پنیر و گردو', 'پنیر لیقوان، گردوی تازه', 95000, '', '', '', 5, ''],
  ];
}

function xl_sample_xlsx(string $outPath): void {
  xl_build($outPath, [
    ['name' => XL_MENU_SHEET, 'widths' => [16, 24, 40, 14, 10, 20, 34, 10, 24], 'rows' => xl_sample_rows()],
    ['name' => XL_HELP_SHEET, 'widths' => [18, 110], 'rows' => xl_help_rows()],
  ]);
}

/* ═══════════════ پارس و اعتبارسنجی ردیف‌ها ═══════════════
   خروجی: ['cats'=>.., 'warnings'=>.., 'stats'=>['cats','items']] */

function xl_id(string $p): string { return $p . bin2hex(random_bytes(5)); }

function xl_parse_rows(array $rows, ?string $upDir): array {
  $warnings = [];
  /* فایل کلاً خالی؟ */
  $anyContent = false;
  foreach ($rows as $r) {
    if (trim(mb_substr(implode(' ', array_map('strval', (array)$r)), 0, 300)) !== '') { $anyContent = true; break; }
  }
  if (!$anyContent) throw new Exception('فایل اکسل خالی است');
  /* سرستون‌ها: تا ۳ ردیف اولِ پر بررسی می‌شود — ردیفی که بیشتر ستون‌های کلیدی را دارد */
  $headIdx = null; $headHits = 0;
  $scan = 0;
  foreach ($rows as $i => $r) {
    $joined = trim(mb_substr(implode(' ', array_map('strval', $r)), 0, 300));
    if ($joined === '') continue;
    if (++$scan > 3) break;
    $hits = 0;
    foreach ($r as $c) {
      $s = trim((string)$c);
      if (str_contains($s, 'دسته')) $hits++;
      elseif (str_contains($s, 'نام')) $hits++;
      elseif (str_contains($s, 'قیمت') || str_contains($s, 'تومان')) $hits++;
    }
    if ($hits >= 2 && $hits > $headHits) { $headIdx = $i; $headHits = $hits; }
  }
  if ($headIdx === null)
    throw new Exception('سرستون‌های شیت «' . XL_MENU_SHEET . '» پیدا نشد — ستون‌های «دسته»، «نام محصول» و «قیمت» باید در ردیف اول باشند. قالب آماده را دانلود کنید و روی همان بسازید');
  if ($headIdx > 0)
    $warnings[] = "ردیف(های) بالای سرستون نادیده گرفته شد";
  $head = $rows[$headIdx];

  $TAGS = ['محبوب' => 'popular', 'پیشنهاد سرآشپز' => 'chef', 'سرآشپز' => 'chef',
           'امروز' => 'daily', 'روز' => 'daily', 'ناموجود' => 'soldout'];

  $cats = []; $catIdx = []; $nItems = 0;

  foreach ($rows as $i => $r) {
    if ($i <= $headIdx) continue;
    $rowNo = $i + 1;
    if ($rowNo > XL_MAX_ROWS) { $warnings[] = "بیشتر از " . XL_MAX_ROWS . " ردیف نادیده گرفته شد"; break; }

    $get = fn(int $n): string => trim((string)($r[$n] ?? ''));
    if ($get(0) === '' && $get(1) === '' && $get(3) === '') continue;   /* ردیف کاملاً خالی */

    $catS = $get(0);
    if ($catS === '') throw new Exception('ردیف ' . xl_fa($rowNo) . ': ستون «دسته» خالی است — هر محصول باید دسته داشته باشد');
    $nameS = $get(1);
    if ($nameS === '') throw new Exception('ردیف ' . xl_fa($rowNo) . ': ستون «نام محصول» خالی است');
    if (mb_strlen($nameS) > 120) $nameS = mb_substr($nameS, 0, 120);

    $price = xl_num($get(3));
    if ($price === null || $price < 1000)
      throw new Exception('ردیف ' . xl_fa($rowNo) . ': قیمت «' . $get(3) . '» معتبر نیست — عدد صحیح ≥ ۱۰۰۰ تومان بنویسید');

    /* تخفیف */
    $off = 0; $offS = $get(4);
    if ($offS !== '') {
      $off = xl_num($offS);
      if ($off === null || $off < 0 || $off > 90)
        throw new Exception('ردیف ' . xl_fa($rowNo) . ': تخفیف «' . $offS . '» معتبر نیست — عدد ۰ تا ۹۰');
      if ($off === 0) $off = 0;
    }

    /* برچسب‌ها */
    $tagKeys = ['popular' => false, 'chef' => false, 'daily' => false, 'soldout' => false];
    $tagS = str_replace(['|', '؛', ';'], '،', $get(5));
    foreach (array_filter(array_map('trim', explode('،', $tagS))) as $tok) {
      $tokN = preg_replace('/\s+/u', ' ', $tok) ?? $tok;
      $hit = $TAGS[$tokN] ?? $TAGS[mb_strtolower($tokN)] ?? null;
      if ($hit) $tagKeys[$hit] = true;
      else $warnings[] = 'ردیف ' . xl_fa($rowNo) . ': برچسب «' . $tok . '» شناخته نشد و نادیده گرفته شد (برچسب‌های مجاز: محبوب، پیشنهاد سرآشپز، امروز، ناموجود)';
    }

    /* آپشن‌ها */
    $opts = [];
    $optS = str_replace(['؛', ';', "\n"], '|', $get(6));
    foreach (array_filter(array_map('trim', explode('|', $optS)), fn($x) => $x !== '') as $optTok) {
      if (count($opts) >= 10) { $warnings[] = 'ردیف ' . xl_fa($rowNo) . ': بیش از ۱۰ آپشن — بقیه نادیده گرفته شد'; break; }
      if (!str_contains($optTok, ':')) {
        /* آپشن رایگان بدون قیمت هم قابل قبول است */
        $opts[] = ['id' => xl_id('o'), 'name' => mb_substr($optTok, 0, 60), 'price' => 0];
        continue;
      }
      $parts = explode(':', $optTok, 2);
      $oName = trim($parts[0]); $oPrice = xl_num($parts[1] ?? '');
      if ($oName === '' || $oPrice === null || $oPrice < 0)
        throw new Exception('ردیف ' . xl_fa($rowNo) . ': قالب آپشن «' . $optTok . '» درست نیست — به شکل «نام:قیمت» بنویسید (قیمت ۰ = رایگان)');
      $opts[] = ['id' => xl_id('o'), 'name' => mb_substr($oName, 0, 60), 'price' => $oPrice];
    }

    /* موجودی */
    $stock = null; $stS = $get(7);
    if ($stS !== '') {
      $stock = xl_num($stS);
      if ($stock === null || $stock < 0 || $stock > 9999)
        throw new Exception('ردیف ' . xl_fa($rowNo) . ': موجودی «' . $stS . '» معتبر نیست — عدد ۰ تا ۹۹۹۹ یا خالی');
    }

    /* تصویر */
    $img = ''; $imgS = preg_replace('#^.*/#', '', $get(8)) ?? '';
    if ($imgS !== '') {
      if ($upDir !== null && is_file("$upDir/$imgS")) $img = "uploads/$imgS";
      else $warnings[] = 'ردیف ' . xl_fa($rowNo) . ': فایل تصویر «' . $imgS . '» در رسانه‌های کافه پیدا نشد — تصویر را بعداً در پنل روی محصول بگذارید';
    }

    /* دستهٔ مقصد — با نام یکسان ادغام می‌شود */
    if (!isset($catIdx[$catS])) {
      if (count($cats) >= XL_MAX_CATS) { $warnings[] = "بیشتر از " . XL_MAX_CATS . " دسته نادیده گرفته شد"; break; }
      $catIdx[$catS] = count($cats);
      $cats[] = ['id' => xl_id('c'), 'name' => mb_substr($catS, 0, 60), 'items' => []];
    }
    $ci = $catIdx[$catS];

    /* تکراری دقیق (نام + قیمت) در همان دسته → حذف با هشدار */
    $dup = false;
    foreach ($cats[$ci]['items'] as $it) {
      if ($it['name'] === $nameS && (int)$it['price'] === $price) { $dup = true; break; }
    }
    if ($dup) { $warnings[] = 'ردیف ' . xl_fa($rowNo) . ': «' . $nameS . '» با همین قیمت تکراری بود و یک‌بار وارد شد'; continue; }

    if (count($cats[$ci]['items']) >= XL_MAX_PER_CAT) { $warnings[] = 'دستهٔ «' . $catS . '» به سقف ' . XL_MAX_PER_CAT . ' محصول رسید — بقیهٔ ردیف‌هایش نادیده گرفته شد'; continue; }

    $cats[$ci]['items'][] = [
      'id' => xl_id('i'), 'name' => $nameS, 'desc' => mb_substr($get(2), 0, 400),
      'price' => $price, 'img' => $img, 'cost' => 0, 'stock' => $stock,
      'opts' => $opts,
      'tags' => ['popular' => $tagKeys['popular'], 'chef' => $tagKeys['chef'],
                 'daily' => $tagKeys['daily'], 'off' => $off, 'soldout' => $tagKeys['soldout']],
    ];
    $nItems++;
  }

  if (!$cats) throw new Exception('هیچ محصولی در فایل پیدا نشد — ردیف‌های زیر سرستون را پر کنید');
  foreach ($cats as $c) if (!$c['items']) $warnings[] = "دستهٔ «{$c['name']}» بدون محصول بود و حذف شد";
  $cats = array_values(array_filter($cats, fn($c) => $c['items']));
  $warnings = array_values(array_unique($warnings));

  return ['cats' => $cats, 'warnings' => $warnings, 'stats' => ['cats' => count($cats), 'items' => $nItems]];
}

/* ═══════════════ اندپوینت‌ها ═══════════════ */

/* بارگذاری فایل (multipart) → فقط پیش‌نمایش؛ ذخیره با excel_apply */
function excel_import(): array {
  t_require_admin();

  $f = $_FILES['file'] ?? null;
  if (!is_array($f)) throw new Exception('فایلی دریافت نشد');
  if ((int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
    throw new Exception(up_err_msg((int)($f['error'] ?? UPLOAD_ERR_NO_FILE)));
  if ((string)($f['tmp_name'] ?? '') === '' || !is_file((string)$f['tmp_name']))
    throw new Exception('فایلی دریافت نشد — لطفاً دوباره انتخاب و بارگذاری کنید');
  if ((int)$f['size'] > XL_MAX_FILE_MB * 1048576)
    throw new Exception('حجم فایل اکسل حداکثر ' . XL_MAX_FILE_MB . ' مگابایت');

  $name = (string)($f['name'] ?? '');
  if (!str_ends_with(strtolower($name), '.xlsx'))
    throw new Exception('فقط فایل اکسل با فرمت xlsx پذیرفته می‌شود — xls و csv کافی نیست؛ فایل را در اکسل با «ذخیره به‌عنوان xlsx» ذخیره کنید');

  $tmp = (string)$f['tmp_name'];
  $sheets = xl_read($tmp);

  /* شیت منو: اول تطبیق دقیق، بعد انگلیسی menu، وگرنه اولین شیت */
  $sheetName = null;
  foreach ($sheets as $nm => $_) {
    if ($nm === XL_MENU_SHEET || mb_strtolower(trim($nm)) === 'menu') { $sheetName = $nm; break; }
  }
  if ($sheetName === null) { $k = array_key_first($sheets); $sheetName = $k !== null ? $k : ''; }
  $rows = $sheets[$sheetName] ?? [];
  if (!$rows) throw new Exception('شیت «' . $sheetName . '» خالی است');

  $parsed = xl_parse_rows($rows, T_ROOT . '/uploads');
  $parsed['sheet'] = $sheetName;
  $parsed['note']  = 'هیچ چیز هنوز ذخیره نشده است — با یکی از دو دکمهٔ پایین اعمال کنید';
  return $parsed;
}

/* تأیید نهایی: replace = جایگزینی کامل دسته‌ها | append = افزودن/ادغام با نام دسته */
function excel_apply(array $in): array {
  $mode = (string)($in['mode'] ?? 'replace');
  if (!in_array($mode, ['replace', 'append'], true)) throw new Exception('حالت ایمپورت نامعتبر است');
  $inCats = $in['cats'] ?? null;
  if (!is_array($inCats) || !$inCats) throw new Exception('دادهٔ ایمپورت خالی است — دوباره فایل را بارگذاری کنید');
  if (count($inCats) > XL_MAX_CATS) throw new Exception('تعداد دسته‌ها از سقف مجاز بیشتر است');

  /* بازسازی کامل — به ورودی کاربر اعتماد نمی‌کنیم */
  $newCats = [];
  foreach ($inCats as $c) {
    if (!is_array($c)) continue;
    $name = trim((string)($c['name'] ?? ''));
    if ($name === '' || !is_array($c['items'] ?? null)) continue;
    $cat = ['id' => xl_id('c'), 'name' => mb_substr($name, 0, 60), 'items' => []];
    foreach (array_slice((array)$c['items'], 0, XL_MAX_PER_CAT) as $it) {
      if (!is_array($it)) continue;
      $nm = trim((string)($it['name'] ?? ''));
      if ($nm === '') continue;
      $price = xl_num($it['price'] ?? null);
      if ($price === null || $price < 1000) continue;

      $img = (string)($it['img'] ?? '');
      if ($img !== '' && !preg_match('#^uploads/[A-Za-z0-9._-]+$#', $img)) $img = '';

      $stock = null;
      if (isset($it['stock']) && $it['stock'] !== null) {
        $stock = xl_num($it['stock']);
        if ($stock === null || $stock < 0) $stock = null;
      }

      $opts = [];
      foreach (array_slice(is_array($it['opts'] ?? null) ? $it['opts'] : [], 0, 10) as $o) {
        if (!is_array($o)) continue;
        $oName = trim((string)($o['name'] ?? ''));
        $oPrice = xl_num($o['price'] ?? 0);
        if ($oName === '' || $oPrice === null || $oPrice < 0) continue;
        $opts[] = ['id' => xl_id('o'), 'name' => mb_substr($oName, 0, 60), 'price' => $oPrice];
      }

      $tg = is_array($it['tags'] ?? null) ? $it['tags'] : [];
      $off = xl_num($tg['off'] ?? 0);
      $off = ($off === null || $off < 0 || $off > 90) ? 0 : $off;
      $tags = [
        'popular' => (bool)($tg['popular'] ?? false),
        'chef'    => (bool)($tg['chef'] ?? false),
        'daily'   => (bool)($tg['daily'] ?? false),
        'off'     => $off,
        'soldout' => (bool)($tg['soldout'] ?? false),
      ];

      $cat['items'][] = [
        'id' => xl_id('i'), 'name' => mb_substr($nm, 0, 120),
        'desc' => mb_substr(trim((string)($it['desc'] ?? '')), 0, 400),
        'price' => $price, 'img' => $img, 'cost' => 0, 'stock' => $stock,
        'opts' => $opts, 'tags' => $tags,
      ];
    }
    if ($cat['items']) $newCats[] = $cat;
  }
  if (!$newCats) throw new Exception('هیچ دسته/محصول معتبری برای ذخیره پیدا نشد');

  $nC = count($newCats); $nI = 0; foreach ($newCats as $c) $nI += count($c['items']);

  return t_db_txn(function (array &$db) use ($newCats, $mode, $nC, $nI): array {
    $menu = $db['menu'] ?? [];
    if ($mode === 'replace') {
      $menu['cats'] = $newCats;
    } else {
      $cur = is_array($menu['cats'] ?? null) ? $menu['cats'] : [];
      foreach ($newCats as $nc) {
        $hit = false;
        foreach ($cur as $i => $cc) {
          if (is_array($cc) && trim((string)($cc['name'] ?? '')) === $nc['name']) {
            $cur[$i]['items'] = array_merge(
              is_array($cc['items'] ?? null) ? $cc['items'] : [],
              $nc['items']
            );
            $hit = true; break;
          }
        }
        if (!$hit) $cur[] = $nc;
      }
      $menu['cats'] = $cur;
    }
    $db['menu'] = $menu;
    return t_state_out($db);
  });
}

/* ═══════════════ v5.0.4-test.5: خروجی اکسل باشگاه مشتریان ═══════════════
   فایلی آمادهٔ ایمپورت در دفترچهٔ تلفن پنل‌های پیامکی (کاوه‌نگار، ملی‌پیامک، SMS.ir و…)
   + شیت راهنما؛ «به‌روزرسانی روزانه» = هر بار دانلود، فایل از دیتای زندهٔ همان لحظه ساخته می‌شود */

/* تاریخ عضویت — شمسی اگر intl در دسترس بود، وگرنه میلادی */
function xl_jdate(int $ms): string {
  $ts = intdiv($ms, 1000);
  if ($ts <= 0) return '';
  if (class_exists('IntlDateFormatter')) {
    try {
      $df = new IntlDateFormatter('fa_IR@calendar=persian', IntlDateFormatter::LONG, IntlDateFormatter::NONE,
        date_default_timezone_get(), IntlDateFormatter::TRADITIONAL, 'yyyy/MM/dd');
      $s = $df->format($ts);
      if ($s !== false && $s !== null) return $s;
    } catch (Throwable $e) { /* افتادن به میلادی */ }
  }
  return date('Y-m-d', $ts);
}

function xl_customers_xlsx(string $outPath, array $customers, array $orders): void {
  /* آمار خرید هر شماره از همهٔ سفارش‌ها */
  $stat = [];
  foreach ($orders as $o) {
    $p = (string)($o['phone'] ?? '');
    if ($p === '') continue;
    if (!isset($stat[$p])) $stat[$p] = ['n' => 0, 'sum' => 0];
    $stat[$p]['n']++;
    $stat[$p]['sum'] += (int)($o['total'] ?? 0);
  }
  /* وفادارترین‌ها (بیشترین امتیاز) بالا */
  usort($customers, fn($a, $b) => ((int)($b['points'] ?? 0)) <=> ((int)($a['points'] ?? 0)));
  /* v5.0.4-test.6: ستون «شماره اشتراک» (۱ تا ۵ رقم، شناسهٔ یکتای عضو) اولِ فهرست
     + ستون «لوکیشن / آدرس» (آخرین لوکیشن ثبت‌شده — برای سفارش از راه دور) */
  $rows = [
    array_map(fn($h) => ['v' => $h, 's' => 'h'],
      ['شماره اشتراک', 'نام', 'موبایل', 'امتیاز', 'تاریخ عضویت', 'تعداد سفارش', 'مجموع خرید (تومان)', 'لوکیشن / آدرس']),
  ];
  foreach ($customers as $c) {
    $p = (string)($c['phone'] ?? '');
    $rows[] = [
      (int)($c['sub_no'] ?? 0),
      (string)($c['name'] ?? ''),
      ['v' => $p, 't' => 's'],   /* موبایل = متن تا صفرِ اول حذف نشود */
      (int)($c['points'] ?? 0),
      xl_jdate((int)($c['since'] ?? 0)),
      (int)($stat[$p]['n'] ?? 0),
      (int)($stat[$p]['sum'] ?? 0),
      (string)($c['loc'] ?? ''),
    ];
  }
  $help = [
    ['v' => 'راهنمای انتقال مشتریان به پنل پیامکی — گیت‌آرتس', 's' => 'b'],
    [],
    ['این فایل هر بار که در پنل، «دانلود اکسل مشتریان» را می‌زنید از دیتای زندهٔ کافه ساخته می‌شود؛ برای به‌روزرسانی روزانه کافی است هر روز یک‌بار دانلود و در پنل پیامکی ایمپورت کنید.'],
    ['ستون «موبایل» به‌صورت متن ذخیره شده تا صفر ابتدای شماره حذف نشود — مستقیم در دفترچهٔ تلفن پنل پیامکی (کاوه‌نگار، ملی‌پیامک، SMS.ir و…) قابل ایمپورت است.'],
    ['«امتیاز» = باقیماندهٔ باشگاه مشتریان (هر ۱۰٫۰۰۰ تومان خرید ۱ امتیاز؛ هر امتیاز در خرید بعدی ۱٫۰۰۰ تومان تخفیف).'],
    ['«شماره اشتراک» شناسهٔ یکتای هر عضو است (۱ تا ۵ رقم، به‌ترتیب عضویت) — برای سفارش تلفنی و پیگیری در پنل پیامکی قابل استفاده است.'],
    ['«لوکیشن / آدرس» آخرین لوکیشنِ اختیاری‌ای است که مشتری هنگام سفارش از راه دور نوشته (خالی = هنوز لوکیشنی ثبت نکرده).'],
    ['«تعداد سفارش» و «مجموع خرید» از همهٔ سفارش‌های ثبت‌شدهٔ همان شمارهٔ موبایل محاسبه شده است.'],
    ['مرتب‌سازی: بیشترین امتیاز (وفادارترین مشتریان) در بالای فهرست.'],
    ['این شیت آزاد است و خوانده نمی‌شود؛ فقط شیت «مشتریان» داده است.'],
  ];
  xl_build($outPath, [
    ['name' => 'مشتریان', 'widths' => [13, 24, 16, 10, 16, 13, 20, 44], 'rows' => $rows],
    ['name' => 'راهنما', 'widths' => [110], 'rows' => $help],
  ]);
}
