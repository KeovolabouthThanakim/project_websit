<?php $letters = ['A', 'B', 'C', 'D', 'E', 'F']; $correct = count(array_filter($options, fn($o) => $o['is_correct'])); ?>
<div class="page-head">
    <div>
        <h1>Câu hỏi #<?= (int) $q['id'] ?></h1>
        <p>Môn <?= e($q['subject_code'] . ' - ' . $q['subject_name']) ?></p>
    </div>
    <div class="header-actions">
        <a class="btn btn-outline" href="<?= url('/questions/' . (int) $q['id'] . '/edit') ?>">Sửa câu hỏi</a>
        <a class="btn btn-outline" href="<?= url('/questions?subject_id=' . (int) $q['subject_id']) ?>">&laquo; Ngân hàng câu hỏi</a>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <h2 class="panel-title">Nội dung</h2>
        <div class="panel-body">
            <p class="q-content"><?= e($q['content']) ?></p>
            <?php if ($q['image']): ?>
                <img class="q-image" src="<?= url('/question-images/' . e($q['image'])) ?>" alt="Ảnh minh họa câu hỏi">
            <?php endif; ?>

            <ol class="opt-list">
                <?php foreach ($options as $i => $o): ?>
                    <li class="<?= $o['is_correct'] ? 'correct' : '' ?>">
                        <span class="opt-letter"><?= $letters[$i] ?? $i + 1 ?></span>
                        <span class="opt-text"><?= e($o['content']) ?></span>
                        <?php if ($o['is_correct']): ?><span class="badge badge-on">Đáp án đúng</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($q['explanation']): ?>
                <h3 class="sub-title">Giải thích</h3>
                <p class="q-content"><?= e($q['explanation']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <h2 class="panel-title">Thông tin</h2>
        <div class="panel-body">
            <dl class="info">
                <dt>Môn học</dt><dd><a href="<?= url('/subjects/' . (int) $q['subject_id']) ?>"><?= e($q['subject_code']) ?></a></dd>
                <dt>Độ khó</dt><dd><span class="badge badge-lv-<?= e($q['difficulty']) ?>"><?= e(\App\Models\Question::DIFFICULTIES[$q['difficulty']] ?? '') ?></span></dd>
                <dt>Loại câu hỏi</dt><dd><?= $correct > 1 ? 'Nhiều đáp án đúng' : 'Một đáp án đúng' ?></dd>
                <dt>Trạng thái</dt><dd><span class="badge <?= $q['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $q['is_active'] ? 'Đang hiện' : 'Đã ẩn' ?></span></dd>
                <dt>Người soạn</dt><dd><?= $q['author_name'] ? e($q['author_name']) : '-' ?></dd>
                <dt>Ngày tạo</dt><dd><?= e(fmt_date($q['created_at'])) ?></dd>
                <dt>Cập nhật</dt><dd><?= e(fmt_date($q['updated_at'])) ?></dd>
            </dl>
        </div>
    </section>
</div>
