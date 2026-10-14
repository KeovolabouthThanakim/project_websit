<?php
namespace App\Models;

/**
 * Nhập hàng loạt câu hỏi trắc nghiệm từ Excel (.xlsx), CSV, Word (.docx), văn bản (.txt) hoặc dán trực tiếp.
 * Không dùng thư viện ngoài (hosting miễn phí không chạy được Composer): .xlsx và .docx thực chất là file zip chứa XML
 * nên đọc bằng ZipArchive + SimpleXML (cần extension zip - hầu hết hosting đều bật sẵn).
 *
 * Quy trình: đọc file -> danh sách câu "thô" (parseText / fromRows) -> check() kiểm tra từng câu theo ĐÚNG luật của
 * form thêm câu hỏi (2-6 đáp án, ít nhất 1 đúng, không phải tất cả đều đúng, không trùng đáp án...) -> xem trước -> nhập.
 * Phần phân tích là hàm thuần (không đụng CSDL) nên dễ kiểm thử.
 *
 * Mỗi câu thô: ['line' => số thứ tự / số dòng để báo lỗi, 'content', 'options' => [['text', 'mark']], 'answer' => chuỗi|null,
 *               'difficulty' => chuỗi|null, 'explanation' => chuỗi|null]
 *  - mark = true: đáp án được đánh dấu đúng ngay trên dòng (ký tự * đầu dòng)
 *  - answer: nội dung dòng "Đáp án: B, D" / ô "Đáp án đúng" (chữ cái hoặc số thứ tự)
 */
class QuestionImport
{
    public const MAX_FILE_BYTES = 2 * 1024 * 1024;
    public const MAX_QUESTIONS = 300;
    private const MAX_XML_BYTES = 20 * 1024 * 1024;   // chặn file zip "bom" (nén nhỏ, bung ra rất lớn)
    private const MAX_ROWS = 2000;
    private const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

    /** Đuôi file được nhận */
    public const EXTENSIONS = ['xlsx', 'csv', 'docx', 'txt'];

    // ------------------------------------------------------------------
    // Đọc file -> câu thô. Trả về [danh sách câu thô, thông báo lỗi ('' nếu ổn)]
    // ------------------------------------------------------------------

    public static function parseFile(string $path, string $ext): array
    {
        try {
            switch (strtolower($ext)) {
                case 'xlsx':
                    return [self::fromRows(self::readXlsx($path)), ''];
                case 'csv':
                    return [self::fromRows(self::readCsv($path)), ''];
                case 'docx':
                    return [self::parseText(self::readDocx($path)), ''];
                case 'txt':
                    return [self::parseText(self::readTextFile($path)), ''];
            }
        } catch (\RuntimeException $e) {
            return [[], $e->getMessage()];
        }
        return [[], 'Định dạng file không được hỗ trợ.'];
    }

    /** Đọc file văn bản UTF-8 (bỏ BOM). Không phải UTF-8 thì báo lỗi để tránh mất dấu tiếng Việt */
    public static function readTextFile(string $path): string
    {
        $raw = (string) file_get_contents($path);
        return self::utf8($raw);
    }

    private static function utf8(string $raw): string
    {
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        if ($raw !== '' && !preg_match('//u', $raw)) {
            throw new \RuntimeException('File không phải mã hóa UTF-8 nên bị lỗi dấu tiếng Việt. Hãy lưu lại dạng UTF-8 (Excel: "CSV UTF-8"; Notepad: Save as -> UTF-8).');
        }
        return $raw;
    }

