<?php
use App\Models\Exam;
use App\Models\Question;

$examId = (int) $exam['id'];
$isDraft = $exam['status'] === 'draft';
$totalStudents = array_sum(array_map(fn($c) => (int) $c['student_count'], $classes));
$statusBtn = function (string $action, string $label, string $class, ?string $confirm = null) use ($examId): string {
    return '<form method="post" action="' . url('/exams/' . $examId . '/status') . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . ' style="display:inline">'
         . csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">'
         . '<button class="btn ' . $class . '" type="submit">' . e($label) . '</button></form>';
};
?>
<div class="page-head">
    <div>
        <h1><?= e($exam['title']) ?> <span class="badge badge-st-<?= e($state) ?>"><?= e(Exam::STATES[$state]) ?></span></h1>
        <p>Môn <?= e($exam['subject_code']) ?> - <?= e($exam['subject_name']) ?></p>
    </div>
    <div class="header-actions">
        <?php if ($isDraft): ?>
            <?= $statusBtn('publish', 'Công bố đề thi', '', 'Công bố đề thi này? Sinh viên của các lớp được chọn sẽ thấy đề thi. Sau khi công bố sẽ không sửa được câu hỏi.') ?>
        <?php elseif ($exam['status'] === 'published'): ?>
            <?= $statusBtn('close', 'Đóng đề thi', 'btn-outline', 'Đóng đề thi? Sinh viên sẽ không vào thi được nữa. Bạn có thể mở lại sau.') ?>
            <?= $statusBtn('unpublish', 'Chuyển về bản nháp', 'btn-outline', 'Chuyển về bản nháp? Sinh viên sẽ không còn thấy đề thi này.') ?>
        <?php else: ?>
            <?= $statusBtn('reopen', 'Mở lại đề thi', '') ?>
            <?= $statusBtn('unpublish', 'Chuyển về bản nháp', 'btn-outline', 'Chuyển về bản nháp?') ?>
        <?php endif; ?>
        <?php if (!$isDraft): ?><a class="btn" href="<?= url('/exams/' . $examId . '/monitor') ?>">Theo dõi trực tiếp</a> <a class="btn btn-outline" href="<?= url('/exams/' . $examId . '/results') ?>">Kết quả &amp; thống kê</a><?php endif; ?>
        <form method="post" action="<?= url('/exams/' . $examId . '/copy') ?>" style="display:inline" data-confirm="Sao chép đề thi này thành một bản nháp mới (giữ câu hỏi và lớp, không chép kết quả)?">
            <?= csrf_field() ?><button class="btn btn-outline" type="submit">Sao chép đề thi</button>
        </form>
        <a class="btn btn-outline" href="<?= url('/exams/' . $examId . '/edit') ?>">Sửa thông tin</a>
        <a class="btn btn-outline" href="<?= url('/exams') ?>">&laquo; Danh sách</a>
    </div>
</div>

