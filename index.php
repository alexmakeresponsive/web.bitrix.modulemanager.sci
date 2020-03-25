<?php

// require_once $_SERVER['DOCUMENT_ROOT'] . '/modulemanager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/loader.php';


\spl_autoload_register([Loader::class, 'autoLoad']);
Loader::switchAutoLoad(true);


// registerNamespace($namespace, $module, $path)
Loader::registerNamespace('Amr\Main', 'main', '' );
// $obj = new Amr\Main\IBlock();


Loader::includeModule('catalog');

// var_dump(isset($var_A));

echo YANDEX_SKU_TEMPLATE_OFFERS;
