<?php

namespace Simplight;

/**
 * \Simplight\Accessor
 * @description Simplight用DAO
 *
 * @author https://github.com/miyayamajun
 */
abstract class Accessor 
{
    const CONFIG_JSON_PATH  = '';
    const DIVISION_KEY_NAME = '';

    const DB_JSON_PATH_PRDO = 'mysql.prod.json';
    const DB_JSON_PATH_STG  = 'mysql.stg.json';
    const DB_JSON_PATH_DEV  = 'mysql.dev.json';

    const USE_MASTER = true;
    const USE_SLAVE  = false;
    const NG_SLAVE_QUERY = '/insert|update|delete|truncate|drop|create|transaction|lock/i';

    protected static $_instance             = null;
    protected static $_db_config            = array();
    protected static $_tbl_config           = array();
    protected static $_dsn_map              = array();
    protected static $_timestamp_field_list = array();
    protected static $_primary_key_list     = array();
    protected static $_field_list           = array();
    protected static $_diff_field_list      = array();

    protected $_get_query;
    protected $_db_handler;

    /**
     * インスタンスを生成する(シングルトン)
     *
     * @return object Accessorのオブジェクト
     */
    public static function create()
    {
        if (!isset(static::$_instance)) {
            static::$_instance = new static();
        }
        return static::$_instance;
    }

    /**
     * Accessor初期化
     */
    protected function __construct()
    {
        $this->_setDBConfigMap();
        $this->_setTableConfigMap();
        $this->_setDsnMap();
        $this->_setGetQuery();
    }

    /**
     * キー($param_list)に応じたレコードを1件取得する
     * @param array $param_list where句生成用の連想配列
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     * @param bool  $use_master マスターDBにアクセスするかどうかのフラグ
     *
     * @return array レコード1件分の連想配列
     */
    public function get($param_list, $div_hint = null, $use_master = self::USE_SLAVE)
    {
        $this->_validate($param_list);
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($param_list, $div_hint, $use_master);

        $statement = $db_handler->prepare(sprintf($this->_get_query, $tbl_name));
        foreach ($param_list as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result : array();
    }

    /**
     * キー($param_list)に応じたレコードを$offsetから$limit件取得する
     * @param array $param_list where句生成用の連想配列
     * @param int   $offset     レコード取得開始位置
     * @param int   $limit      レコード取得件数
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     * @param bool  $use_master マスターDBにアクセスするかどうかのフラグ
     *
     * @return array レコード$limit件分の連想配列
     */
    public function find($param_list, $offset = 0, $limit = 10, $div_hint = null, $use_master = self::USE_SLAVE)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($param_list, $div_hint, $use_master);

        $statement = $db_handler->prepare($this->_getFindQuery($tbl_name, $param_list, $offset, $limit));
        foreach ($param_list as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();

        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result ? $result : array();
    }

    /**
     * 分割キー($div_hint)に対応したテーブルのレコードを$offsetから$limit件取得する
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列
     * @param int   $offset     レコード取得開始位置
     * @param int   $limit      レコード取得件数
     * @param bool  $use_master マスターDBにアクセスするかどうかのフラグ
     *
     * @return array レコード$limit件分の連想配列
     */
    public function findAll($div_hint, $offset = 0, $limit = 20, $use_master = self::USE_SLAVE)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($div_hint, $div_hint, $use_master);

        $offset = intval($offset);
        $limit  = intval($limit);
        $sql    = "select * from $tbl_name limit $offset, $limit";

        $statement = $db_handler->query($sql);
        $result    = $statement->fetchAll(\PDO::FETCH_ASSOC);
        return $result ? $result : array();
    }

    /**
     * レコードを1件挿入する
     * @param array $data_list 挿入するレコード情報
     * @param array $div_hint  対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード挿入成功or失敗
     */
    public function insert($data_list, $div_hint = null)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($data_list, $div_hint, self::USE_MASTER);
        $set_prefix = 'set_';
        $set_string = $this->_getQueryPhrase($data_list, ',', $set_prefix, $is_insert = true);
        $sql        = "insert into {$tbl_name} set {$set_string}";
        $statement  = $db_handler->prepare($sql);
        $this->_statementBindValue($statement, $data_list, $set_prefix);

