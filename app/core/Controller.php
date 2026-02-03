<?php

abstract class Controller
{
    protected function view(string $view, array $data = []): void
    {
        extract($data);

        require __DIR__ . '/../views/layout/header.php';
        require __DIR__ . '/../views/' . $view . '.php';
        require __DIR__ . '/../views/layout/footer.php';
    }

    protected function redirect(string $path = ''): void
    {
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $base = $base === '' ? '' : $base;
        $url  = $base;

        if ($path !== '') {
            $url .= '/' . ltrim($path, '/');
        }

        header("Location: $url");
        exit;
    }
}
