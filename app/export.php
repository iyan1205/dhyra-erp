<?php
/**
 * ============================================================
 *  EKSPOR: Excel (.xlsx tanpa library tambahan), dokumen cetak/PDF (HTML),
 *  dan dokumen faktur / purchase order.
 * ============================================================
 */
declare(strict_types=1);

const X_PINE = '0F3B35', X_INK = '16211D', X_MUT = '65736C', X_LINE = 'DFE4DB', X_SOFT = 'E3EFE9', X_SOFT2 = 'CFE5DB', X_NEG = 'B4322B';

/* ---------------------------- ZIP (metode simpan, tanpa ekstensi zip) ---------------------------- */
function zip_store(array $files): string
{
    $out = ''; $cen = ''; $off = 0; $t = getdate();
    $dt = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday']; $tm = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
    foreach ($files as $name => $data) {
        $crc = crc32($data); $sz = strlen($data); $nl = strlen($name);
        $lh = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $tm, $dt, $crc, $sz, $sz, $nl, 0) . $name;
        $cen .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $tm, $dt, $crc, $sz, $sz, $nl, 0, 0, 0, 0, 0, $off) . $name;
        $out .= $lh . $data; $off += strlen($lh) + $sz;
    }
    return $out . $cen . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($cen), $off, 0);
}
function xe($s): string { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)($s ?? '')), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function xcol(int $i): string { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; }

