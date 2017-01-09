<?php

define('BATCH_MODE', 1);

require_once dirname(__FILE__) . '/../../app/_app.php';
$app = \Simplight\App::create();

if (defined('PROD')) {
    $db_config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.prod.json'), true);
} else if (defined('STG')) {
    $db_config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.stg.json'), true);
} else {
    $db_config_map = json_decode(file_get_contents(JSON_DIR . '/mysql.dev.json'), true);
}

$username = $db_config_map['username'];
$password = $db_config_map['password'];

echo "\nテーブル作成開始 ==========================\n\n";

$iterator = new \RecursiveDirectoryIterator(TABLE_JSON_DIR);
$iterator = new \RecursiveIteratorIterator($iterator);

$column_length_template = array(
    'tinyint'   => 3,
    'smallint'  => 5,
    'mediumint' => 8,
    'int'       => 11,
    'bigint'    => 20,
    'float'     => '7,4',
    'double'    => '14,6',
    'varchar'   => 255,
);
$dbh_map = array();

foreach ($iterator as $fileinfo) {
    if ($fileinfo->isFile()) {
        $class_name = '';
        $file_path  = $fileinfo->getPathname();
        if (!preg_match('/\.json$/', $file_path)) {
            continue;
        }

        $config_map = json_decode(file_get_contents($file_path), true);
        
        $create_table_inner_str = "";
        foreach ($config_map['columns'] as $name => $map) {
            $create_table_inner_str .= "`{$name}`";
            $create_table_inner_str .= " {$map['cast']}";
            if (isset($column_length_template[$map['cast']])) {
                $create_table_inner_str .= isset($map['length']) ? "({$map['length']})" : "({$column_length_template[$map['cast']]})";
            }
            if (isset($map['unsigned']) && $map['unsigned'] === true) {
                $create_table_inner_str .= ' unsigned';
            }
            if (!isset($map['null']) || !$map['null']) {
                $create_table_inner_str .= ' not null';
            }
            if (isset($map['default'])) {
                if ($map['cast'] === 'timestamp' && $map['default'] === 'current_timestamp') {
                    $create_table_inner_str .= " default {$map['default']}";
                } else if ($map['default'] !== 'current_timestamp') {
                    $create_table_inner_str .= " default '{$map['default']}'";
                }
            }
            if ($map['cast'] === 'timestamp' && isset($map['update_current_timestamp'])) {
                $create_table_inner_str .= " on update current_timestamp";
            }
            $create_table_inner_str .= ",";
        }
        if (isset($config_map['index'])) {
            foreach ($config_map['index'] as $index) {
                $column_str = '';
                foreach ($index['columns'] as $index_column_name) {
                    if (empty($column_str)) {
                        $column_str .= "`{$index_column_name}`";
                    } else {
                        $column_str .= ", `{$index_column_name}`";
                    }
                }
                $create_table_inner_str .= "index {$index['index_name']} ({$column_str}),";
            }
        }
        if (isset($config_map['unique'])) {
            foreach ($config_map['unique'] as $unique) {
                $column_str = '';
                foreach ($unique['columns'] as $unique_column_name) {
                    if (empty($column_str)) {
                        $column_str .= "`{$unique_column_name}`";
                    } else {
                        $column_str .= ", `{$unique_column_name}`";
                    }
                }
                $create_table_inner_str .= "unique key ({$column_str}),";
            }
        }
        $primary_key_str = '';
        foreach ($config_map['primary_key'] as $primary_key) {
            if (empty($primary_key_str)) {
                $primary_key_str .= "`{$primary_key}`";
            } else {
                $primary_key_str .= ", `{$primary_key}`";
            }
        }
        $create_table_inner_str .= "primary key ($primary_key_str)";

        foreach ($config_map['map'] as $map) {
            $host             = $db_config_map['database_settings'][$map['db']]['master']['host'];
            $port             = $db_config_map['database_settings'][$map['db']]['master']['port'];
            $table_name       = sprintf($config_map['table_name'] . $config_map['postfix'], $map['table_number']);
            $create_table_sql = "create table if not exists {$table_name} ({$create_table_inner_str}) engine=innodb;";

            if (!isset($dbh_map[$map['db']])) {
                $dsn = "mysql:dbname={$map['db']};host={$host};port={$port}";
                $dbh_map[$map['db']] = new PDO($dsn, $username, $password);
            }
            if (!$dbh_map[$map['db']]->query($create_table_sql)) {
                echo " -> エラー: {$map['db']}にテーブル「{$table_name}」を作成できませんでした\n";
                echo "    query: {$create_table_sql}\n";
                foreach ($dbh_map[$map['db']]->errorInfo() as $error) {
                    echo "    {$error}\n";
                }
                echo "\n";
            } else {
                echo " -> {$map['db']}にテーブル「{$table_name}」を作成しました\n";
            }
        }
    }
}

echo "\nテーブルの作成が完了しました ==========================\n\n";

exit;

