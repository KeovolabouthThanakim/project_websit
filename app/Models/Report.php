<?php
namespace App\Models;

use App\Core\App;
use App\Core\Model;

/**
 * Báo cáo tổng quan toàn hệ thống cho quản trị viên: gom kết quả thi theo môn học / lớp / học kỳ.
 * Số liệu gốc là từng cặp (kỳ thi, lớp được thi); các nhóm được cộng dồn bằng tổng và số đếm nên điểm trung bình
 * và tỷ lệ đạt là chính xác (không phải "trung bình của các trung bình").
 *
 * Học kỳ KHÔNG lưu trong CSDL mà suy ra từ ngày mở cổng thi theo quy ước ở semesterOf() - không cần sửa dữ liệu cũ.
 * Chỉ tính bài đã nộp của sinh viên đang thuộc lớp được thi; kỳ thi bản nháp không có trong báo cáo.
 */
class Report extends Model
{
    protected static string $table = 'exams';

    public const BY = ['subject' => 'Môn học', 'class' => 'Lớp', 'semester' => 'Học kỳ'];

    /**
     * Quy ước học kỳ: [tên => tháng bắt đầu], đổi bằng khóa 'semesters' trong config.php. Mặc định:
     * HK1 từ tháng 8, HK2 từ tháng 2, HK hè từ tháng 6. Tháng nhỏ hơn mọi mốc (vd tháng 1) thuộc học kỳ cuối năm trước.
     * Năm học bắt đầu ở tháng của học kỳ đầu tiên trong danh sách.
     */
    public static function terms(): array
    {
        $t = App::config('semesters');
        if (!is_array($t) || !$t) {
            $t = ['HK1' => 8, 'HK2' => 2, 'HK hè' => 6];
        }
        return $t;
    }

    /** Timestamp -> ['key' => '2025-2026|HK1', 'label' => 'HK1 2025-2026'] */
    public static function semesterOf(int $ts, ?array $terms = null): array
    {
        $terms = $terms ?? self::terms();
        $yearStart = (int) reset($terms);
        $sorted = $terms;
        asort($sorted);
        $m = (int) date('n', $ts);
        $y = (int) date('Y', $ts);

        $name = array_key_last($sorted);          // tháng nhỏ hơn mọi mốc -> học kỳ cuối của năm học trước
        foreach ($sorted as $n => $start) {
            if ($start <= $m) {
                $name = $n;
            }
        }
        $ay = $m >= $yearStart ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
        return ['key' => $ay . '|' . $name, 'label' => $name . ' ' . $ay];
    }

