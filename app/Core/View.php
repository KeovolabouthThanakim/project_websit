<?php
namespace App\Core;

class View
{
    /** Hiển thị app/Views/<view>.php bên trong layout (mặc định layouts/main) */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $content = self::partial($view, $data);
        echo $layout ? self::partial($layout, $data + ['content' => $content]) : $content;
    }

    private static function partial(string $view, array $data): string
    {
        $file = ROOT . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Không tìm thấy view: $view");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
