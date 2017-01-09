<?php

namespace Simplight;

/**
 * \Simplight\View
 * @description 表示用データを制御するクラス
 *
 * @author https://github.com/miyayamajun
 */
final class View
{
    private static $_instance = null;
    private static $_register_plugins = array(
        array('type' => 'function', 'name' => 'url'),
    );

    private $_smarty;

    /**
     * コンストラクタ
     * @param AppObject $app
     *
     * @return ViewObject
     */
    public function __construct($app)
    {
        if (!$app instanceof \Simplight\App) {
            throw new \Exception('Appインスタンス以外からの呼出に対応していません', STATUS_CODE_ERROR);
        }
        $this->_setup();
        $this->_registerPlugins();
    }

    /**
     * セットアップ
     *
     * @return void
     */
    private function _setup()
    {
        $this->_smarty = new \Smarty();
        $this->_smarty->setCacheDir(CACHE_DIR . '/smarty');
        $this->_smarty->setCompileDir(CACHE_DIR . '/smarty_compile');
        $this->_smarty->setTemplateDir(TEMPLATE_DIR);
    }

    /**
     * テンプレート関数の登録
     *
     * @return void
     */
    private function _registerPlugins()
    {
        $plugins = static::$_register_plugins;
        $view_methods = new \Simplight\ViewMethods();
        foreach ($plugins as $plugin) {
            $type     = $plugin['type'];
            $name     = $plugin['name'];
            $callback = isset($plugin['callback']) ? $plugin['callback'] : $plugin['name'];
            $this->_smarty->registerPlugin($type, $name, array(&$view_methods, $callback));
        }
    }

    /**
     * Smarty::displayメソッドラッパー
     * @param string $path テンプレートのパス
     *
     * @return void
     */
    public function display($path)
    {
        $this->_smarty->display($path);
    }
    
    /**
     * Smarty::fetchメソッドラッパー
     * @param string $path テンプレートのパス
     *
     * @return string レンダリングされた出力情報
     */
    public function fetch($path)
    {
        return $this->_smarty->fetch($path);
    }

    /**
     * Smarty::assignGlobalメソッドラッパー
     * @param mixed $key
     * @param mixed $param
     *
     * @return void
     */
    public function assign($key, $param)
    {
        $this->_smarty->assignGlobal($key, $param);
    }
}

final class ViewMethods
{
    /**
     * tmplate method: 引数からURLを生成して返す
     * @param array        $params
     * @param SmartyObject &$smarty
     *
     * @return $url
     */
    public function url($params, &$smarty)
    {
        $root = isset($params['ssl']) && $params['ssl'] === true ? HTTPS_ROOT_URL : HTTP_ROOT_URL;
        $controller = isset($params['controller']) ? $params['controller']         : 'root';
        $action     = isset($params['action'])     ? $params['action']             : 'root';
        $parameter  = isset($params['params'])     ? join('/', $params['params']) : '';

        return "$root/$controller/$action/$parameter";
    }
}
