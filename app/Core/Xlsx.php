<?php
namespace App\Core;

/**
 * Tạo file Excel (.xlsx) không cần thư viện ngoài (hosting miễn phí không chạy được Composer): .xlsx là file zip chứa XML,
 * ghi bằng ZipArchive (extension zip - hầu hết hosting đều bật sẵn). Excel / LibreOffice / Google Sheets đều mở được.
 *
 * Khác bộ tạo mẫu đơn giản ở QuestionImport::buildXlsx:
 *  - số ghi là SỐ thật (Excel cộng / sắp xếp / vẽ biểu đồ được), chuỗi ghi là chuỗi (không bao giờ bị hiểu là công thức,
 *    nên không cần chặn "CSV injection");
 *  - có dòng tiêu đề in đậm + nền xám, dòng tổng in đậm, định dạng % và 2 số lẻ, đóng băng dòng tiêu đề, tự chỉnh độ rộng cột;
 *  - đánh được cột quá Z (AA, AB...).
 *
 * Dùng: Xlsx::build($rows, 'Ten sheet', ['title_rows' => 2, 'header' => true, 'bold_rows' => [7], 'formats' => [5 => 'pct', 6 => 'dec2']])
 *  - $rows: mảng các dòng; mỗi ô là null / '' (ô trống), số (int|float) hoặc chuỗi.
 *  - title_rows: số dòng đầu là tiêu đề / ghi chú (in đậm dòng đầu), tiếp theo là dòng tiêu đề cột.
 *  - header: dòng ngay sau title_rows là tiêu đề cột (in đậm, nền xám, được đóng băng).
 *  - bold_rows: chỉ số dòng (tính từ 0) cần in đậm, vd dòng "Tổng".
 *  - formats: [chỉ số cột từ 0 => 'pct' (0.0%, ô chứa số 0-1) | 'dec2' (0.00) | 'int' (#,##0)].
 *  - widths: [chỉ số cột => độ rộng]; không đặt thì tự tính theo nội dung.
 */
