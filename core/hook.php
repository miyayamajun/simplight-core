<?php

namespace Simplight;

/**
 * \Simplight\Hook
 * @description コントローラ処理前に指定したページにて処理を実行するクラス
 *
 * @author https://github.com/miyayamajun
 */
final class Hook
{
    private $_pre_hook_list;
    private $_post_hook_list;
    private $_app;

    /**
     * コンストラクタ
     * @param AppObject $app
     *
     * @return void
     */
    public function __construct($app)
    {
        if (!$app instanceof \Simplight\App) {
            throw new \Exception('Appインスタンス以外からの呼出に対応していません', STATUS_CODE_ERROR);
        }
        $this->_app = $app;
        $this->_setup();
    }

    /**
     * _hook_listに登録されたHookを処理する
     *
     * @return void
     */
    public function preHookExecute()
    {
        if (empty($this->_pre_hook_list)) {
            return;
        }
        foreach ($this->_pre_hook_list as $hook) {
            $hook->execute();
        }
    } 

    
    /**
     * _hook_listに登録されたHookを処理する
     *
     * @return void
     */
    public function postHookExecute()
    {
        if (empty($this->_post_hook_list)) {
            return;
        }
        foreach ($this->_post_hook_list as $hook) {
            $hook->execute();
        }
    } 

    /**
     * jsonから実行可能なHookをリストに登録する
     *
     * @return void
     */
    private function _setup()
    {
        $tmp_json         = file_get_contents(JSON_DIR . '/hook.json');
        $json             = mb_convert_encoding($tmp_json, 'utf8');
        $config_list      = json_decode(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $json), true);

        foreach ($config_list as $map) {
            if ($this->_app->getTimestamp() < strtotime($map['open_date'])) {
                continue;
            }
            if ($this->_app->getTimestamp() > strtotime($map['close_date'])) {
                continue;
            }
            $hook_class_name = '\\Simplight\\Hook\\' . ucfirst($map['name']);
            if (isset($map['type']) && $map['type'] === 'post') {
                $this->_post_hook_list[]  = new $hook_class_name($this->_app);
            } else {
                $this->_pre_hook_list[]  = new $hook_class_name($this->_app);
            }
        }
    }
}

/**
 * \Simplight\HookBase
 * @description Hookの根幹クラス
 *
 * @author https://github.com/miyayamajun
 */
abstract class HookBase
{
    /**
     * 処理を実行するコントローラ名とアクション名を _ (アンダースコア) で繋ぐ
     * 全てが対象の時は * (アスタリスクを指定する)
     */
    public static $target_action_list = array(
        '*_*',
    );

    private $_app;

    /**
     * コンストラクタ
     * @param AppObject $app
     *
     * @return void
     */
    public function __construct($app)
    {
        $this->_app = $app;
    }

    /**
     * target_action_listをもとに実行可能かチェックする
     *
     * @return boolean trueなら実行可能
     */
    protected function _validate()
    {
        $request_controller_name = $this->_app->request->getControllerName();
        $request_action_name     = $this->_app->request->getActionName();
        $target_action_list      = static::$target_action_list;

        foreach ($target_action_list as $target_action) {
            $target_list            = explode('_', $target_action);
            $target_controller_name = $target_list[0];
            $target_action_name     = $target_list[1];

            $controller_valid = '*' === $target_controller_name || $request_controller_name === $target_controller_name;
            $action_valid     = '*' === $target_action_name || $request_action_name === $target_action_name;
            if ($controller_valid && $action_valid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hookの実行処理本体。子クラスで処理を記述する
     *
     * @return void
     */
    public function execute()
    {
        if (!$this->_validate()) {
            return;
        }
    }
}
