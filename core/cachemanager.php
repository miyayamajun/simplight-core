<?php

namespace Simplight;

/**
 * \Simplight\Cachemanager
 * @description Memcachedを操作するクラス
 *
 * @author https://github.com/miyayamajun 
 */
class Cachemanager
{
    const JSON_NAME_DEV  = 'memcached.dev.json';
    const JSON_NAME_STG  = 'memcached.stg.json';
    const JSON_NAME_PROD = 'memcached.production.json';

    private static $_instance;

    private $_memcached;

    /**
     * インスタンスを返す(シングルトン)
     *
     * @return CachemanagerObject
     */
    public static function create()
    {
        if (!defined('MEMCACHED_ENABLE')) {
            throw new \Simplight\Exception('Memcachedが有効ではありません', 'Memcachedエラー', STATUS_CODE_ERROR);
        }
        if (!isset(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * コンストラクタ
     *
     * @return void
     */
    private function __construct()
    {
        $this->_setup();
        $this->_setServerList();
    }

    /**
     * memcachedの初期設定を行う
     *
     * @return void
     */
    private function _setup()
    {
        $this->_memcached = new \Memcached();
        $option_list = array(
            // 分散アルゴリズムをConsistent hashにする
            \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT,
        );
        $this->_memcached->setOptions($option_list);
    }

    /**
     * 設定情報(連想配列)からサーバリストを読み込み登録する
     *
     * @return void
     */
    private function _setServerList()
    {
        $config_map = $this->_getConfigFromJson();
        $server_list = array();
        foreach ($config_map['server'] as $server_map) {
            $server_list[] = array($server_map['host'], $server_map['port'], $server_map['weight']);
        }
        $this->_memcached->addServers($server_list);
    }

    /**
     * 設定ファイル(json)を取得する
     *
     * @return array jsonから変換された連想配列
     */
    private function _getConfigFromJson()
    {
        if (defined('PROD')) {
            $filename = self::JSON_NAME_PROD;
        } else if (defined('STG')) {
            $filename = self::JSON_NAME_STG;
        } else {
            $filename = self::JSON_NAME_DEV;
        }
        $json        = file_get_contents(JSON_DIR . '/' . $filename);
        $config_hash = json_decode(mb_convert_encoding($json, 'UTF-8', 'SJIS, EUC-JP, ASCII, UTF-8'), true);

        return $config_hash;
    }

    /**
     * memcachedのgetメソッド使用してアイテムを取得する
     * @param mixed $key
     *
     * @return array [$value, $cas]
     */
    public function get($key)
    {
        $cas = null;
        $value = $this->_memcached->get($key, null, $cas);

        return array($value, $cas);
    } 

    /**
     * memcachedのsetメソッドでアイテムを格納する
     * @param mixed $key
     * @param mixed $value
     *
     * @return boolean
     */
    public function set($key, $value)
    {
        return $this->_memcached->set($key, $value);
    }

    /**
     * memcachedのcasメソッドでアイテムを格納する
     * @param mixed $cas
     * @param mixed $key
     * @param mixed $value
     *
     * @return boolean
     */
    public function cas($cas, $key, $value)
    {
        return $this->_memcached->cas($cas, $key, $value);
    }

    /**
     * memcachedのdeleteメソッドでアイテムを削除
     * @param mixed $key
     *
     * @return boolean
     */
    public function delete($key)
    {
        return $this->_memcached->delete($key);
    }
    
    /**
     * memacahedサーバに格納されている全てのキーを取得して、キーワードにマッチするものを削除する
     * @param string $keyword
     *
     * @return array 削除したキーのリスト
     */
    public function searchDelete($keyword)
    {
        $key_list        = $this->_memcached->getAllKeys();
        $delete_key_list = array();
        foreach ($key_list as $Key) {
            $match_flg = preg_match("/{$keyword}/", $key);
            if (false !== $match_flg && 0 < $match_flg) {
                $delete_key_list[] = $key;
            }
        }
        return $this->_memcached->deleteMulti($delete_key_list) ? $delete_key_list : array();
    }
}
