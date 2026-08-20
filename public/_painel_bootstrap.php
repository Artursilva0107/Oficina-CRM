<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Config\Container;
use App\Config\Env;

session_name(Env::get('SESSION_NAME', 'oficina_sess'));
session_start();

$auth = Container::authService();
$usuarioLogado = $auth->exigirLogin();
