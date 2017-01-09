<?php

define('BATCH_MODE', 1);

require_once dirname(__FILE__) . '/../../app/_app.php';

$app = \Simplight\App::create();

if (defined('PROD')) {
    $config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.prod.json'), true);
} else if (defined('STG')) {
    $config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.stg.json'), true);
} else {
    $config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.dev.json'), true);
}

$username = $config_map['username'];
$password = $config_map['password'];

echo "\nデータベース作成開始 ==========================\n\n";

foreach ($config_map['database_settings'] as $db_name => $setting_map) {
    $mysqli = new mysqli($setting_map['master']['host'], $username, $password, '', $setting_map['master']['port']);
    $mysqli->query("create database if not exists $db_name");
    $mysqli->close();

    echo "    -> データベース「{$db_name}」の作成に成功しました\n";
}
echo "\nデータベースの作成が完了しました ==========================\n\n";

exit;

