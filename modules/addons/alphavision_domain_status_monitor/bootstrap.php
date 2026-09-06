<?php
declare(strict_types=1);

defined('WHMCS') || die('Acesso direto não permitido.');

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('O autoload do Domain Status Monitor não foi encontrado.');
}
require_once $autoload;