class Xlsx
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    // Chỉ số kiểu ô (cellXfs) trong styles.xml bên dưới
    private const S_DEFAULT = 0;
    private const S_HEADER  = 1;
    private const S_TITLE   = 2;
    private const FMT_BASE  = [      // [bình thường, in đậm]
        'pct'  => [3, 6],
        'dec2' => [4, 7],
        'int'  => [5, 8],
    ];
    private const S_BOLD = 9;

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /** Tên cột Excel từ chỉ số 0: 0 -> A, 25 -> Z, 26 -> AA */
    public static function colName(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    /** Tên sheet hợp lệ: tối đa 31 ký tự, không chứa : \ / ? * [ ] */
    public static function sheetName(string $name): string
    {
        $name = trim((string) preg_replace('/[:\\\\\/?*\[\]]+/u', ' ', $name));
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 31, 'UTF-8') : substr($name, 0, 31);
        return $name === '' ? 'Sheet1' : $name;
    }

    public static function build(array $rows, string $sheetName = 'Sheet1', array $opts = []): string
    {
        $titleRows = (int) ($opts['title_rows'] ?? 0);
        $hasHeader = !empty($opts['header']);
        $headerRow = $titleRows;                       // chỉ số dòng tiêu đề cột (nếu có)
        $bold = array_flip($opts['bold_rows'] ?? []);
        $formats = $opts['formats'] ?? [];

        $esc = fn(string $s) => htmlspecialchars(self::cleanXml($s), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $maxCols = 0;
        $widths = [];
        $rowsXml = '';
        foreach (array_values($rows) as $r => $cells) {
            $cells = array_values((array) $cells);
            $maxCols = max($maxCols, count($cells));
            $rowsXml .= '<row r="' . ($r + 1) . '">';
            foreach ($cells as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $ref = self::colName($c) . ($r + 1);

                if ($hasHeader && $r === $headerRow) {
                    $style = self::S_HEADER;
                } elseif ($r < $titleRows) {
                    $style = $r === 0 ? self::S_TITLE : self::S_DEFAULT;
                } elseif (isset($bold[$r])) {
                    $style = isset($formats[$c], self::FMT_BASE[$formats[$c]]) && is_numeric($v) ? self::FMT_BASE[$formats[$c]][1] : self::S_BOLD;
                } else {
                    $style = isset($formats[$c], self::FMT_BASE[$formats[$c]]) && is_numeric($v) ? self::FMT_BASE[$formats[$c]][0] : self::S_DEFAULT;
                }

                if (is_int($v) || is_float($v)) {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '"><v>' . self::num($v) . '</v></c>';
                    $len = strlen((string) $v) + 2;
                } else {
                    $str = (string) $v;
                    $rowsXml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($str) . '</t></is></c>';
                    // Dòng tiêu đề lớn / ghi chú (title_rows) không được làm rộng cột
                    $len = $r < $titleRows ? 0 : (function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str)) + 2;
                }
                $widths[$c] = max($widths[$c] ?? 0, $len);
            }
            $rowsXml .= '</row>';
        }

        $colsXml = '';
        for ($c = 0; $c < $maxCols; $c++) {
            $w = $opts['widths'][$c] ?? max(8, min(60, $widths[$c] ?? 8));
            $colsXml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        }

        // Đóng băng tới hết dòng tiêu đề cột
        $freeze = '';
        if ($hasHeader) {
            $top = $headerRow + 1;
            $freeze = '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $top . '" topLeftCell="A' . ($top + 1) . '" activePane="bottomLeft" state="frozen"/>'
                    . '<selection pane="bottomLeft" activeCell="A' . ($top + 1) . '" sqref="A' . ($top + 1) . '"/></sheetView></sheetViews>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $files = [
            '[Content_Types].xml' => $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => $xml . '<workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $esc(self::sheetName($sheetName)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $xml . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => self::stylesXml($xml, $ns),
            'xl/worksheets/sheet1.xml' => $xml . '<worksheet xmlns="' . $ns . '">' . $freeze
                . ($colsXml !== '' ? '<cols>' . $colsXml . '</cols>' : '')
                . '<sheetData>' . $rowsXml . '</sheetData></worksheet>',
        ];

        return self::zip($files);
    }

    /**
     * styles.xml: 0 mặc định | 1 tiêu đề cột (đậm, nền xám, viền, căn giữa) | 2 tiêu đề lớn (đậm, cỡ 14)
     * | 3-5 % / 0.00 / #,##0 | 6-8 như trên nhưng đậm | 9 chữ đậm
     */
    private static function stylesXml(string $xml, string $ns): string
    {
        return $xml . '<styleSheet xmlns="' . $ns . '">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>'
            . '<fonts count="3">'
            .   '<font><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .   '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFE7E6E6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .   '<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .   '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .   '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .   '<xf numFmtId="2" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .   '<xf numFmtId="3" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .   '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** Số -> chuỗi dùng dấu chấm thập phân bất kể locale của máy chủ */
    private static function num($v): string
    {
        if (is_int($v)) {
            return (string) $v;
        }
        $s = rtrim(rtrim(number_format((float) $v, 10, '.', ''), '0'), '.');
        return $s === '' || $s === '-' ? '0' : $s;
    }

    /** Bỏ ký tự điều khiển không được phép trong XML 1.0 (nếu để lại, Excel báo "file bị hỏng") */
    private static function cleanXml(string $s): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    }

    /** Ghi các file XML vào zip tạm rồi đọc lại thành chuỗi nhị phân */
    private static function zip(array $files): string
    {
        if (!self::available()) {
            throw new \RuntimeException('Máy chủ chưa bật extension zip của PHP nên không tạo được file Excel.');
        }
        $dir = sys_get_temp_dir();
        if (!is_dir($dir) || !is_writable($dir)) {          // một số hosting khóa thư mục tạm -> dùng storage/ của ứng dụng
            $dir = ROOT . '/storage';
        }
        $tmp = tempnam($dir, 'xlsx');
        if ($tmp === false) {
            throw new \RuntimeException('Không tạo được file tạm để ghi Excel (kiểm tra quyền ghi thư mục tạm hoặc storage/).');
        }
        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Không mở được file tạm để ghi Excel.');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            if (!$zip->close()) {
                throw new \RuntimeException('Không ghi được file Excel.');
            }
            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /** Gửi file .xlsx xuống trình duyệt rồi dừng. Xóa mọi dữ liệu đã lỡ in ra trước đó (cảnh báo, khoảng trắng) để file không bị hỏng. */
    public static function download(string $binary, string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . self::MIME);
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $binary;
        exit;
    }
}
