<?php

define('BATCH_MODE', 1);

require_once dirname(__FILE__) . '/../../app/app.php';
$app = \Simplight\App::create();

if (defined('RELEASE')) {
    $db_json = json_decode(file_get_contents(JSON_DIR . '/mysql.release.json'), true);
} else {
    $db_json = json_decode(file_get_contents(JSON_DIR . '/mysql.dev.json'), true);
}

$username = $db_json['username'];
$password = $db_json['password'];

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
$dbh = array();

foreach ($iterator as $fileinfo) {
    if ($fileinfo->isFile()) {
        $class_name = '';
        $file_path  = $fileinfo->getPathname();
        if (!preg_match('/\.json$/', $file_path)) {
            continue;
        }

        $json = json_decode(file_get_contents($file_path), true);
        
        $create_table_inner_str = "";
        foreach ($json['columns'] as $name => $array) {
            $create_table_inner_str .= "`{$name}`";
            $create_table_inner_str .= " {$array['cast']}";
            if (isset($column_length_template[$array['cast']])) {
                $create_table_inner_str .= isset($array['length']) ? "({$array['length']})" : "({$column_length_template[$array['cast']]})";
            }
            if (isset($array['unsigned']) && $array['unsigned'] === true) {
                $create_table_inner_str .= ' unsigned';
            }
            if (!isset($array['null_ok']) || !$array['null_ok']) {
                $create_table_inner_str .= ' not null';
            }
            if (isset($array['default'])) {
                if ($array['cast'] === 'timestamp' && $array['default'] === 'current_timestamp') {
                    $create_table_inner_str .= " default {$array['default']}";
                } else if ($array['default'] !== 'current_timestamp') {
                    $create_table_inner_str .= " default '{$array['default']}'";
                }
            }
            if ($array['cast'] === 'timestamp' && isset($array['update_current_timestamp'])) {
                $create_table_inner_str .= " on update current_timestamp";
            }
            $create_table_inner_str .= ",";
        }
        if (isset($json['index'])) {
            foreach ($json['index'] as $index) {
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
        if (isset($json['unique'])) {
            foreach ($json['unique'] as $unique) {
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
        foreach ($json['primary_key'] as $primary_key) {
            if (empty($primary_key_str)) {
                $primary_key_str .= "`{$primary_key}`";
            } else {
                $primary_key_str .= ", `{$primary_key}`";
            }
        }
        $create_table_inner_str .= "primary key ($primary_key_str)";

        foreach ($json['map'] as $map) {
            $host             = $db_json['database_settings'][$map['db']]['host'];
            $port             = $db_json['database_settings'][$map['db']]['port'];
            $table_name       = sprintf($json['table_name'] . $json['postfix'], $map['table_number']);
            $create_table_sql = "create table if not exists {$table_name} ({$create_table_inner_str}) engine=innodb;";

            if (!isset($dbh[$map['db']])) {
                $dsn = "mysql:dbname={$map['db']};host={$host};port={$port}";
                $dbh[$map['db']] = new PDO($dsn, $username, $password);
            }
            if (!$dbh[$map['db']]->query($create_table_sql)) {
                echo " -> エラー: {$map['db']}にテーブル「{$table_name}」を作成できませんでした\n";
                foreach ($dbh[$map['db']]->errorInfo() as $error) {
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

