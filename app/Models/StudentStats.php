<?php
namespace App\Models;

/**
 * Số liệu cho trang chủ sinh viên: thống kê nhanh, kết quả gần đây, điểm theo môn, biểu đồ tiến bộ,
 * so sánh với điểm trung bình lớp. Chỉ tính toán từ dữ liệu đã có (không truy vấn CSDL) nên dễ kiểm thử.
 */
class StudentStats
{
    /** Chỉ hiện điểm trung bình lớp khi có ít nhất ngần này bài đã nộp (tránh suy ra điểm của một bạn cụ thể) */
    public const MIN_PEERS = 3;
    public const RECENT = 5;
    public const ATTENTION = 5;
    public const CHART_POINTS = 10;

    /**
     * @param array $all       kết quả Attempt::listForStudent()
     * @param array $classAvg  kết quả Result::classAverages() - [exam_id => ['avg', 'n']]
     * @param array $subjects  môn học của lớp (ClassSubject::forClass)
     */
    public static function build(array $all, array $classAvg, array $subjects): array
    {
        $submitted = [];
        $attention = [];
        $upcomingCount = 0;
        $bySubject = [];

        foreach ($all as $x) {
            $state = Exam::state($x);
            $code = $x['subject_code'];
            $bySubject[$code] = $bySubject[$code] ?? ['total' => 0, 'done' => 0, 'sum' => 0.0, 'avg' => null];
            $bySubject[$code]['total']++;

            if ($x['attempt_status'] === 'submitted' && $x['score'] !== null) {
                $x['score'] = (float) $x['score'];
                // Điểm TB lớp chỉ hiện sau khi kỳ thi kết thúc (giống quy tắc hiện đáp án) và đủ số bài
                $x['class_avg'] = self::peerAvg($x, $state, $classAvg);
                $submitted[] = $x;
                $bySubject[$code]['done']++;
                $bySubject[$code]['sum'] += $x['score'];
            } elseif ($x['attempt_status'] === 'in_progress') {
                $x['prio'] = 0;
                $x['state'] = $state;
                $attention[] = $x;
            } elseif (!$x['attempt_id'] && $state === 'open') {
                $x['prio'] = 1;
                $x['state'] = $state;
                $attention[] = $x;
            } elseif (!$x['attempt_id'] && $state === 'upcoming') {
                $x['prio'] = 2;
                $x['state'] = $state;
                $attention[] = $x;
                $upcomingCount++;
            }
        }

        foreach ($bySubject as $code => $b) {
            $bySubject[$code]['avg'] = $b['done'] > 0 ? $b['sum'] / $b['done'] : null;
        }

        // Cũ -> mới theo giờ nộp
        usort($submitted, function ($a, $b) {
            return [strtotime((string) $a['submitted_at']), (int) $a['attempt_id']] <=> [strtotime((string) $b['submitted_at']), (int) $b['attempt_id']];
        });

        $count = count($submitted);
        $avg = $count ? array_sum(array_column($submitted, 'score')) / $count : null;
        $passed = count(array_filter($submitted, function ($x) { return $x['score'] >= Result::passScore(); }));

        // So với lớp: chỉ trên các kỳ thi đã có điểm TB lớp hiển thị được
        $withAvg = array_values(array_filter($submitted, function ($x) { return $x['class_avg'] !== null; }));
        $compare = null;
        if ($withAvg) {
            $mine = array_sum(array_column($withAvg, 'score')) / count($withAvg);
            $cls = array_sum(array_column($withAvg, 'class_avg')) / count($withAvg);
            $compare = ['mine' => $mine, 'class' => $cls, 'diff' => $mine - $cls, 'exams' => count($withAvg)];
        }

        // Ưu tiên: đang làm dở (hạn gần nhất) -> đang mở (sắp đóng cổng) -> sắp diễn ra (sắp mở)
        usort($attention, function ($a, $b) {
            $key = function ($x) {
                $t = $x['prio'] === 0 ? $x['deadline_at'] : ($x['prio'] === 1 ? $x['end_at'] : $x['start_at']);
                return [$x['prio'], strtotime((string) $t)];
            };
            return $key($a) <=> $key($b);
        });
        $todo = count(array_filter($attention, function ($x) { return $x['prio'] < 2; }));
        $hero = ($attention && $attention[0]['prio'] < 2) ? $attention[0] : null;

        $chart = [];
        foreach (array_slice($submitted, -self::CHART_POINTS) as $x) {
            $chart[] = [
                'label' => date('d/m', strtotime((string) $x['submitted_at'])),
                'title' => $x['title'],
                'code' => $x['subject_code'],
                'score' => $x['score'],
                'avg' => $x['class_avg'],
            ];
        }

        return [
            'stats' => ['avg' => $avg, 'submitted' => $count, 'passed' => $passed, 'todo' => $todo, 'upcoming' => $upcomingCount],
            'compare' => $compare,
            'hero' => $hero,
            'attention' => array_slice($attention, 0, self::ATTENTION),
            'recent' => array_slice(array_reverse($submitted), 0, self::RECENT),
            'chart' => $chart,
            'bySubject' => $bySubject,
            'serverNow' => time(),
        ];
    }

