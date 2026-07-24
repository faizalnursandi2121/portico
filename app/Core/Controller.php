<?php

namespace App\Core;

use App\Helpers\CsrfHelper;

class Controller
{
    public function view($view, $data = [])
    {
        extract($data);
        $viewPath = ROOT.'/app/Views/'.$view.'.php';

        if (file_exists($viewPath)) {
            ob_start();
            require_once $viewPath;
            echo CsrfHelper::injectFields(ob_get_clean());
        } else {
            echo "View not found: $view";
        }
    }

    public function redirect($url)
    {
        header('Location: '.$url);
        exit();
    }
}
