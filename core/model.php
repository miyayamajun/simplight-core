<?php

namespace Simplight;

/**
 * Model
 * データやビジネスロジックを管理するクラス 
 *
 * @author https://github.com/miyayamajun
 */
abstract class Model
{
    /**
     * クラス定数
     */
    const ACCESSOR_NAME = '';
    const USE_ACCESSOR  = true;

    /**
     * クラス変数
     */
    protected static $_instance = array();

    /**
     * インスタンス変数
     */
    protected $_primary_key_data;
    protected $_data;
    protected $_diff_data;
    protected $_save_data;
    protected $_save_flg = false;

    /**
     * インスタンスを生成するメソッド(詳細は小クラスにて定義)
     *
     * @return ModelObject
     */
    public static function create()
    {
        if  (!isset(static::$_instance[0])) {
            static::$_instance[0] = new static();
        }
        return static::$_instance[0];
    }

    /**
     * コンストラクタ
     * @param array $primary_key_data
     *
     * @return ModelObject
     */   
    protected function __construct($primary_key_data = array())
    {
        if (static::USE_ACCESSOR) {
        
        }
        $this->_primary_key_data = $primary_key_data;
    }

    /**
     * データが存在するかチェックする
     * @param mixed $key
     *
     * @return bool
     */
    public function isExists($key)
    {
        if (!$this->_isFieldKey($key)) {
            $this->_setData();
            return isset($this->_data[$key]);
        }
        return false;
    }

    /**
     * データを取得する
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->_isFieldKey($key)) {
            $this->_setData();
            return isset($this->_data[$key]) ? $this->_data[$Key] : null;
        }
    }

    /**
     * データをセットする
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function set($key, $value)
    {
        $accessor = $this->_getAccessor();
        if ($accessor->isFieldKey($key)) {
            $this->_setData();
            if ($accessor->isDiffFieldKey($key)) {
                $diff = $value - $this->_data[$key];
                $this->_diff_data[$key] = $diff;
                $this->_data[$key] = $value;
            } else {
                $this->_data[$key] = $value;
            }
            $this->_save_flg = true;
        }
    }

    /**
     * レコードをDBに挿入する(重複してる場合はエラー)
     *
     * @return void
     */
    public function insert()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();

        if (!$accessor->insert($this->_save_data)) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
    }

    /**
     * レコードを更新する(重複してる場合はエラー)
     *
     * @return void
     */
    public function update()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();
        $accessor->update($this->_primary_key_data, $this->_save_data);
    }

    /**
     * レコードをDBに挿入する(重複してる場合は更新)
     *
     * @return void
     */
    public function save()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();

        if (!$accessor->save($this->_primary_key_data, $this->_save_data)) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
    }

    /**
     * レコードを削除する
     *
     * @return void
     */
    public function delete()
    {
        $accessor = $this->_getAccessor();
        $accessor->delete($this->_primary_key_data);
    }

    /**
     * 保存用連想配列をセットする
     *
     * @return void
     */
    protected function _setSaveData()
    {
        $this->_save_data = $this->_data;

        // 差分保存データがある時は差し替え
        if (is_array($this->_diff_data) && !empty($this->_diff_data)) {
            foreach ($this->_diff_data as $key => $value) {
                $this->_save_data[$key] = $value;
            }
        }
    }

    /**
     * データソースからデータを取得してセットする
     *
     * @return void
     */
    protected function _setData()
    {
        if (!is_null($this->_data)) {
            return;
        }
        $accessor    = $this->_getAccessor();
        $this->_data = $accessor->get($this->_primary_key_data);
        if (empty($this->_data)) {
            $this->_data = $this->_getInitData();
        }
    }

    /**
     * DBにデータが存在しない場合に、初期状態の_dataを生成して取得する
     *
     * @return array
     */
    protected function _getInitData()
    {
        $accessor = $this->_getAccessor();
        $init_data = array();
        $field_list = $accessor->getFieldList();
        foreach ($field_list as $key_name) {
            $init_data[$key_name] = null;
        }
        return $init_data;
    }

    /**
     * accessorインスタンスを取得する
     *
     * @return accessor instance
     */
    protected function _getAccessor()
    {
        $accessor_name = static::ACCESSOR_NAME;
        return $accessor_name::create();
    }
}