    /** CSV (dấu phẩy hoặc chấm phẩy - Excel tiếng Việt thường dùng chấm phẩy) -> mảng các dòng */
    public static function readCsv(string $path): array
    {
        $raw = self::utf8((string) file_get_contents($path));
        $firstLine = strtok($raw, "\n") ?: '';
        $delim = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        if (substr_count($firstLine, "\t") > substr_count($firstLine, $delim)) {
            $delim = "\t";
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $delim, '"', '\\')) !== false) {
            if ($r === [null]) {
                continue;
            }
            $rows[] = array_map(fn($c) => (string) $c, $r);
            if (count($rows) > self::MAX_ROWS) {
                throw new \RuntimeException('File có quá nhiều dòng (tối đa ' . self::MAX_ROWS . ').');
            }
        }
        fclose($h);
        return $rows;
    }

    private static function openZip(string $path): \ZipArchive
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Máy chủ chưa bật extension zip của PHP nên không đọc được file Excel / Word. Hãy dùng file CSV hoặc dán nội dung trực tiếp.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Không mở được file. File có thể bị hỏng hoặc không đúng định dạng .xlsx / .docx.');
        }
        return $zip;
    }

    private static function zipXml(\ZipArchive $zip, string $name): ?\SimpleXMLElement
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new \RuntimeException('File quá lớn khi giải nén.');
        }
        $xml = $zip->getFromName($name);
        $prev = libxml_use_internal_errors(true);
        $doc = $xml === false ? false : simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $doc === false ? null : $doc;   // KHÔNG dùng `?:` - SimpleXML coi phần tử chỉ có con có tiền tố (w:...) là "rỗng"
    }

    /** Sheet đầu tiên của file .xlsx -> mảng các dòng (mỗi dòng là mảng ô theo thứ tự cột, ô trống = '') */
    public static function readXlsx(string $path): array
    {
        $zip = self::openZip($path);
        try {
            // Chuỗi dùng chung
            $shared = [];
            $ss = self::zipXml($zip, 'xl/sharedStrings.xml');
            if ($ss !== null) {
                foreach ($ss->si as $si) {
                    $shared[] = self::textOf($si);
                }
            }

            // Tìm sheet đầu tiên qua workbook.xml + rels (không giả định tên sheet1.xml)
            $target = 'worksheets/sheet1.xml';
            $wb = self::zipXml($zip, 'xl/workbook.xml');
            $rels = self::zipXml($zip, 'xl/_rels/workbook.xml.rels');
            if ($wb !== null && $rels !== null && isset($wb->sheets->sheet[0])) {
                $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($rels->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = (string) $rel['Target'];
                        $target = ltrim(strpos($t, '/') === 0 ? substr($t, 4) : $t, '/'); // "/xl/worksheets/.." hoặc "worksheets/.."
                        break;
                    }
                }
            }
            $sheet = self::zipXml($zip, 'xl/' . preg_replace('#^xl/#', '', $target));
            if ($sheet === null) {
                throw new \RuntimeException('Không đọc được bảng tính trong file Excel.');
            }

            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $row) {
                $cells = [];
                $next = 0;
                foreach ($row->c as $c) {
                    $col = isset($c['r']) ? self::colIndex((string) $c['r']) : $next;
                    $next = $col + 1;
                    $type = (string) $c['t'];
                    if ($type === 's') {
                        $val = $shared[(int) $c->v] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $val = self::textOf($c->is);
                    } elseif ($type === 'b') {
                        $val = (string) $c->v === '1' ? 'TRUE' : 'FALSE';
                    } else {
                        $val = (string) $c->v;     // số / chuỗi công thức: lấy giá trị đã tính
                        if ($type === '' && preg_match('/^-?\d+\.0+$/', $val)) {
                            $val = (string) (int) $val;
                        }
                    }
                    $cells[$col] = $val;
                }
                $line = [];
                $max = $cells ? max(array_keys($cells)) : -1;
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = (string) ($cells[$i] ?? '');
                }
                $rows[] = $line;
                if (count($rows) > self::MAX_ROWS) {
                    throw new \RuntimeException('File có quá nhiều dòng (tối đa ' . self::MAX_ROWS . ').');
                }
            }
            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** Ghép mọi <t> trong một ô / chuỗi dùng chung (kể cả chữ định dạng nhiều đoạn), bỏ phần phiên âm */
    private static function textOf(?\SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        $out = '';
        if (isset($node->t)) {
            $out .= (string) $node->t;
        }
        foreach ($node->r ?? [] as $run) {
            $out .= (string) $run->t;
        }
        return $out;
    }

    /** "C5" -> 2 (cột C, tính từ 0) */
    private static function colIndex(string $ref): int
    {
        preg_match('/^[A-Za-z]+/', $ref, $m);
        $n = 0;
        foreach (str_split(strtoupper($m[0] ?? 'A')) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** File .docx -> văn bản thuần, mỗi đoạn một dòng */
    public static function readDocx(string $path): string
    {
        $zip = self::openZip($path);
        try {
            $doc = self::zipXml($zip, 'word/document.xml');
            if ($doc === null) {
                throw new \RuntimeException('Không đọc được nội dung file Word (.docx).');
            }
            $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $doc->registerXPathNamespace('w', $w);
            $lines = [];
            foreach ($doc->xpath('//w:p') ?: [] as $p) {
                $p->registerXPathNamespace('w', $w);
                $text = '';
                foreach ($p->xpath('.//w:t | .//w:tab | .//w:br') ?: [] as $n) {
                    $name = $n->getName();
                    $text .= $name === 't' ? (string) $n : ($name === 'tab' ? ' ' : "\n");
                }
                foreach (explode("\n", $text) as $l) {
                    $lines[] = $l;
                }
                if (count($lines) > self::MAX_ROWS * 10) {
                    throw new \RuntimeException('File quá dài.');
                }
            }
            return implode("\n", $lines);
        } finally {
            $zip->close();
        }
    }

    // ------------------------------------------------------------------
    // Văn bản (Word / .txt / dán) -> câu thô
    //
    //   Câu 1: Thiết bị nào sau đây là thiết bị nhập?
    //   A. Màn hình
    //   *B. Bàn phím          <- dấu * đầu dòng = đáp án đúng (hoặc dùng dòng "Đáp án:")
    //   C. Máy in
    //   Đáp án: B             <- nhiều đáp án: "Đáp án: A, C"
    //   Giải thích: ...       (không bắt buộc)
    //   Độ khó: Dễ            (Dễ / Trung bình / Khó, không bắt buộc)
    // ------------------------------------------------------------------

    public static function parseText(string $text): array
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}", "\u{2028}"], ["\n", "\n", ' ', "\n"], $text);
        $questions = [];
        $cur = null;
        $field = null;    // 'content' | 'option' | 'explanation' - nơi dòng tiếp theo (không có nhãn) được nối vào
        $num = 0;

        $flush = function () use (&$cur, &$questions) {
            if ($cur !== null) {
                $questions[] = $cur;
            }
            $cur = null;
        };

        foreach (explode("\n", $text) as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Câu mới: "Câu 3:", "Câu 3.", "Câu 3)", "Question 3:", "3." / "3)" ở đầu dòng
            // ("3." trần chỉ tính khi không đang viết giải thích, vì giải thích hay có danh sách đánh số 1. 2. 3.)
            if (preg_match('/^(?:c[âa]u|question|q)\s*\.?\s*(\d+)\s*[:.)\-–]?\s*(.*)$/iu', $line, $m)
                || ($field !== 'explanation' && preg_match('/^(\d{1,4})\s*[.)]\s+(.+)$/u', $line, $m))) {
                $flush();
                $num++;
                $cur = ['line' => $num, 'content' => trim($m[2]), 'options' => [], 'answer' => null, 'difficulty' => null, 'explanation' => null];
                $field = 'content';
                continue;
            }
            if ($cur === null) {
                continue;     // chữ trước câu đầu tiên (tiêu đề, hướng dẫn) - bỏ qua
            }

            // Đáp án đúng
            if (preg_match('/^(?:đáp\s*án(?:\s*đúng)?|đ\.?\s*a|answer|key)\s*[:：\-]\s*(.+)$/iu', $line, $m)) {
                $cur['answer'] = trim($m[1]);
                $field = null;
                continue;
            }
            if (preg_match('/^(?:giải\s*thích|explanation)\s*[:：]\s*(.*)$/iu', $line, $m)) {
                $cur['explanation'] = trim($m[1]);
                $field = 'explanation';
                continue;
            }
            if (preg_match('/^(?:độ\s*khó|mức\s*độ|difficulty|level)\s*[:：]\s*(.+)$/iu', $line, $m)) {
                $cur['difficulty'] = trim($m[1]);
                $field = null;
                continue;
            }

            // Đáp án: "A. ...", "B) ...", có thể có dấu * ở đầu (hoặc cuối) để đánh dấu đúng.
            // Chỉ nhận khi đúng chữ cái kế tiếp (A rồi B rồi C...) để không nhầm một dòng thường trong đề bài.
            $expected = self::LETTERS[count($cur['options'])] ?? null;
            if ($expected !== null && preg_match('/^(\*?)\s*([A-Fa-f])\s*[.):]\s*(.*)$/u', $line, $m) && strtoupper($m[2]) === $expected) {
                $cur['options'][] = ['text' => trim($m[3]), 'mark' => $m[1] === '*'];
                $field = 'option';
                continue;
            }

            // Dòng tiếp theo của nội dung / đáp án / giải thích đang viết dở
            if ($field === 'content') {
                $cur['content'] .= ($cur['content'] === '' ? '' : "\n") . $line;
            } elseif ($field === 'option') {
                $i = count($cur['options']) - 1;
                $cur['options'][$i]['text'] .= ' ' . $line;
            } elseif ($field === 'explanation') {
                $cur['explanation'] .= "\n" . $line;
            }
        }
        $flush();
        return $questions;
    }

    // ------------------------------------------------------------------
    // Bảng (Excel / CSV) -> câu thô
    // Cột: Câu hỏi | A | B | C | D | E | F | Đáp án đúng | Độ khó | Giải thích
    // Có dòng tiêu đề thì nhận theo tên cột (thứ tự cột tùy ý); không có thì theo thứ tự trên.
    // ------------------------------------------------------------------

    public static function fromRows(array $rows): array
    {
        // Bỏ dòng trống hoàn toàn
        $rows = array_values(array_filter($rows, fn($r) => trim(implode('', array_map('strval', $r))) !== ''));
        if (!$rows) {
            return [];
        }

        $map = self::headerMap($rows[0]);
        $startLine = 1;
        if ($map !== null) {
            array_shift($rows);
            $startLine = 2;
        } else {
            $map = ['content' => 0, 'options' => [1, 2, 3, 4, 5, 6], 'answer' => 7, 'difficulty' => 8, 'explanation' => 9];
        }

        $out = [];
        foreach ($rows as $i => $r) {
            $cell = fn($idx) => $idx === null ? '' : trim((string) ($r[$idx] ?? ''));
            // Giữ đúng vị trí cột (A, B, C...): ô trống ở giữa vẫn chiếm chỗ (sẽ báo lỗi) để chữ cái ở cột "Đáp án đúng" không bị lệch.
            // Chỉ bỏ các ô trống nằm sau đáp án cuối cùng.
            $opts = [];
            foreach ($map['options'] as $idx) {
                $opts[] = ['text' => $cell($idx), 'mark' => false];
            }
            while ($opts && $opts[count($opts) - 1]['text'] === '') {
                array_pop($opts);
            }
            $out[] = [
                'line'        => $startLine + $i,      // số dòng trong file (đúng với số dòng thấy trong Excel nếu không có dòng trống)
                'content'     => $cell($map['content']),
                'options'     => $opts,
                'answer'      => $cell($map['answer']) ?: null,
                'difficulty'  => $cell($map['difficulty']) ?: null,
                'explanation' => $cell($map['explanation']) ?: null,
            ];
        }
        return $out;
    }

    /** Nhận dạng dòng tiêu đề; trả về null nếu dòng đầu không phải tiêu đề */
    private static function headerMap(array $header): ?array
    {
        $map = ['content' => null, 'options' => [], 'answer' => null, 'difficulty' => null, 'explanation' => null];
        $letterCols = [];
        foreach ($header as $i => $h) {
            $f = self::fold((string) $h);
            if ($f === '') {
                continue;
            }
            if (preg_match('/^(?:dap an |phuong an |lua chon |option |answer )?([a-f])$/', $f, $m)) {
                $letterCols[ord($m[1]) - 97] = $i;
            } elseif (preg_match('/^(dap an dung|dap an|dung|correct|correct answer|answer|key)$/', $f)) {
                $map['answer'] = $i;
            } elseif (preg_match('/^(do kho|muc do|difficulty|level)$/', $f)) {
                $map['difficulty'] = $i;
            } elseif (preg_match('/^(giai thich|explanation)$/', $f)) {
                $map['explanation'] = $i;
            } elseif (preg_match('/^(cau hoi|noi dung|noi dung cau hoi|question|de bai)$/', $f)) {
                $map['content'] = $i;
            }
        }
        if ($map['content'] === null || count($letterCols) < 2) {
            return null;
        }
        ksort($letterCols);
        $map['options'] = array_values($letterCols);
        return $map;
    }

    /** Chữ thường, bỏ dấu tiếng Việt, gộp khoảng trắng: "Đáp án đúng" -> "dap an dung" */
    public static function fold(string $s): string
    {
        static $map = null;
        if ($map === null) {
            $groups = [
                'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
                'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
            ];
            $map = [];
            foreach ($groups as $base => $chars) {
                foreach (preg_split('//u', $chars, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                    $map[$ch] = $base;
                }
            }
        }
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = strtr($s, $map);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    // ------------------------------------------------------------------
    // Kiểm tra từng câu thô -> câu sạch hoặc danh sách lỗi
    // ------------------------------------------------------------------

    /**
     * @return array [câu sạch|null, lỗi[]]. Câu sạch: ['content', 'options' => [['content', 'is_correct']], 'difficulty', 'explanation'|null]
     * Luật giống hệt QuestionController::validate để câu nhập vào luôn hợp lệ như câu soạn tay.
     */
    public static function check(array $raw): array
    {
        $errors = [];

        $content = trim((string) $raw['content']);
        if ($content === '') {
            $errors[] = 'Thiếu nội dung câu hỏi.';
        } elseif (str_len($content) > 2000) {
            $errors[] = 'Nội dung câu hỏi quá 2000 ký tự.';
        }

        $explanation = trim((string) ($raw['explanation'] ?? ''));
        if (str_len($explanation) > 2000) {
            $errors[] = 'Giải thích quá 2000 ký tự.';
        }

        $diffKey = self::difficulty($raw['difficulty'] ?? null);
        if ($diffKey === null) {
            $errors[] = 'Độ khó "' . $raw['difficulty'] . '" không hợp lệ (dùng: Dễ, Trung bình, Khó).';
            $diffKey = 'medium';
        }

        $opts = [];
        $seen = [];
        $n = count($raw['options']);
        foreach ($raw['options'] as $i => $o) {
            $t = trim((string) $o['text']);
            if ($t === '') {
                $errors[] = 'Đáp án ' . self::LETTERS[$i] . ' đang để trống.';
                continue;
            }
            if (str_len($t) > 500) {
                $errors[] = 'Đáp án ' . self::LETTERS[$i] . ' quá 500 ký tự.';
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
            if (isset($seen[$key])) {
                $errors[] = 'Đáp án ' . self::LETTERS[$i] . ' trùng với đáp án ' . self::LETTERS[$seen[$key]] . '.';
            }
            $seen[$key] = $i;
            $opts[] = ['content' => $t, 'is_correct' => !empty($o['mark']) ? 1 : 0];
        }
        if ($n < Question::MIN_OPTIONS) {
            $errors[] = 'Cần ít nhất ' . Question::MIN_OPTIONS . ' đáp án' . ($n === 0 ? ' (nếu file Word dùng danh sách tự đánh số thì hãy gõ tay "A.", "B."...).' : '.');
        } elseif ($n > Question::MAX_OPTIONS) {
            $errors[] = 'Tối đa ' . Question::MAX_OPTIONS . ' đáp án.';
        }

        // Đáp án đúng: từ dòng "Đáp án: ..." / cột "Đáp án đúng", cộng với đáp án đánh dấu *
        $answer = trim((string) ($raw['answer'] ?? ''));
        if ($answer !== '') {
            [$idxs, $bad] = self::answerIndexes($answer, $n);
            if ($bad !== '') {
                $errors[] = $bad;
            }
            foreach ($idxs as $i) {
                if (isset($opts[$i])) {
                    $opts[$i]['is_correct'] = 1;
                }
            }
        }
        $correct = array_sum(array_column($opts, 'is_correct'));
        if ($n >= Question::MIN_OPTIONS && $n <= Question::MAX_OPTIONS) {
            if ($correct < 1) {
                if ($answer === '') {
                    $errors[] = 'Chưa chỉ ra đáp án đúng (thêm dòng "Đáp án: B" hoặc cột "Đáp án đúng").';
                }
            } elseif ($correct === count($opts)) {
                $errors[] = 'Không thể để tất cả đáp án đều đúng.';
            }
        }

        if ($errors) {
            return [null, array_values(array_unique($errors))];
        }
        return [['content' => $content, 'options' => $opts, 'difficulty' => $diffKey, 'explanation' => $explanation === '' ? null : $explanation], []];
    }

    /** "B", "A, C", "A;C", "A và C", "1,3" -> [chỉ số đáp án]; kèm thông báo lỗi nếu có chữ không hợp lệ */
    public static function answerIndexes(string $answer, int $optionCount): array
    {
        $s = self::fold($answer);
        $s = preg_replace('/\b(va|and)\b/', ' ', $s);
        if (!preg_match('/^[a-f1-6\s,;\/&+\-]+$/', $s)) {
            return [[], 'Đáp án đúng "' . $answer . '" không hợp lệ (ghi chữ cái, ví dụ: B hoặc A, C).'];
        }
        $tokens = preg_split('/[\s,;\/&+\-]+/', trim($s), -1, PREG_SPLIT_NO_EMPTY);
        // "AC" / "ABD" viết liền
        $flat = [];
        foreach ($tokens as $t) {
            if (preg_match('/^[a-f]+$/', $t) && strlen($t) > 1) {
                foreach (str_split($t) as $ch) {
                    $flat[] = $ch;
                }
            } else {
                $flat[] = $t;
            }
        }
        $idx = [];
        foreach ($flat as $t) {
            $i = ctype_digit($t) ? (int) $t - 1 : ord($t) - 97;
            if ($i < 0 || $i >= $optionCount) {
                return [[], 'Đáp án đúng "' . $answer . '" nhắc tới đáp án không có trong câu hỏi.'];
            }
            $idx[$i] = $i;
        }
        return [array_values($idx), ''];
    }

    /** Chuỗi độ khó -> 'easy' | 'medium' | 'hard'; rỗng = medium; không nhận ra = null */
    public static function difficulty(?string $v): ?string
    {
        $f = self::fold((string) $v);
        if ($f === '') {
            return 'medium';
        }
        $map = ['de' => 'easy', 'easy' => 'easy', 'd' => 'easy', '1' => 'easy',
                'trung binh' => 'medium', 'tb' => 'medium', 'medium' => 'medium', 'tbinh' => 'medium', '2' => 'medium',
                'kho' => 'hard', 'hard' => 'hard', 'k' => 'hard', '3' => 'hard'];
        return $map[$f] ?? null;
    }

    // ------------------------------------------------------------------
    // Mẫu tải về
    // ------------------------------------------------------------------

    public const SAMPLE_TEXT = "Câu 1: Thiết bị nào sau đây là thiết bị nhập (input) của máy tính?\nA. Màn hình\n*B. Bàn phím\nC. Máy in\nD. Loa\nGiải thích: Bàn phím đưa dữ liệu vào máy tính; màn hình và máy in là thiết bị xuất.\nĐộ khó: Dễ\n\nCâu 2: Phần mềm nào sau đây là hệ điều hành? (chọn tất cả đáp án đúng)\nA. Windows\nB. Microsoft Word\nC. Linux\nD. Google Chrome\nĐáp án: A, C\nĐộ khó: Trung bình\n";

    /** Dòng mẫu cho file Excel / CSV */
    public static function sampleRows(): array
    {
        return [
            ['Câu hỏi', 'A', 'B', 'C', 'D', 'E', 'F', 'Đáp án đúng', 'Độ khó', 'Giải thích'],
            ['Thiết bị nào sau đây là thiết bị nhập (input) của máy tính?', 'Màn hình', 'Bàn phím', 'Máy in', 'Loa', '', '', 'B', 'Dễ', 'Bàn phím đưa dữ liệu vào máy tính.'],
            ['Phần mềm nào sau đây là hệ điều hành? (chọn tất cả đáp án đúng)', 'Windows', 'Microsoft Word', 'Linux', 'Google Chrome', '', '', 'A, C', 'Trung bình', 'Windows và Linux là hệ điều hành.'],
            ['1 KB bằng bao nhiêu byte?', '100 byte', '1000 byte', '1024 byte', '2048 byte', '', '', 'C', 'Dễ', ''],
        ];
    }

    public static function templateXlsx(): string
    {
        return self::buildXlsx(self::sampleRows(), 'Cau hoi');
    }

    /**
     * Tạo file .xlsx tối giản từ mảng dòng (chuỗi ghi trực tiếp - Excel / LibreOffice / Google Sheets đều mở được).
     * Dùng chung cho file mẫu nhập câu hỏi và nhập người dùng. Trả về nội dung nhị phân.
     */
    public static function buildXlsx(array $sampleRows, string $sheetName): string
    {
        $esc = fn(string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rowsXml = '';
        foreach ($sampleRows as $r => $cells) {
            $rowsXml .= '<row r="' . ($r + 1) . '">';
            foreach ($cells as $c => $v) {
                if ($v === '') {
                    continue;
                }
                $ref = chr(65 + $c) . ($r + 1);
                $rowsXml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>';
            }
            $rowsXml .= '</row>';
        }
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="60" customWidth="1"/><col min="2" max="7" width="20" customWidth="1"/><col min="8" max="9" width="14" customWidth="1"/><col min="10" max="10" width="40" customWidth="1"/></cols><sheetData>' . $rowsXml . '</sheetData></worksheet>',
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'qtpl');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $xml) {
            $zip->addFromString($name, $xml);
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }
}
