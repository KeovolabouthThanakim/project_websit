/* Đếm ngược cho mọi phần tử <span class="cd" data-to="UNIX_TIMESTAMP">.
 * Trang cần khai báo trước: window.NAU_NOW = <giờ máy chủ, giây> để bù độ lệch đồng hồ giữa máy chủ và máy sinh viên.
 * Về 0 thì tải lại trang (tối đa 1 lần mỗi 15 giây) để máy chủ cập nhật trạng thái (mở cổng thi / tự nộp bài). */
(function () {
    var nodes = document.querySelectorAll('.cd[data-to]');
    if (!nodes.length) return;
    var offset = (parseInt(window.NAU_NOW || '0', 10) || Math.floor(Date.now() / 1000)) * 1000 - Date.now();
    var reloaded = false;
    function p2(x) { return (x < 10 ? '0' : '') + x; }
    function fmt(s) {
        if (s >= 86400) return Math.floor(s / 86400) + ' ngày ' + Math.floor((s % 86400) / 3600) + ' giờ';
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), x = s % 60;
        return h > 0 ? h + ':' + p2(m) + ':' + p2(x) : m + ':' + p2(x);
    }
    function tick() {
        var now = Date.now() + offset, done = false;
        for (var i = 0; i < nodes.length; i++) {
            var left = Math.ceil(parseInt(nodes[i].getAttribute('data-to'), 10) - now / 1000);
            if (left <= 0) { nodes[i].textContent = '0:00'; done = true; continue; }
            nodes[i].textContent = fmt(left);
            nodes[i].classList.toggle('cd-low', left < 300);
        }
        if (done && !reloaded) {
            reloaded = true;
            try {
                var last = parseInt(sessionStorage.getItem('nauCdReload') || '0', 10);
                if (Date.now() - last < 15000) return;
                sessionStorage.setItem('nauCdReload', String(Date.now()));
            } catch (e) {}
            setTimeout(function () { location.reload(); }, 1500);
        }
    }
    tick();
    setInterval(tick, 1000);
})();