/**
 * ListModel
 * データやビジネスロジックを管理するクラス 
 *
 * @author https://github.com/miyayamajun
 */
abstract class ListModel extends Model implements Iterator
{
    /**
     * データが存在するかチェックする
     * @param mixed $key
     *
     * @return bool
     */
    public function isExists($key)
    {
        if (!$this->_isFieldKey($key)) {
            $this->_setData();
            return isset($this->_data[$key]);
        }
        return false;
    }

    /**
     * データを取得する
     * @param mixed $key
     *
     * @return mixed
     */
    public function get($key)
    {
        if (!$this->_isFieldKey($key)) {
            $this->_setData();
            return isset($this->_data[$key]) ? $this->_data[$Key] : null;
        }
    }

    /**
     * データをセットする
     * @param mixed $key
     * @param mixed $value
     *
     * @return void
     */
    public function set($key, $value)
    {
        $accessor = $this->_getAccessor();
        if ($accessor->isFieldKey($key)) {
            $this->_setData();
            if ($accessor->isDiffFieldKey($key)) {
                $diff = $value - $this->_data[$key];
                $this->_diff_data[$key] = $diff;
                $this->_data[$key] = $value;
            } else {
                $this->_data[$key] = $value;
            }
            $this->_save_flg = true;
        }
    }

    /**
     * レコードをDBに挿入する(重複してる場合はエラー)
     *
     * @return void
     */
    public function insert()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();

        if (!$accessor->insert($this->_save_data)) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
    }

    /**
     * レコードを更新する(重複してる場合はエラー)
     *
     * @return void
     */
    public function update()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();
        $accessor->update($this->_primary_key_data, $this->_save_data);
    }

    /**
     * レコードをDBに挿入する(重複してる場合は更新)
     *
     * @return void
     */
    public function save()
    {
        if (!$this->_save_flg) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
        $this->_setSaveData();
        $accessor = $this->_getAccessor();

        if (!$accessor->save($this->_primary_key_data, $this->_save_data)) {
            throw new \Simplight\Exception('データを保存できませんでした', 'データ保存エラー', STATUS_CODE_ERROR);
        }
    }

    /**
     * レコードを削除する
     *
     * @return void
     */
    public function delete()
    {
        $accessor = $this->_getAccessor();
        $accessor->delete($this->_primary_key_data);
    }

    /**
     * 保存用連想配列をセットする
     *
     * @return void
     */
    protected function _setSaveData()
    {
        $this->_save_data = $this->_data;

        // 差分保存データがある時は差し替え
        if (is_array($this->_diff_data) && !empty($this->_diff_data)) {
            foreach ($this->_diff_data as $key => $value) {
                $this->_save_data[$key] = $value;
            }
        }
    }

    /**
     * データソースからデータを取得してセットする
     *
     * @return void
     */
    protected function _setData()
    {
        if (!is_null($this->_data)) {
            return;
        }
        $accessor    = $this->_getAccessor();
        $this->_data = $accessor->get($this->_primary_key_data);
        if (empty($this->_data)) {
            $this->_data = $this->_getInitData();
        }
    }

    /**
     * DBにデータが存在しない場合に、初期状態の_dataを生成して取得する
     *
     * @return array
     */
    protected function _getInitData()
    {
        $accessor = $this->_getAccessor();
        $init_data = array();
        $field_list = $accessor->getFieldList();
        foreach ($field_list as $key_name) {
            $init_data[$key_name] = null;
        }
        return $init_data;
    }
}
