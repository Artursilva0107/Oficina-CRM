<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
session_name(\App\Config\Env::get('SESSION_NAME', 'oficina_crm_sess'));
session_start();

use App\Config\Container;

Container::authService()->encerrarSessao();
header('Location: /login.php');
exit;