    /**
     * Dòng gốc: mỗi (kỳ thi, lớp được thi) một dòng gồm subject_id/code/name, class_id/code, exam_id, title, start_at, status,
     * students (sĩ số lớp), n (bài đã nộp của lớp), sum_score, passed (bài đạt) và semester_key / semester_label.
     */
    public static function baseRows(): array
    {
        $stmt = self::db()->prepare(
            "SELECT e.id AS exam_id, e.title, e.start_at, e.status, s.id AS subject_id, s.code AS subject_code, s.name AS subject_name,
                    c.id AS class_id, c.code AS class_code,
                    (SELECT COUNT(*) FROM class_students cs WHERE cs.class_id = ec.class_id) AS students,
                    (SELECT COUNT(*) FROM exam_attempts a JOIN class_students cs2 ON cs2.user_id = a.user_id AND cs2.class_id = ec.class_id
                      WHERE a.exam_id = e.id AND a.status = 'submitted' AND a.score IS NOT NULL) AS n,
                    (SELECT COALESCE(SUM(a.score), 0) FROM exam_attempts a JOIN class_students cs2 ON cs2.user_id = a.user_id AND cs2.class_id = ec.class_id
                      WHERE a.exam_id = e.id AND a.status = 'submitted' AND a.score IS NOT NULL) AS sum_score,
                    (SELECT COUNT(*) FROM exam_attempts a JOIN class_students cs2 ON cs2.user_id = a.user_id AND cs2.class_id = ec.class_id
                      WHERE a.exam_id = e.id AND a.status = 'submitted' AND a.score >= ?) AS passed
             FROM exams e
             JOIN subjects s ON s.id = e.subject_id
             JOIN exam_classes ec ON ec.exam_id = e.id
             JOIN classes c ON c.id = ec.class_id
             WHERE e.status <> 'draft'
             ORDER BY e.start_at DESC, e.id DESC, c.code"
        );
        $stmt->execute([Result::passScore()]);
        $terms = self::terms();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $sem = self::semesterOf(strtotime($r['start_at']), $terms);
            $r['semester_key'] = $sem['key'];
            $r['semester_label'] = $sem['label'];
        }
        return $rows;
    }

    /** Giữ các dòng khớp bộ lọc (0 / '' = không lọc) */
    public static function filter(array $rows, string $semester, int $subjectId, int $classId): array
    {
        return array_values(array_filter($rows, fn($r) =>
            ($semester === '' || $r['semester_key'] === $semester)
            && ($subjectId === 0 || (int) $r['subject_id'] === $subjectId)
            && ($classId === 0 || (int) $r['class_id'] === $classId)));
    }

    /**
     * Gom theo $by ('subject' | 'class' | 'semester'). Mỗi nhóm: label, sub (mô tả phụ), exams (số kỳ thi khác nhau),
     * expected (tổng lượt sinh viên được thi = sĩ số các lớp theo từng kỳ), submitted, participation (0-1), avg, pass_rate.
     * Kết quả sắp theo nhãn (học kỳ: mới nhất trước). Hàm thuần.
     */
    public static function group(array $rows, string $by): array
    {
        $g = [];
        foreach ($rows as $r) {
            switch ($by) {
                case 'class':
                    $k = 'c' . $r['class_id'];
                    $label = $r['class_code'];
                    $sub = '';
                    break;
                case 'semester':
                    $k = $r['semester_key'];
                    $label = $r['semester_label'];
                    $sub = '';
                    break;
                default:
                    $k = 's' . $r['subject_id'];
                    $label = $r['subject_code'];
                    $sub = $r['subject_name'];
            }
            $g[$k] ??= ['label' => $label, 'sub' => $sub, 'key' => $k, 'exam_ids' => [], 'expected' => 0, 'submitted' => 0, 'sum' => 0.0, 'passed' => 0];
            $g[$k]['exam_ids'][(int) $r['exam_id']] = true;
            $g[$k]['expected'] += (int) $r['students'];
            $g[$k]['submitted'] += (int) $r['n'];
            $g[$k]['sum'] += (float) $r['sum_score'];
            $g[$k]['passed'] += (int) $r['passed'];
        }
        $out = array_map(fn($x) => self::finish($x), array_values($g));
        usort($out, $by === 'semester' ? fn($a, $b) => strcmp($b['key'], $a['key']) : fn($a, $b) => strnatcasecmp($a['label'], $b['label']));
        return $out;
    }

    /** Tổng của toàn bộ các dòng (số kỳ thi tính khác nhau) */
    public static function total(array $rows): array
    {
        $t = ['label' => 'Tổng', 'sub' => '', 'key' => '', 'exam_ids' => [], 'expected' => 0, 'submitted' => 0, 'sum' => 0.0, 'passed' => 0];
        foreach ($rows as $r) {
            $t['exam_ids'][(int) $r['exam_id']] = true;
            $t['expected'] += (int) $r['students'];
            $t['submitted'] += (int) $r['n'];
            $t['sum'] += (float) $r['sum_score'];
            $t['passed'] += (int) $r['passed'];
        }
        return self::finish($t);
    }

    private static function finish(array $x): array
    {
        $x['exams'] = count($x['exam_ids']);
        unset($x['exam_ids']);
        $x['participation'] = $x['expected'] > 0 ? $x['submitted'] / $x['expected'] : null;
        $x['avg'] = $x['submitted'] > 0 ? round($x['sum'] / $x['submitted'], 2) : null;
        $x['pass_rate'] = $x['submitted'] > 0 ? $x['passed'] / $x['submitted'] : null;
        return $x;
    }

    /** Từng kỳ thi (cộng các lớp của kỳ thi đó): mới nhất trước */
    public static function perExam(array $rows): array
    {
        $e = [];
        foreach ($rows as $r) {
            $id = (int) $r['exam_id'];
            $e[$id] ??= ['id' => $id, 'title' => $r['title'], 'subject_code' => $r['subject_code'], 'semester_label' => $r['semester_label'],
                         'start_at' => $r['start_at'], 'status' => $r['status'], 'classes' => [], 'expected' => 0, 'submitted' => 0, 'sum' => 0.0, 'passed' => 0];
            $e[$id]['classes'][] = $r['class_code'];
            $e[$id]['expected'] += (int) $r['students'];
            $e[$id]['submitted'] += (int) $r['n'];
            $e[$id]['sum'] += (float) $r['sum_score'];
            $e[$id]['passed'] += (int) $r['passed'];
        }
        $out = [];
        foreach ($e as $x) {
            $x['avg'] = $x['submitted'] > 0 ? round($x['sum'] / $x['submitted'], 2) : null;
            $x['pass_rate'] = $x['submitted'] > 0 ? $x['passed'] / $x['submitted'] : null;
            $out[] = $x;
        }
        usort($out, fn($a, $b) => strcmp($b['start_at'], $a['start_at']));
        return $out;
    }
}