<div class="two-col">
    <div>
        <section class="panel">
            <h2 class="panel-title">Câu hỏi của đề (<?= count($questions) ?>)</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>STT</th><th>Nội dung câu hỏi</th><th>Độ khó</th><th>Loại</th><?php if ($isDraft): ?><th></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($questions as $i => $q): ?>
                        <?php $text = trim(preg_replace('/\s+/u', ' ', $q['content'])); ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="q-cell">
                                <a href="<?= url('/questions/' . (int) $q['id']) ?>"><?= e(str_cut($text, 110)) ?><?= str_len($text) > 110 ? '…' : '' ?></a>
                                <?php if (!$q['is_active']): ?><span class="badge badge-off">Đã ẩn</span><?php endif; ?>
                            </td>
                            <td style="white-space:nowrap"><span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(Question::DIFFICULTIES[$q['difficulty']] ?? $q['difficulty']) ?></span></td>
                            <td style="white-space:nowrap"><?= (int) $q['correct_count'] > 1 ? 'Nhiều đáp án' : '1 đáp án' ?></td>
                            <?php if ($isDraft): ?>
                            <td class="actions" style="white-space:nowrap;min-width:0">
                                <form method="post" action="<?= url('/exams/' . $examId . '/questions/' . (int) $q['id'] . '/remove') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-danger" type="submit">Bỏ</button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$questions): ?>
                        <tr><td colspan="<?= $isDraft ? 5 : 4 ?>" class="empty">Đề thi chưa có câu hỏi nào<?= $isDraft ? '. Hãy thêm câu hỏi ở phần bên dưới.' : '.' ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($isDraft && $questions): ?>
            <div class="panel-body" style="border-top:1px solid var(--line)">
                <form method="post" action="<?= url('/exams/' . $examId . '/questions/clear') ?>" data-confirm="Bỏ tất cả câu hỏi khỏi đề thi này?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-danger" type="submit">Bỏ tất cả câu hỏi</button>
                </form>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($isDraft): ?>
        <section class="panel" id="them-cau-hoi">
            <h2 class="panel-title">Bốc ngẫu nhiên từ ngân hàng câu hỏi</h2>
            <div class="panel-body">
                <form method="post" action="<?= url('/exams/' . $examId . '/questions/random') ?>">
                    <?= csrf_field() ?>
                    <div class="form-grid" style="grid-template-columns:repeat(3,1fr)">
                        <?php foreach (Question::DIFFICULTIES as $k => $label): ?>
                        <div class="form-row">
                            <label for="n_<?= e($k) ?>"><?= e(['easy' => 'Số câu dễ', 'medium' => 'Số câu trung bình', 'hard' => 'Số câu khó'][$k] ?? $label) ?></label>
                            <input type="number" id="n_<?= e($k) ?>" name="n_<?= e($k) ?>" value="0" min="0" max="200">
                            <span class="hint">Còn <?= (int) $counts[$k] ?> câu</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="btn" type="submit">Bốc ngẫu nhiên và thêm vào đề</button>
                    <span class="hint" style="margin-top:8px">Chỉ lấy câu hỏi đang hiện của môn này và chưa có trong đề.</span>
                </form>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Chọn câu hỏi thủ công (<?= count($available) ?>)</h2>
            <form method="get" action="<?= url('/exams/' . $examId) ?>#chon-thu-cong" id="chon-thu-cong" class="panel-body" style="padding-bottom:0">
                <label for="level">Lọc theo độ khó</label>
                <select id="level" name="level" onchange="this.form.submit()" style="max-width:240px">
                    <option value="">Tất cả</option>
                    <?php foreach (Question::DIFFICULTIES as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $level === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <noscript><button class="btn btn-outline btn-sm" type="submit" style="margin-top:8px">Lọc</button></noscript>
            </form>
            <?php if ($available): ?>
            <form method="post" action="<?= url('/exams/' . $examId . '/questions') ?>">
                <?= csrf_field() ?>
                <div class="panel-body">
                    <div class="checklist" style="max-height:340px">
                        <?php foreach ($available as $q): ?>
                            <?php $text = trim(preg_replace('/\s+/u', ' ', $q['content'])); ?>
                            <label class="check-row">
                                <input type="checkbox" name="question_ids[]" value="<?= (int) $q['id'] ?>">
                                <span>
                                    <span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(Question::DIFFICULTIES[$q['difficulty']] ?? '') ?></span>
                                    <?= e(str_cut($text, 120)) ?><?= str_len($text) > 120 ? '…' : '' ?>
                                    <span class="muted">(<?= (int) $q['correct_count'] > 1 ? 'nhiều đáp án' : 'một đáp án' ?>)</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top:14px"><button class="btn" type="submit">Thêm câu hỏi đã chọn vào đề</button></div>
                </div>
            </form>
            <?php else: ?>
                <div class="panel-body"><p class="muted" style="margin:0">Không còn câu hỏi nào để thêm<?= $level !== '' ? ' ở mức độ này' : '' ?>. <a href="<?= url('/questions/create?subject_id=' . (int) $exam['subject_id']) ?>">Soạn thêm câu hỏi cho môn này</a>.</p></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>

    <div>
        <?php if ($isDraft): ?>
        <section class="panel">
            <h2 class="panel-title">Điều kiện công bố</h2>
            <div class="panel-body">
                <?php if ($problems): ?>
                    <ul class="plain">
                        <?php foreach ($problems as $p): ?><li><span><?= e($p) ?></span></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="margin:0"><span class="badge badge-on">Sẵn sàng</span> Đề thi đủ điều kiện để công bố.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="panel">
            <h2 class="panel-title">Thông tin đề thi</h2>
            <div class="panel-body">
                <dl class="info">
                    <dt>Mở cổng thi</dt><dd><?= e(fmt_date($exam['start_at'])) ?></dd>
                    <dt>Đóng cổng thi</dt><dd><?= e(fmt_date($exam['end_at'])) ?></dd>
                    <dt>Thời gian làm</dt><dd><?= (int) $exam['duration_minutes'] ?> phút</dd>
                    <dt>Số câu hỏi</dt><dd><?= count($questions) ?></dd>
                    <dt>Đảo câu hỏi</dt><dd><?= $exam['shuffle'] ? 'Có' : 'Không' ?></dd>
                    <dt>Bài làm</dt><dd><?= (int) $attempts ?> / <?= $totalStudents ?> sinh viên đã vào thi<?= $attempts > 0 ? '<div class="muted" style="font-weight:400;font-size:.8rem">Đã có bài làm nên không chuyển về bản nháp được.</div>' : '' ?></dd>
                    <dt>Người tạo</dt><dd><?= $exam['author_name'] ? e($exam['author_name']) : '-' ?></dd>
                    <dt>Ngày tạo</dt><dd><?= e(fmt_date($exam['created_at'])) ?></dd>
                    <dt>Mô tả</dt><dd style="font-weight:400"><?= $exam['description'] ? nl2br(e($exam['description'])) : '-' ?></dd>
                </dl>
            </div>
        </section>

        <section class="panel">
            <h2 class="panel-title">Lớp được thi (<?= count($classes) ?>)</h2>
            <div class="panel-body">
                <?php if ($classes): ?>
                    <ul class="plain">
                        <?php foreach ($classes as $c): ?>
                            <li><span><strong><?= e($c['code']) ?></strong><br><span class="muted"><?= e($c['name']) ?></span></span><span class="muted"><?= (int) $c['student_count'] ?> SV</span></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="muted" style="margin:10px 0 0">Tổng cộng <strong><?= (int) $totalStudents ?></strong> sinh viên.</p>
                <?php else: ?>
                    <p class="muted" style="margin:0">Chưa chọn lớp nào.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($isDraft): ?>
        <form method="post" action="<?= url('/exams/' . $examId . '/delete') ?>" data-confirm="Bạn có chắc muốn xóa đề thi &quot;<?= e($exam['title']) ?>&quot;? Thao tác này không thể hoàn tác.">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit">Xóa đề thi</button>
        </form>
        <?php endif; ?>
    </div>
</div>
