<?php
namespace App\Core;

abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function view(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    protected function user(): ?array
    {
        return Auth::user();
    }
}