class XStyle
{
    public array $fonts = [], $fills = [], $borders = [], $xfs = [], $memo = [];
    function __construct()
    {
        $this->fonts[] = '<font><sz val="10"/><color rgb="FF' . X_INK . '"/><name val="Arial"/></font>';
        $this->fills[] = '<fill><patternFill patternType="none"/></fill>'; $this->fills[] = '<fill><patternFill patternType="gray125"/></fill>';
        $this->borders[] = '<border><left/><right/><top/><bottom/><diagonal/></border>';
        $this->xfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>';
    }
    private function idx(array &$a, string $x): int { $i = array_search($x, $a, true); if ($i === false) { $a[] = $x; $i = count($a) - 1; } return $i; }
    /** d: b, i, sz, c, bg, h, wrap, fmt(164=angka akuntansi, 165=tanggal), bt, bb (warna), bbd (double) */
    function get(array $d): int
    {
        $key = json_encode($d); if (isset($this->memo[$key])) return $this->memo[$key];
        $fi = $this->idx($this->fonts, '<font>' . (!empty($d['b']) ? '<b/>' : '') . (!empty($d['i']) ? '<i/>' : '') . '<sz val="' . ($d['sz'] ?? 10) . '"/><color rgb="FF' . ($d['c'] ?? X_INK) . '"/><name val="Arial"/></font>');
        $fl = !empty($d['bg']) ? $this->idx($this->fills, '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $d['bg'] . '"/><bgColor indexed="64"/></patternFill></fill>') : 0;
        $bd = '<border><left/><right/>' . (!empty($d['bt']) ? '<top style="thin"><color rgb="FF' . $d['bt'] . '"/></top>' : '<top/>') .
            (!empty($d['bbd']) ? '<bottom style="double"><color rgb="FF' . $d['bbd'] . '"/></bottom>' : (!empty($d['bb']) ? '<bottom style="thin"><color rgb="FF' . $d['bb'] . '"/></bottom>' : '<bottom/>')) . '<diagonal/></border>';
        $bi = $this->idx($this->borders, $bd);
        $nf = (int)($d['fmt'] ?? 0);
        $al = '<alignment' . (!empty($d['h']) ? ' horizontal="' . $d['h'] . '"' : '') . ' vertical="center"' . (!empty($d['wrap']) ? ' wrapText="1"' : '') . '/>';
        $this->xfs[] = '<xf numFmtId="' . $nf . '" fontId="' . $fi . '" fillId="' . $fl . '" borderId="' . $bi . '" xfId="0"' . ($nf ? ' applyNumberFormat="1"' : '') . ' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $al . '</xf>';
        return $this->memo[$key] = count($this->xfs) - 1;
    }
    function xml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0;[Red](#,##0)"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/></numFmts>'
            . '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts><fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>'
            . '<borders count="' . count($this->borders) . '">' . implode('', $this->borders) . '</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
function xl_date(string $s): ?float { if (!is_date($s)) return null; return (strtotime($s . ' 00:00:00 UTC') / 86400) + 25569; }

/** Laporan/tabel -> .xlsx: kop perusahaan, judul, header, isi berformat, total (rumus SUM). */
function export_table_xlsx(array $t, array $st, array $user): string
{
    $X = new XStyle(); $nc = count($t['columns']); $rowsX = []; $merges = [];
    $cell = function ($v, array $s, ?string $f = null) use ($X) { return ['v' => $v, 's' => $X->get($s), 'f' => $f]; };
    $contact = implode('   |   ', array_filter([$st['Alamat'] ?? '', $st['Telepon'] ?? '', $st['Email'] ?? ''])) . (!empty($st['NPWP']) ? '   |   NPWP ' . $st['NPWP'] : '');
    $rowsX[] = ['h' => 24, 'c' => [$cell($st['NamaPerusahaan'] ?: APP_NAME, ['b' => 1, 'sz' => 14, 'c' => X_PINE])]];
    $rowsX[] = ['h' => 16, 'c' => array_merge([$cell($contact, ['sz' => 8, 'c' => X_MUT, 'bb' => X_PINE])], array_fill(0, $nc - 1, $cell(null, ['bb' => X_PINE])))];
    $rowsX[] = ['h' => 22, 'c' => [$cell($t['title'], ['b' => 1, 'sz' => 12])]];
    $rowsX[] = ['h' => 16, 'c' => [$cell($t['subtitle'] ?? '', ['sz' => 8, 'c' => X_MUT])]];
    $HR = 5;
    $rowsX[] = ['h' => 26, 'c' => array_map(fn($c) => $cell($c['label'], ['b' => 1, 'c' => 'FFFFFF', 'bg' => X_PINE, 'wrap' => 1, 'h' => $c['type'] === 'num' ? 'right' : ($c['type'] === 'date' ? 'center' : 'left')]), $t['columns'])];
    $n = count($t['rows']);
    foreach ($t['rows'] as $k => $r) {
        $sty = $t['styles'][$k] ?? 'i'; $cs = [];
        foreach ($t['columns'] as $j => $c) {
            $s = ['bb' => X_LINE, 'b' => $sty !== 'i' ? 1 : 0];
            if ($sty === 'h') $s['bg'] = X_SOFT; if ($sty === 'g') { $s['bg'] = X_SOFT2; $s['bt'] = X_INK; $s['bbd'] = X_INK; } if ($sty === 't') $s['bt'] = X_INK;
            if ($sty === 'w') { $s['bg'] = 'FBEAE8'; $s['c'] = X_NEG; }
            $v = $r[$j] ?? null;
            if ($c['type'] === 'num') { $s['h'] = 'right'; $s['fmt'] = 164; $v = ($v === null || $v === '') ? null : (float)$v; }
            elseif ($c['type'] === 'date') { $s['h'] = 'center'; $s['fmt'] = 165; $v = is_date($v) ? xl_date($v) : null; }
            else { $s['wrap'] = 1; $v = $v === null ? '' : (string)$v; }
            $cs[] = $cell($v, $s);
        }
        $rowsX[] = ['h' => 0, 'c' => $cs];
    }
    if (!empty($t['sum']['cols'])) {
        $cs = [];
        for ($j = 0; $j < $nc; $j++) {
            $s = ['b' => 1, 'bg' => X_SOFT2, 'bt' => X_INK, 'bbd' => X_INK];
            if (in_array($j, $t['sum']['cols'], true)) { $s['h'] = 'right'; $s['fmt'] = 164; $cl = xcol($j); $sum = 0; foreach ($t['rows'] as $r) $sum += (float)($r[$j] ?? 0); $cs[] = $cell(r2($sum), $s, $n ? 'SUM(' . $cl . ($HR + 1) . ':' . $cl . ($HR + $n) . ')' : null); }
            elseif ($j === max(0, $t['sum']['cols'][0] - 1)) { $s['h'] = 'right'; $cs[] = $cell($t['sum']['label'] ?? 'TOTAL', $s); }
            else $cs[] = $cell(null, $s);
        }
        $rowsX[] = ['h' => 22, 'c' => $cs];
    }
    $rowsX[] = ['h' => 0, 'c' => []];
    $rowsX[] = ['h' => 0, 'c' => [$cell('Dicetak oleh ' . ($user['nama'] ?: $user['username']) . ' pada ' . date('d/m/Y H:i') . '   |   ' . APP_NAME, ['i' => 1, 'sz' => 7, 'c' => X_MUT])]];

    // lebar kolom menurut isi
    $W = [];
    foreach ($t['columns'] as $j => $c) {
        $mx = mb_strlen($c['label']);
        foreach (array_slice($t['rows'], 0, 400) as $r) { $v = $r[$j] ?? ''; $l = $c['type'] === 'num' ? strlen(fmt_num($v)) + 2 : ($c['type'] === 'date' ? 10 : mb_strlen((string)$v)); $mx = max($mx, $l); }
        $W[] = $c['type'] === 'num' ? min(max($mx + 2, 13), 20) : ($c['type'] === 'date' ? 12 : min(max($mx + 2, 8), 45));
    }
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="' . $HR . '" topLeftCell="A' . ($HR + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><cols>';
    foreach ($W as $i => $w) $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    $xml .= '</cols><sheetData>';
    foreach ($rowsX as $ri => $r) {
        $xml .= '<row r="' . ($ri + 1) . '"' . ($r['h'] ? ' ht="' . $r['h'] . '" customHeight="1"' : '') . '>';
        foreach ($r['c'] as $ci => $c) {
            $ref = xcol($ci) . ($ri + 1);
            if ($c['f']) $xml .= '<c r="' . $ref . '" s="' . $c['s'] . '"><f>' . $c['f'] . '</f><v>' . $c['v'] . '</v></c>';
            elseif ($c['v'] === null || $c['v'] === '') $xml .= '<c r="' . $ref . '" s="' . $c['s'] . '"/>';
            elseif (is_int($c['v']) || is_float($c['v'])) $xml .= '<c r="' . $ref . '" s="' . $c['s'] . '"><v>' . $c['v'] . '</v></c>';
            else $xml .= '<c r="' . $ref . '" s="' . $c['s'] . '" t="inlineStr"><is><t xml:space="preserve">' . xe($c['v']) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData><pageMargins left="0.45" right="0.45" top="0.5" bottom="0.6" header="0.3" footer="0.3"/><pageSetup paperSize="9" orientation="' . ($nc > 6 ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>'
        . '<headerFooter><oddFooter>&amp;R&amp;8Halaman &amp;P dari &amp;N</oddFooter></headerFooter></worksheet>';
    $sheetName = xe(mb_substr(preg_replace('#[\\\\/?*\[\]:]#', '', $t['title']), 0, 31) ?: 'Laporan');
    return zip_store([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $sheetName . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'' . str_replace("'", "''", $sheetName) . '\'!$' . $HR . ':$' . $HR . '</definedName></definedNames></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $X->xml(),
        'xl/worksheets/sheet1.xml' => $xml,
    ]);
}

/* ---------------------------- Dokumen cetak / PDF (HTML) ---------------------------- */
function doc_css(bool $landscape = false): string
{
    return '<style>@page{size:A4 ' . ($landscape ? 'landscape' : 'portrait') . ';margin:12mm 11mm 14mm}*{box-sizing:border-box}body{font-family:Arial,Helvetica,sans-serif;color:#16211d;font-size:10px;margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.kop{display:flex;align-items:center;gap:12px;border-bottom:2px solid #0f3b35;padding-bottom:6px;margin-bottom:10px}.kop img{max-height:44px;max-width:120px}.kop h1{font-size:16px;margin:0;color:#0f3b35}.kop p{margin:2px 0 0;color:#65736c;font-size:8.5px}
h2{font-size:13px;margin:0 0 2px}.sub{color:#65736c;font-size:8.5px;margin-bottom:8px}table{width:100%;border-collapse:collapse}thead{display:table-header-group}th{background:#0f3b35;color:#fff;text-align:left;padding:6px 6px;font-size:9px}
td{padding:4px 6px;border-bottom:1px solid #dfe4db;vertical-align:top}.r{text-align:right;white-space:nowrap}.c{text-align:center;white-space:nowrap}tr.h td{background:#e3efe9;font-weight:bold}tr.t td{font-weight:bold;border-top:1px solid #16211d}
tr.g td{background:#cfe5db;font-weight:bold;border-top:1px solid #16211d;border-bottom:3px double #16211d}tr.w td{background:#fbeae8;color:#b4322b;font-weight:bold}.neg{color:#b4322b}.ft{margin-top:12px;color:#65736c;font-size:7.5px;font-style:italic}tr{page-break-inside:avoid}</style>';
}
function doc_kop(array $st): string
{
    $contact = implode('  |  ', array_filter([$st['Alamat'] ?? '', $st['Telepon'] ?? '', $st['Email'] ?? ''])) . (!empty($st['NPWP']) ? '  |  NPWP ' . $st['NPWP'] : '');
    return '<div class="kop">' . (!empty($st['LogoUrl']) ? '<img src="' . h($st['LogoUrl']) . '" alt="">' : '') . '<div><h1>' . h($st['NamaPerusahaan'] ?: APP_NAME) . '</h1><p>' . h($contact) . '</p></div></div>';
}
function cell_html($v, string $type): string
{
    if ($type === 'num') { if ($v === null || $v === '') return '<td class="r"></td>'; $n = (float)$v; return '<td class="r' . ($n < 0 ? ' neg' : '') . '">' . fmt_num($n) . '</td>'; }
    if ($type === 'date') return '<td class="c">' . h(dmy($v)) . '</td>';
    return '<td>' . h($v) . '</td>';
}
function export_table_html(array $t, array $st, array $user): string
{
    $nc = count($t['columns']);
    $h = '<!doctype html><html><head><meta charset="utf-8"><title>' . h($t['title']) . '</title>' . doc_css($nc > 6) . '</head><body>' . doc_kop($st)
        . '<h2>' . h($t['title']) . '</h2><div class="sub">' . h($t['subtitle'] ?? '') . '</div><table><thead><tr>';
    foreach ($t['columns'] as $c) $h .= '<th class="' . ($c['type'] === 'num' ? 'r' : ($c['type'] === 'date' ? 'c' : '')) . '">' . h($c['label']) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($t['rows'] as $k => $r) {
        $s = $t['styles'][$k] ?? 'i';
        $h .= '<tr class="' . ($s === 'i' ? '' : $s) . '">';
        foreach ($t['columns'] as $j => $c) $h .= cell_html($r[$j] ?? null, $c['type']);
        $h .= '</tr>';
    }
    if (!empty($t['sum']['cols'])) {
        $h .= '<tr class="g">';
        for ($j = 0; $j < $nc; $j++) {
            if (in_array($j, $t['sum']['cols'], true)) { $s = 0; foreach ($t['rows'] as $r) $s += (float)($r[$j] ?? 0); $h .= '<td class="r">' . fmt_num($s) . '</td>'; }
            elseif ($j === max(0, $t['sum']['cols'][0] - 1)) $h .= '<td class="r">' . h($t['sum']['label'] ?? 'TOTAL') . '</td>';
            else $h .= '<td></td>';
        }
        $h .= '</tr>';
    }
    return $h . '</tbody></table><div class="ft">Dicetak oleh ' . h($user['nama'] ?: $user['username']) . ' pada ' . date('d/m/Y H:i') . '  |  ' . APP_NAME . '</div></body></html>';
}

/* ---------------------------- Terbilang ---------------------------- */
function terbilang($n): string
{
    $n = (int)floor(abs(round((float)$n)));
    $sat = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    $t = function ($x) use (&$t, $sat): string {
        if ($x < 12) return $sat[$x];
        if ($x < 20) return $sat[$x - 10] . ' belas';
        if ($x < 100) return $sat[intdiv($x, 10)] . ' puluh' . ($x % 10 ? ' ' . $sat[$x % 10] : '');
        if ($x < 200) return 'seratus' . ($x - 100 ? ' ' . $t($x - 100) : '');
        if ($x < 1000) return $sat[intdiv($x, 100)] . ' ratus' . ($x % 100 ? ' ' . $t($x % 100) : '');
        if ($x < 2000) return 'seribu' . ($x - 1000 ? ' ' . $t($x - 1000) : '');
        foreach ([[1000000000000, 'triliun'], [1000000000, 'miliar'], [1000000, 'juta'], [1000, 'ribu']] as $u)
            if ($x >= $u[0]) return $t(intdiv($x, $u[0])) . ' ' . $u[1] . ($x % $u[0] ? ' ' . $t($x % $u[0]) : '');
        return '';
    };
    return $n === 0 ? 'nol' : $t($n);
}
function terbilang_title($n, string $mu): string { return ucwords(terbilang($n)) . ' ' . ($mu === 'Rp' ? 'Rupiah' : $mu); }

/* ---------------------------- Faktur / Purchase Order ---------------------------- */
function faktur_html(array $d, array $user): string
{
    $f = $d['faktur']; $st = settings_all(); $mu = $st['MataUang'] ?: 'Rp'; $kon = $d['kon'] ?? []; $jual = $f['tipe'] !== 'Pembelian';
    $money = fn($v) => h($mu) . ' ' . fmt_num($v);
    $qty = fn($q) => number_format((float)$q, fmod((float)$q, 1.0) ? 2 : 0, ',', '.');
    $statusCls = ['Lunas' => '#0f7a52', 'Jatuh Tempo' => '#b4322b', 'Sebagian' => '#25569b'][$f['status']] ?? '#65736c';
    $kontak = $f['kontak'] ?: ($kon['nama'] ?? '');
    $kline = implode('  |  ', array_filter([$kon['telepon'] ?? '', $kon['email'] ?? '']));
    $h = '<!doctype html><html><head><meta charset="utf-8"><title>' . h($f['nomor']) . '</title>' . doc_css(false) . '<style>
.hd{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f3b35;padding-bottom:8px;margin-bottom:14px}.hd .co{display:flex;gap:12px;align-items:center}.hd img{max-height:52px;max-width:140px}
.hd h1{font-size:18px;margin:0;color:#0f3b35}.hd p{margin:2px 0 0;color:#65736c;font-size:9px}.doc{text-align:right}.doc b{display:block;font-size:17px;color:#0f3b35;letter-spacing:.5px}.doc .no{font-size:12px;font-weight:bold;margin-top:2px}.doc p{margin:2px 0 0}
.two{display:flex;justify-content:space-between;margin-bottom:14px}.lbl{font-size:8px;font-weight:bold;color:#65736c;letter-spacing:.6px}.kn{font-size:13px;font-weight:bold;margin:3px 0 2px}.st{font-size:12px;font-weight:bold;color:' . $statusCls . '}
td{padding:6px}th{padding:7px 6px}.sum{width:300px;margin:10px 0 0 auto}.sum td{border:0;padding:4px 6px}.sum .tot td{font-size:12px;font-weight:bold;border-top:1px solid #16211d;border-bottom:3px double #16211d}.sum .sisa td{background:#cfe5db;font-weight:bold}
.note{margin-top:12px;font-size:9.5px}.tt{display:flex;justify-content:space-between;margin-top:34px;font-size:9.5px}.tt div{text-align:center;width:200px}.tt .ln{margin-top:52px;color:#65736c}</style></head><body>';
    $contact = implode('  |  ', array_filter([$st['Telepon'] ?? '', $st['Email'] ?? '']));
    $h .= '<div class="hd"><div class="co">' . (!empty($st['LogoUrl']) ? '<img src="' . h($st['LogoUrl']) . '" alt="">' : '') . '<div><h1>' . h($st['NamaPerusahaan'] ?: APP_NAME) . '</h1><p>' . h($st['Alamat'] ?? '') . '</p><p>' . h($contact) . '</p>' . (!empty($st['NPWP']) ? '<p>NPWP ' . h($st['NPWP']) . '</p>' : '') . '</div></div>'
        . '<div class="doc"><b>' . ($jual ? 'FAKTUR PENJUALAN' : 'PURCHASE ORDER') . '</b><div class="no">' . h($f['nomor']) . '</div><p>Tanggal ' . h(dmy($f['tanggal'])) . '</p><p>Jatuh tempo ' . h(dmy($f['jatuhTempo'])) . '</p></div></div>';
    $h .= '<div class="two"><div><div class="lbl">' . ($jual ? 'DITAGIHKAN KEPADA' : 'PEMASOK') . '</div><div class="kn">' . h($kontak) . '</div>' . (!empty($kon['alamat']) ? '<div>' . h($kon['alamat']) . '</div>' : '') . ($kline ? '<div>' . h($kline) . '</div>' : '') . (!empty($kon['npwp']) ? '<div>NPWP ' . h($kon['npwp']) . '</div>' : '') . '</div>'
        . '<div style="text-align:right"><div class="lbl">STATUS</div><div class="st">' . h(mb_strtoupper($f['status'])) . '</div></div></div>';
    $h .= '<table><thead><tr><th class="c" style="width:34px">No</th><th>Deskripsi</th><th class="r" style="width:60px">Qty</th><th class="r" style="width:110px">Harga</th><th class="r" style="width:120px">Jumlah</th></tr></thead><tbody>';
    foreach ($d['items'] as $i => $it) $h .= '<tr><td class="c">' . ($i + 1) . '</td><td>' . h($it['d']) . '</td><td class="r">' . $qty($it['q']) . '</td><td class="r">' . fmt_num($it['h']) . '</td><td class="r">' . fmt_num(r2((float)$it['q'] * (float)$it['h'])) . '</td></tr>';
    $h .= '</tbody></table><table class="sum"><tr><td class="r">Subtotal</td><td class="r">' . $money($f['subtotal']) . '</td></tr>';
    if ((float)$f['ppn'] > 0) $h .= '<tr><td class="r">PPN ' . h(jn($f['persenPPN'])) . '%</td><td class="r">' . $money($f['ppn']) . '</td></tr>';
    $h .= '<tr class="tot"><td class="r">TOTAL</td><td class="r">' . $money($f['total']) . '</td></tr>';
    if ((float)$f['terbayar'] > 0) $h .= '<tr><td class="r">Terbayar</td><td class="r">' . $money($f['terbayar']) . '</td></tr>';
    $h .= '<tr class="sisa"><td class="r">SISA TAGIHAN</td><td class="r">' . $money($f['sisa']) . '</td></tr></table>';
    $h .= '<div class="note"><i>Terbilang: ' . h(terbilang_title($f['total'], $mu)) . '</i></div>';
    if (trim((string)($f['ket'] ?? '')) !== '') $h .= '<div class="note">Catatan: ' . h($f['ket']) . '</div>';
    if ($d['pembayaran']) {
        $h .= '<div class="note" style="margin-top:14px"><div class="lbl">RIWAYAT PEMBAYARAN</div><table style="margin-top:4px">';
        foreach ($d['pembayaran'] as $p) $h .= '<tr><td>' . h(dmy($p['tanggal'])) . '</td><td>' . h($p['akunKasNama'] ?: $p['akunKas']) . '</td><td>' . h($p['catatan']) . '</td><td class="r">' . $money($p['jumlah']) . '</td></tr>';
        $h .= '</table></div>';
    }
    if ($jual) { if (!empty($st['FakturCatatan'])) $h .= '<div class="note" style="margin-top:14px">' . nl2br(h($st['FakturCatatan'])) . '</div>'; if (!empty($st['FakturPenutup'])) $h .= '<div class="note" style="color:#65736c">' . nl2br(h($st['FakturPenutup'])) . '</div>'; }
    else $h .= '<div class="note" style="margin-top:14px">Purchase order ini merupakan permintaan pembelian barang / jasa kepada pemasok.</div>';
    $h .= '<div class="tt"><div>' . ($jual ? 'Hormat kami,' : 'Dibuat oleh,') . '<div class="ln">( .............................. )</div></div><div>' . ($jual ? 'Diterima oleh,' : 'Disetujui oleh,') . '<div class="ln">( .............................. )</div></div></div>';
    return $h . '<div class="ft">Dicetak dari ' . APP_NAME . ' oleh ' . h($user['nama'] ?: $user['username']) . ' pada ' . date('d/m/Y H:i') . '</div></body></html>';
}
