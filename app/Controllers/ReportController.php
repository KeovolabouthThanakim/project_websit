<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Xlsx;
use App\Models\Report;
use App\Models\Result;

/** Báo cáo tổng quan kết quả thi toàn hệ thống (chỉ quản trị viên): theo môn học / lớp / học kỳ, xuất Excel (.xlsx) hoặc CSV. */
class ReportController extends Controller
{
    public function index(): void
    {
        [$all, $rows, $f] = $this->load();
        $this->view('reports/index', [
            'title'    => 'Báo cáo tổng quan',
            'f'        => $f,
            'groups'   => Report::group($rows, $f['by']),
            'total'    => Report::total($rows),
            'exams'    => array_slice(Report::perExam($rows), 0, 100),
            'examCount' => count(Report::perExam($rows)),
            'options'  => $this->options($all),
            'pass'     => Result::passScore(),
            'terms'    => Report::terms(),
            'xlsxOk'   => Xlsx::available(),
        ]);
    }

    /** Xuất báo cáo: ?format=xlsx (mặc định, file Excel thật) hoặc ?format=csv. Máy chủ không có extension zip thì tự dùng CSV. */
    public function export(): void
    {
        [$all, $rows, $f] = $this->load();
        $by = $f['by'];
        $format = (string) $this->request->input('format', 'xlsx');
        $base = 'bao-cao-theo-' . $by . '-' . date('Ymd');

        if ($format !== 'csv' && Xlsx::available()) {
            $this->exportXlsx($all, $rows, $f, $base . '.xlsx');
        }
        $this->exportCsv($rows, $by, $base . '.csv');
    }

    private function exportXlsx(array $all, array $rows, array $f, string $filename): void
    {
        $by = $f['by'];
        $opt = $this->options($all);
        $withSub = $by === 'subject';                       // cột "Mô tả" (tên môn) chỉ có khi nhóm theo môn học

        $filters = ['Nhóm theo: ' . Report::BY[$by]];
        if ($f['semester'] !== '') {
            $filters[] = 'Học kỳ: ' . ($opt['semesters'][$f['semester']] ?? $f['semester']);
        }
        if ($f['subject_id']) {
            $filters[] = 'Môn học: ' . ($opt['subjects'][$f['subject_id']] ?? $f['subject_id']);
        }
        if ($f['class_id']) {
            $filters[] = 'Lớp: ' . ($opt['classes'][$f['class_id']] ?? $f['class_id']);
        }
        $filters[] = 'Xuất lúc ' . date('d/m/Y H:i');

        $header = array_merge([Report::BY[$by]], $withSub ? ['Mô tả'] : [],
            ['Số kỳ thi', 'Lượt được thi', 'Đã nộp', 'Tỷ lệ tham gia', 'Điểm TB', 'Tỷ lệ đạt (từ ' . fmt_score(Result::passScore()) . ')']);
        $table = [
            [school_name() . ' - Báo cáo tổng quan kết quả thi'],
            [implode('  |  ', $filters)],
            [],
            $header,
        ];
        $groups = Report::group($rows, $by);
        $total = Report::total($rows);
        foreach (array_merge($groups, [$total]) as $g) {
            $table[] = array_merge([$g['label']], $withSub ? [$g['sub']] : [],
                [(int) $g['exams'], (int) $g['expected'], (int) $g['submitted'], $g['participation'], $g['avg'], $g['pass_rate']]);
        }

        // Cột số liệu: sau khi có / không có cột "Mô tả" thì lệch 1 vị trí
        $o = $withSub ? 1 : 0;
        try {
            $binary = Xlsx::build($table, 'Bao cao', [
                'title_rows' => 3,
                'header'     => true,
                'bold_rows'  => [count($table) - 1],        // dòng "Tổng"
                'formats'    => [4 + $o => 'pct', 5 + $o => 'dec2', 6 + $o => 'pct'],
                'widths'     => [0 => 22] + ($withSub ? [1 => 40] : []),
            ]);
        } catch (\RuntimeException $e) {
            $this->exportCsv($rows, $by, preg_replace('/\.xlsx$/', '.csv', $filename));
            return;
        }
        Xlsx::download($binary, $filename);
    }

    private function exportCsv(array $rows, string $by, string $filename): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();                                 // bỏ mọi thứ đã lỡ in ra (cảnh báo PHP...) để file tải về không bị hỏng
        }
        $out = "\xEF\xBB\xBF" . implode(',', [Report::BY[$by], 'Mô tả', 'Số kỳ thi', 'Lượt được thi', 'Đã nộp', 'Tỷ lệ tham gia', 'Điểm TB', 'Tỷ lệ đạt (từ ' . Result::passScore() . ')']) . "\r\n";
        $pc = fn($v) => $v === null ? '' : number_format($v * 100, 1, '.', '') . '%';
        foreach (array_merge(Report::group($rows, $by), [Report::total($rows)]) as $g) {
            $cells = [$g['label'], $g['sub'], $g['exams'], $g['expected'], $g['submitted'], $pc($g['participation']),
                      $g['avg'] === null ? '' : number_format($g['avg'], 2, '.', ''), $pc($g['pass_rate'])];
            $out .= implode(',', array_map(fn($c) => '"' . str_replace('"', '""', (string) ExamResultController::csvCell($c)) . '"', $cells)) . "\r\n";
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($out));
        echo $out;
        exit;
    }

    /** [mọi dòng gốc, dòng đã lọc, bộ lọc đã chuẩn hóa] */
    private function load(): array
    {
        $all = Report::baseRows();
        $by = (string) $this->request->input('by', 'subject');
        $sem = (string) $this->request->input('semester', '');
        $subject = (string) $this->request->input('subject_id', '');
        $class = (string) $this->request->input('class_id', '');
        $f = [
            'by'         => isset(Report::BY[$by]) ? $by : 'subject',
            'semester'   => in_array($sem, array_column($all, 'semester_key'), true) ? $sem : '',
            'subject_id' => ctype_digit($subject) ? (int) $subject : 0,
            'class_id'   => ctype_digit($class) ? (int) $class : 0,
        ];
        return [$all, Report::filter($all, $f['semester'], $f['subject_id'], $f['class_id']), $f];
    }

    /** Danh sách chọn cho bộ lọc, lấy từ dữ liệu thực có */
    private function options(array $all): array
    {
        $sem = $sub = $cls = [];
        foreach ($all as $r) {
            $sem[$r['semester_key']] = $r['semester_label'];
            $sub[(int) $r['subject_id']] = $r['subject_code'] . ' - ' . $r['subject_name'];
            $cls[(int) $r['class_id']] = $r['class_code'];
        }
        krsort($sem);
        asort($sub);
        asort($cls);
        return ['semesters' => $sem, 'subjects' => $sub, 'classes' => $cls];
    }
}
