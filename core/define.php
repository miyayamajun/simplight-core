<?php

/**
 * ディレクトリ
 */
define('ROOT_DIR',       preg_replace('/\/app$/', '', APP_DIR));
define('CORE_DIR',       APP_DIR .  '/core');
define('LIB_DIR',        APP_DIR .  '/lib');
define('CACHE_DIR',      APP_DIR .  '/cache');
define('CONTROLLER_DIR', ROOT_DIR . '/src/controller');
define('MODEL_DIR',      ROOT_DIR . '/src/model');
define('ACCESSOR_DIR',   ROOT_DIR . '/src/accessor');
define('HOOK_DIR',       ROOT_DIR . '/src/hook');
define('TEMPLATE_DIR',   ROOT_DIR . '/src/view');
define('JSON_DIR',       ROOT_DIR . '/settings/json');
define('TABLE_JSON_DIR', JSON_DIR . '/table');

/**
 * ステータスコード
 */
define('STATUS_CODE_OK',        200);
define('STATUS_CODE_NOT_FOUND', 404);
define('STATUS_CODE_ERROR',     500);

define('DEV', 1);
// define('STG', 1);
// define('PROD', 1);

/**
 * memcached
 */
if (class_exists('Memcached')) {
    define('MEMCACHED_ENABLE', 1);
}

/**
 * URL
 */
if (isset($_SERVER['HTTP_HOST'])) {
    define('HTTP_ROOT_URL', "http://{$_SERVER['HTTP_HOST']}");
    define('HTTPS_ROOT_URL', "https://{$_SERVER['HTTP_HOST']}");
}

/**
 * その他
 */
define('DB_SAVE_RETRY_COUNT', 10); // DB保存のリトライ回数