    /** Điểm TB lớp của một kỳ thi: chỉ hiện sau khi kỳ thi kết thúc và đủ MIN_PEERS bài nộp (không lộ điểm từng bạn) */
    private static function peerAvg(array $x, string $state, array $classAvg): ?float
    {
        $ca = $classAvg[(int) $x['id']] ?? null;
        return ($ca && $ca['n'] >= self::MIN_PEERS && in_array($state, ['ended', 'closed'], true)) ? $ca['avg'] : null;
    }

    // ------------------------------------------------------------------
    // Lịch sử kết quả (trang /my-results)
    // ------------------------------------------------------------------

    /** Số điểm tối đa trên biểu đồ lịch sử (nhiều hơn thì nhãn trục bị chồng lên nhau) */
    public const HISTORY_CHART = 20;

    /**
     * Toàn bộ bài đã nộp, nhóm theo môn.
     * @param array  $all       Attempt::listForStudent()
     * @param array  $classAvg  Result::classAverages()
     * @param string $subject   mã môn cần lọc ('' = tất cả; mã không có bài nộp nào thì coi như '')
     * @return array ['subjects' => [mã => tên] (để dựng ô lọc), 'subject' => mã đang lọc,
     *   'groups' => [mã => ['code','name','items' (cũ -> mới, mỗi bài có delta so với bài trước cùng môn),
     *                        'count','avg','best','last','passed','delta','chart']],
     *   'stats' => ['count','avg','best','passed'], 'chart' => [...]]
     */
    public static function history(array $all, array $classAvg, string $subject = ''): array
    {
        $submitted = [];
        foreach ($all as $x) {
            if ($x['attempt_status'] !== 'submitted' || $x['score'] === null) {
                continue;
            }
            $x['score'] = (float) $x['score'];
            $x['class_avg'] = self::peerAvg($x, Exam::state($x), $classAvg);
            $x['spent'] = ($x['started_at'] && $x['submitted_at']) ? max(0, strtotime($x['submitted_at']) - strtotime($x['started_at'])) : null;
            $submitted[] = $x;
        }
        usort($submitted, function ($a, $b) {
            return [strtotime((string) $a['submitted_at']), (int) $a['attempt_id']] <=> [strtotime((string) $b['submitted_at']), (int) $b['attempt_id']];
        });

        $subjects = [];
        foreach ($submitted as $x) {
            $subjects[$x['subject_code']] = $x['subject_name'];
        }
        ksort($subjects);
        if ($subject !== '' && !isset($subjects[$subject])) {
            $subject = '';
        }
        $shown = $subject === '' ? $submitted : array_values(array_filter($submitted, function ($x) use ($subject) {
            return $x['subject_code'] === $subject;
        }));

        $groups = [];
        foreach ($shown as $x) {
            $code = $x['subject_code'];
            if (!isset($groups[$code])) {
                $groups[$code] = ['code' => $code, 'name' => $x['subject_name'], 'items' => []];
            }
            $n = count($groups[$code]['items']);
            $x['delta'] = $n ? $x['score'] - $groups[$code]['items'][$n - 1]['score'] : null;
            $groups[$code]['items'][] = $x;
        }
        ksort($groups);
        foreach ($groups as $code => $g) {
            $scores = array_column($g['items'], 'score');
            $last = end($g['items']);
            $groups[$code] += [
                'count'  => count($scores),
                'avg'    => array_sum($scores) / count($scores),
                'best'   => max($scores),
                'last'   => $last['score'],
                'delta'  => $last['delta'],
                'passed' => count(array_filter($scores, function ($v) { return $v >= Result::passScore(); })),
                'chart'  => self::points(array_slice($g['items'], -self::HISTORY_CHART)),
            ];
        }

        $scores = array_column($shown, 'score');
        return [
            'subjects' => $subjects,
            'subject'  => $subject,
            'groups'   => $groups,
            'stats'    => [
                'count'  => count($scores),
                'avg'    => $scores ? array_sum($scores) / count($scores) : null,
                'best'   => $scores ? max($scores) : null,
                'passed' => count(array_filter($scores, function ($v) { return $v >= Result::passScore(); })),
            ],
            'chart'    => self::points(array_slice($shown, -self::HISTORY_CHART)),
        ];
    }