        return $statement->execute();
    }

    /**
     * レコードを更新する
     * @param array $param_list where句生成用の連想配列
     * @param array $data_list  更新対象のカラムと値の連想配列
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード更新成功or失敗
     */
    public function update($param_list, $data_list, $div_hint = null)
    {
        $this->_validate($param_list);
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($param_list, $div_hint, self::USE_MASTER);

        $set_prefix   = 'set_';
        $set_string   = $this->_getQueryPhrase($data_list, ',', $set_prefix);
        $where_prefix = 'where_';
        $where_string = $this->_getQueryPhrase($param_list, ' and ', $where_prefix);

        $sql       = "update {$tbl_name} set {$set_string} where {$where_string}";
        $statement = $db_handler->prepare($sql);
        $this->_statementBindValue($statement, $data_list, $set_prefix);
        $this->_statementBindValue($statement, $param_list, $where_prefix);
        return $statement->execute();
    }
    
    /**
     * レコード1件を挿入、すでにレコードが存在する場合は更新する
     * @param array $data_list  更新対象のカラムと値の連想配列
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード挿入更新成功or失敗
     */
    public function save($data_list, $div_hint = null)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($data_list, $div_hint, self::USE_MASTER);

        // primary key の無い配列を作る
        $no_primary_list = $this->_getNoPrimaryKeyList($data_list);

        $set_prefix    = 'set_';
        $set_string    = $this->_getQueryPhrase($data_list, ',', $set_prefix, $is_insert = true);
        $update_string = $this->_getQueryPhrase($no_primary_list, ',', $set_prefix);
        
        $sql       = "insert into {$tbl_name} set {$set_string} on duplicate key update {$update_string}";
        $statement = $db_handler->prepare($sql);
        $this->_statementBindValue($statement, $data_list, $set_prefix);
        return $statement->execute();
    }

    /**
     * データを一括挿入する(重複するデータがある場合はエラー)
     * @param array $multi_data_list 保存するデータリスト
     * @param array $div_hint        対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード挿入更新成功or失敗
     */
    public function bulkInsert($multi_data_list, $div_hint)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($div_hint, null, self::USE_MASTER);
        $sql = $this->_getBulkInsertQuery($multi_data_list);

        return $this->_bulkExecute($db_handler, $sql, $multi_data_list);
    }

    /**
     * データを一括挿入する(重複するデータがある場合は更新)
     * @param array $multi_data_list 保存するデータリスト
     * @param array $div_hint        対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード挿入更新成功or失敗
     */
    public function bulkSave($multi_data_list, $div_hint)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($div_hint, null, self::USE_MASTER);
        $sql = $this->_getBulkInsertQuery($multi_data_list);

        // primary key の無い配列を作る
        $tmp_data_list   = reset($multi_data_list);
        $no_primary_list = $this->_getNoPrimaryKeyList($tmp_data_list);

        $sql .= 'on duplicate key update ';
        $update_string = '';
        foreach ($no_primary_list as $key => $data) {
            $sql .= empty($update_string) ? '' : ',';
            if (in_array($key, static::$_diff_field_list)) {
                $sql .= "`{$key}`=`{$key}`+values(`{$key}`)";
            } else {
                $sql .= "`{$key}`=values(`{$key}`)";
            }
        }
        return $this->_bulkExecute($db_handler, $sql, $multi_data_list);
    }

    /**
     * キー($param_list)に応じたレコードを削除する
     * @param array $param_list where句生成用の連想配列
     * @param array $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return bool レコード削除成功or失敗
     */
    public function delete($param_list, $div_hint = null)
    {
        $this->_validate($param_list);
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($param_list, $div_hint, self::USE_MASTER);

        $where_prefix = 'where_';
        $where_string = $this->_getQueryPhrase($param_list, ' and ', $where_prefix);

        $sql       = "delete from {$tbl_name} where {$where_string}";
        $statement = $db_handler->prepare($sql);
        $this->_statementBindValue($statement, $param_list, $where_prefix);
        return $statement->execute();
    }

    /**
     * 指定したSQLを実行する
     * @param string $sql              実行するSQL文
     * @param array  $placeholder_list SQLにプレースホルダーが含まれる場合の置き換えリスト
     * @param array  $div_hint   対象テーブル指定用の分割キー連想配列(nullなら$param_listから分割キーを取得する)
     *
     * @return mixed
     */
    public function query($sql, $placeholder_list, $div_hint, $use_master = self::USE_MASTER)
    {
        list($div_key, $db_handler, $tbl_name) = $this->_getConnectParam($where_list, $div_hint, $use_master);
        if ($use_master === self::USE_SLAVE && preg_match(self::NG_SLAVE_QUERY, $sql) !== false) {
            throw new \Simplight\Exception('スレーブに書き込もうとしています');
        }

        $statement = $db_handler->prepare($sql);
        if (!empty($placeholder_list)) {
            $this->_statementBindValue($statement, $placeholder_list);
        }
        if (preg_match(self::NG_SLAVE_QUERY, $sql) !== false) {
            return $statement->execute();
        } else {
            $statement->execute();
            return $statement->fetchAll();
        }
    }

    /**
     * フィールドリストに登録されているキーか調べる
     * @param mixed $key
     *
     * @return bool
     */
    public function isFieldKey($key)
    {
        return isset(static::$_field_list[$key]);
    }

    /**
     * 差分保存対象フィールドリストに登録されているキーか調べる
     * @param mixed $key
     *
     * @return bool
     */
    protected function isDiffFieldKey($key)
    {
        return isset(static::$_diff_field_list[$key]);
    }

    /**
     * フィールドリストを取得する
     * @param mixed $key
     *
     * @return bool
     */
    protected function getFieldList()
    {
        return static::$_field_list;
    }

    /**
     * prefixとキーを繋げてbindValueを実行する
     * @param PDOStatement &$statement
     * @param array        $list
     * @param string       $prefix
     *
     * @return void
     */
    protected function _statementBindValue(&$statement, $list, $prefix = '')
    {
        foreach ($list as $key => $value) {
            $statement->bindValue($prefix . $key, $value);
        }
    }

    /**
     * 連想配列からクエリフレーズを取得する
     * @param array  $list    クエリフレーズ生成用の連想配列
     * @param string $div_str フレーズ分割用の文字列
     * @param string $key_prefix キーの接頭辞
     * @param string saveメソッドからの呼び出しフラグ
     *
     * @return string クエリフレーズ
     */
    protected function _getQueryPhrase($list, $div_str, $key_prefix = '', $is_insert = false)
    {
        $list = $this->_getFieldDataList($list);
        $keys = array_keys($list);
        $str  = join($div_str, array_map(
            function ($key) use ($key_prefix, $is_insert) {
                if (in_array($key, static::$_timestamp_field_list)) {
                    return "`{$key}`=from_unixtime(:{$key_prefix}{$key})";
                } else if (in_array($key, static::$_diff_field_list)) {
                    return $is_insert ? "`{$key}`=:{$key_prefix}{$key}" : "`{$key}`=`{$key}`+:{$key_prefix}{$key}";
                } else {
                    return "`{$key}`=:{$key_prefix}{$key}";
                }
            }, $keys)
        );
        return $str;
    }

    /**
     * プライマリキーを含まないデータを生成して取得する
     * @param array $data_list
     *
     * @return array
     */
    protected function _getNoPrimaryKeyList($data_list)
    {
        $no_primary_list  = $data_list;
        $primary_key_list = static::$_primary_key_list;
        foreach ($primary_key_list as $primary_key) {
            if (!isset($no_primary_list[$primary_key])) {
                continue;
            }
            unset($no_primary_list[$primary_key]);
        }
        return $no_primary_list;
    }

    /**
     * フィールドリストに登録されているキーのみの配列を返す
     * @param &array $list
     *
     * @return array
     */
    protected function _getFieldDataList($list)
    {
        $return_list    = array();
        foreach ($list as $key => $data) {
            // フィールドに登録されたデータかチェックする
            if (!in_array($key, static::$_field_list) || isset($return_list[$key])) {
                continue;
            }
            $return_list[$key] = $data;
        }
        return $return_list;
    }

    /**
     * find用のクエリを生成してセットする
     * @param string $tbl_name
     * @param array  $param_list where句生成用の連想配列
     * @param int    $offset
     * @param int    $limit
     *
     * @return string find用クエリ
     */
    protected function _getFindQuery($tbl_name, $param_list, $offset, $limit)
    {
        $callback  = function ($key) { return "`{$key}` = :{$key}"; };
        $where_str = join(' and ', array_map($callback, array_keys($param_list)));
        return "select * from {$tbl_name} where {$where_str} limit {$offset}, {$limit}";
    }

    /**
     * where句にプライマリキー、分割キーが含まれているかチェックする
     * @param array $param_list where句生成用の連想配列
     *
     * @return void
     */
    protected function _validate($param_list)
    {
        $key_list = array_keys($param_list);
        if ($key_list != static::$_primary_key_list) {
            throw new \Simplight\Exception('プライマリキーが一致しません', 'Accessor Error', STATUS_CODE_ERROR);
        }
        if (!isset($param_list[static::DIVISION_KEY_NAME])) {
            throw new \Simplight\Exception('分割キーが渡されていません', 'Accessor Error', STATUS_CODE_ERROR);
        }
    }

    /**
     * get用のクエリを生成してセットする
     *
     * @return void
     */
    protected function _setGetQuery()
    {
        $callback  = function ($key) { return "`{$key}` = :{$key}"; };
        $primary_key_list = static::$_primary_key_list;
        $where_str = join(' and ', array_map($callback, $primary_key_list));
        $this->_get_query = "select * from %s where {$where_str}";
    }

    /**
     * 一括更新用のクエリを生成して取得する
     * @param array $multi_data_list
     *
     * @return string
     */
    protected function _getBulkInsertQuery($multi_data_list)
    {
        $sql        = "insert into {$tbl_name} values ";
        $values_str = '';
        foreach ($multi_data_list as $idx => $data_list) {
            $set_prefix  = "set_{$idx}_";
            $values_str .= empty($values_str) ? '(' : ',(';
            $values_str .= join(',', array_map(function($key) use ($set_prefix) {
                $data_key = $set_prefix . $key;
                return in_array($key, static::$_timestamp_field_list) ? "from_unixtime(:{$data_key})" : ":{$data_key}";
            }, array_keys($data_list)));
            $values_str .= ')';
        }
        $sql .= $values_str;
        return $sql;
    }

    /**
     * 一括更新の共通処理
     * @param PDO instance $db_handler
     * @param string       $sql
     * @param array        $multi_data_list
     *
     * @return book 実行結果
     */
    protected function _bulkExecute($db_handler, $sql, $multi_data_list)
    {
        $statement = $db_handler->prepare($sql);
        foreach ($multi_data_list as $idx => $data_list) {
            $set_prefix  = "set_{$idx}_";
            $this->_statementBindValue($statement, $data_list, $set_prefix);
        }
        return $statement->execute();
    }

    /**
     * DBコネクション用のデータを取得する
     * @param array $data_list where句や挿入用データの連想配列
     * @param array $div_hint  分割キー指定用の連想配列
     * @param bool  $use_master マスターDBにアクセスするかどうかのフラグ
     *
     * @return array(分割キー, 対象DBハンドラー, 対象テーブル名)
     */
    protected function _getConnectParam($data_list, $div_hint, $use_master)
    {
        $div_key    = $this->_getDivisionKey($data_list, $div_hint);
        $db_handler = $this->_getDBHandler($div_key, $use_master);
        $tbl_name   = $this->_getTableName($div_key);

        return array($div_key, $db_handler, $tbl_name);
    }

    /**
     * 連想配列から分割キーを取得する
     * @param array $data_list  where句や挿入用データの連想配列
     * @param array $div_hint   分割キー指定用の連想配列
     *
     * @return int
     */
    protected function _getDivisionKey($data_list, $div_hint)
    {
        $key_name = static::DIVISION_KEY_NAME;
        if (!is_null($div_hint) && isset($div_hint[$key_name])) {
            $key = $div_hint[$key_name];
        } else if (isset($data_list[$key_name])) {
            $key = $data_list[$key_name];
        }
        if (!isset($key)) {
            throw new \Simplight\Exception("分割キーが渡されていません [{$key_name}]", 'Error Accessor Division Key', STATUS_CODE_ERROR);
        }
        return intval($key % static::$_tbl_config['division_count']);
    }

    /**
     * 分割キーから対応するDBハンドラーを取得する
     * @param int  $div_key    分割キー
     * @param bool $use_master マスターDBにアクセスするかどうかのフラグ
     *
     * @return PDO Object
     */
    protected function _getDBHandler($div_key, $use_master = self::USE_SLAVE)
    {
        $dsn_info  = static::$_dsn_map[$div_key];
        $db_name   = $dsn_info['db_name'];
        $repl_name = $use_master ? 'master' : 'slave';
        if (isset($this->_db_handler[$db_name][$repl_name])) {
            return $this->_db_handler[$db_name][$repl_name];
        }

        $username = static::$_db_config['username'];
        $password = static::$_db_config['password'];
        $info     = $dsn_info['info'][$repl_name];
        $dsn      = "mysql:dbname={$db_name};host={$info['host']};port={$info['port']}";
        $this->_db_handler[$db_name][$repl_name] = new \PDO($dsn, $username, $password);
        return $this->_db_handler[$db_name][$repl_name];
    }

    /**
     * 分割キーから対応するテーブル名を取得する
     * @param int $div_key 分割キー
     *
     * @return string テーブル名
     */
    protected function _getTableName($div_key)
    {
        return static::$_dsn_map[$div_key]['table_name'];
    }

    /**
     * DB設定ファイルから設定を読み出す
     *
     * @return void
     */
    protected function _setDBConfigMap()
    {
        if (!empty(static::$_db_config)) {
            return;
        }
        $json_path = $this->_getDBJsonPath();
        $tmp_json  = file_get_contents(JSON_DIR . '/' . $json_path);
        $json      = mb_convert_encoding($tmp_json, 'UTF-8', 'SJIS, EUC-JP, ASCII');
        static::$_db_config = json_decode($json, true);
    }

    /**
     * DB設定jsonファイルのパスを取得する
     *
     * @return string jsonファイルのパス
     */
    protected function _getDBJsonPath()
    {
        if (defined('PRDO')) {
            return self::DB_JSON_PATH_PROD;
        } else if (defined('STG')) {
            return self::DB_JSON_PATH_STG;
        } else {
            return self::DB_JSON_PATH_DEV;
        }
    }

    /**
     * jsonファイルから設定を読み出す
     *
     * @return void
     */
    protected function _setTableConfigMap()
    {
        if (!empty(static::$_tbl_config)) {
            return;
        }
        $tmp_json = file_get_contents(TABLE_JSON_DIR . '/' . static::CONFIG_JSON_PATH);
        $json     = mb_convert_encoding($tmp_json, 'UTF-8', 'SJIS, EUC-JP, ASCII');
        static::$_tbl_config = json_decode($json, true);
    }

    /**
     * データベース識別子をセットする
     *
     * @return void
     */
    protected function _setDsnMap()
    {
        if (!empty(static::$_dsn_map)) {
            return;
        }
        $config  = static::$_tbl_config;
        $dsn_map = array();

        foreach ($config['map'] as $map) {
            $db_name  = $map['db'];
            $tbl_name = sprintf($config['table_name'] . $config['postfix'], $map['table_number']);
            $tmp_map  = array(
                'db_name'    => $map['db'],
                'table_name' => $tbl_name,
                'info'       => static::$_db_config['database_settings'][$map['db']],
            );
            $range_list = range($map['key_range_min'], $map['key_range_max']);
            $dsn_map    = array_merge($dsn_map, array_fill_keys($range_list, $tmp_map));
        }
        static::$_dsn_map = $dsn_map;
    }
}