    /** Điểm dữ liệu cho chartSvg() */
    private static function points(array $items): array
    {
        $out = [];
        foreach ($items as $x) {
            $out[] = [
                'label' => date('d/m', strtotime((string) $x['submitted_at'])),
                'title' => $x['title'],
                'code'  => $x['subject_code'],
                'score' => $x['score'],
                'avg'   => $x['class_avg'],
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Lịch thi (trang /my-schedule)
    // ------------------------------------------------------------------

    /** Chuẩn hóa tham số tháng "YYYY-MM": sai định dạng hoặc lệch quá 5 năm so với hiện tại thì về tháng hiện tại */
    public static function monthParam(?string $raw, ?int $now = null): string
    {
        $now = $now ?? time();
        $cur = date('Y-m', $now);
        if ($raw === null || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $raw, $m)) {
            return $cur;
        }
        return abs((int) $m[1] - (int) date('Y', $now)) > 5 ? $cur : $raw;
    }

    /**
     * Lịch thi của sinh viên.
     * kind của từng kỳ thi: running (đang làm dở) | open (đang mở, chưa làm) | upcoming (sắp diễn ra) |
     *                       done (đã nộp) | missed (đã hết hạn mà không làm bài).
     * @return array ['agenda' => việc cần làm (đang làm dở -> đang mở theo hạn gần nhất -> sắp diễn ra theo giờ mở),
     *   'counts' => [running, open, upcoming, missed], 'month' => 'YYYY-MM', 'prev', 'next', 'label', 'isCurrent',
     *   'weeks' => [[ 7 ô: null hoặc ['date','day','today','items' => [['x' => kỳ thi, 'cont' => bool]]] ]],
     *   'monthItems' => số kỳ thi rơi vào tháng đang xem, 'serverNow']
     */
    public static function schedule(array $all, string $month, ?int $now = null): array
    {
        $now = $now ?? time();
        $items = [];
        $counts = ['running' => 0, 'open' => 0, 'upcoming' => 0, 'missed' => 0];
        foreach ($all as $x) {
            $state = Exam::state($x);
            if ($x['attempt_status'] === 'submitted') {
                $kind = 'done';
            } elseif ($x['attempt_status'] === 'in_progress') {
                $kind = 'running';
            } elseif ($state === 'open') {
                $kind = 'open';
            } elseif ($state === 'upcoming') {
                $kind = 'upcoming';
            } else {
                $kind = 'missed';
            }
            if (isset($counts[$kind])) {
                $counts[$kind]++;
            }
            $x['state'] = $state;
            $x['kind'] = $kind;
            $x['start_ts'] = strtotime((string) $x['start_at']);
            $x['end_ts'] = strtotime((string) $x['end_at']);
            $items[] = $x;
        }
        usort($items, function ($a, $b) { return [$a['start_ts'], (int) $a['id']] <=> [$b['start_ts'], (int) $b['id']]; });

        $rank = ['running' => 0, 'open' => 1, 'upcoming' => 2];
        $agenda = array_values(array_filter($items, function ($x) use ($rank) { return isset($rank[$x['kind']]); }));
        usort($agenda, function ($a, $b) use ($rank) {
            $t = function ($x) { return $x['kind'] === 'running' ? strtotime((string) $x['deadline_at']) : ($x['kind'] === 'open' ? $x['end_ts'] : $x['start_ts']); };
            return [$rank[$a['kind']], $t($a)] <=> [$rank[$b['kind']], $t($b)];
        });

        // Lịch tháng: kỳ thi hiện ở ngày mở cổng (đậm) và các ngày sau đó đến ngày đóng cổng (nhạt, tối đa 31 ngày)
        $first = new \DateTime($month . '-01');
        $daysInMonth = (int) $first->format('t');
        $monthStart = $first->format('Y-m-d');
        $monthEnd = $first->format('Y-m-') . sprintf('%02d', $daysInMonth);
        $byDay = [];
        $monthItems = 0;
        foreach ($items as $x) {
            $from = date('Y-m-d', $x['start_ts']);
            $to = date('Y-m-d', max($x['end_ts'], $x['start_ts']));
            if ($to < $monthStart || $from > $monthEnd) {
                continue;
            }
            $monthItems++;
            $d = new \DateTime($from);
            for ($i = 0; $i < 31 && $d->format('Y-m-d') <= $to; $i++, $d->modify('+1 day')) {
                $key = $d->format('Y-m-d');
                if ($key >= $monthStart && $key <= $monthEnd) {
                    $byDay[$key][] = ['x' => $x, 'cont' => $i > 0];
                }
            }
        }

        $weeks = [];
        $row = array_fill(0, (int) $first->format('N') - 1, null);   // thứ Hai là cột đầu
        $today = date('Y-m-d', $now);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $first->format('Y-m'), $day);
            $row[] = ['date' => $date, 'day' => $day, 'today' => $date === $today, 'items' => $byDay[$date] ?? []];
            if (count($row) === 7) {
                $weeks[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $weeks[] = array_pad($row, 7, null);
        }

        $prev = (clone $first)->modify('-1 month')->format('Y-m');
        $next = (clone $first)->modify('+1 month')->format('Y-m');
        return [
            'agenda'     => $agenda,
            'counts'     => $counts,
            'month'      => $month,
            'prev'       => $prev,
            'next'       => $next,
            'label'      => 'Tháng ' . (int) $first->format('n') . '/' . $first->format('Y'),
            'isCurrent'  => $month === date('Y-m', $now),
            'weeks'      => $weeks,
            'monthItems' => $monthItems,
            'serverNow'  => $now,
        ];
    }

    /** "+0,7" / "-1,2" / "0" */
    public static function signed(float $v): string
    {
        if (abs($v) < 0.005) {
            return '0';
        }
        return ($v > 0 ? '+' : '-') . fmt_score(round(abs($v), 2));
    }

    /** Biểu đồ đường (SVG thuần, không cần thư viện): điểm của sinh viên + điểm TB lớp (nếu có), vạch điểm đạt nét đứt */
    public static function chartSvg(array $pts): string
    {
        $n = count($pts);
        if ($n === 0) {
            return '';
        }
        $W = 640; $H = 240; $l = 34; $r = 16; $t = 18; $b = 34;
        $pw = $W - $l - $r;
        $ph = $H - $t - $b;
        $y = function ($v) use ($t, $ph) { return round($t + $ph * (1 - max(0, min(10, $v)) / 10), 1); };
        $x = function ($i) use ($n, $l, $pw) { return round($n === 1 ? $l + $pw / 2 : $l + $pw * $i / ($n - 1), 1); };

        $o = '<svg class="dc-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Biểu đồ điểm qua các kỳ thi">';
        foreach ([0, Result::passScore(), 10] as $g) {
            $cls = $g == Result::passScore() ? 'dc-pass' : 'dc-grid';
            $o .= '<line class="' . $cls . '" x1="' . $l . '" x2="' . ($W - $r) . '" y1="' . $y($g) . '" y2="' . $y($g) . '"/>';
            $o .= '<text class="dc-axis" x="' . ($l - 8) . '" y="' . ($y($g) + 4) . '" text-anchor="end">' . fmt_score($g) . '</text>';
        }

        $labelEvery = max(1, (int) ceil($n / 10));   // nhiều điểm thì bớt nhãn trục để không chồng chữ
        $mine = $avg = [];
        foreach ($pts as $i => $p) {
            $mine[] = $x($i) . ',' . $y($p['score']);
            if ($p['avg'] !== null) {
                $avg[] = $x($i) . ',' . $y($p['avg']);
            }
        }
        if (count($avg) > 1) {
            $o .= '<polyline class="dc-line-avg" points="' . implode(' ', $avg) . '"/>';
        }
        if ($n > 1) {
            $o .= '<polyline class="dc-line" points="' . implode(' ', $mine) . '"/>';
        }
        foreach ($pts as $i => $p) {
            $tip = $p['title'] . ' (' . $p['code'] . '): ' . fmt_score($p['score']) . ' điểm'
                . ($p['avg'] !== null ? ' - TB lớp ' . fmt_score(round($p['avg'], 2)) : '');
            if ($p['avg'] !== null) {
                $o .= '<circle class="dc-dot-avg" cx="' . $x($i) . '" cy="' . $y($p['avg']) . '" r="3.5"><title>' . e($tip) . '</title></circle>';
            }
            $o .= '<circle class="dc-dot" cx="' . $x($i) . '" cy="' . $y($p['score']) . '" r="5"><title>' . e($tip) . '</title></circle>';
            if ($n <= 12) {
                $o .= '<text class="dc-val" x="' . $x($i) . '" y="' . ($y($p['score']) - 11) . '">' . fmt_score($p['score']) . '</text>';
            }
            if ($i % $labelEvery === 0) {
                $o .= '<text class="dc-axis" x="' . $x($i) . '" y="' . ($H - 12) . '" text-anchor="middle">' . e($p['label']) . '</text>';
            }
        }
        return $o . '</svg>';
    }
}
