<?php

namespace Simplight;

define('APP_DIR', dirname(__FILE__));
define('BASE_DIR', dirname(APP_DIR));
define('SRC_DIR', BASE_DIR . '/src');

require_once APP_DIR  . '/core/define.php';
require_once CORE_DIR . '/autoloader.php';
require_once LIB_DIR  . '/smarty/Smarty.class.php';

if (defined('DEV')) {
    require_once APP_DIR . '/debug/functions.php';
}

/**
 * \Simplight\App
 * @description Simplight WAFの起点となるクラス
 *
 * @author https://github.com/miyayamajun
 */
final class App
{
    private static $_instance = null;
    
    private $_autoloader;
    private $_controller;
    private $_action;
    private $_hook;
    
    public $request;
    public $view;

    /**
     * Appインスタンスをシングルトンで返す
     *
     * @return AppObject
     */
    public static function create()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 初期化
     *
     * @return AppObject
     */
    private function __construct()
    {
        try {
            $this->_setAutoloader();
            $this->_setView();
            if (!defined('BATCH_MODE')) {
                $this->_setRequest();
            }
        } catch (\Exception $exception) {
            $this->setError($exception); 
            exit;
        }
    }

    /**
     * autoloaderをセットする
     *
     * @return void
     */
    private function _setAutoloader()
    {
        try {
            $this->_autoloader = new \Simplight\Autoloader($this);

            /**
             * 各ディレクトリを登録
             */
            $this->_autoloader->regist(CORE_DIR);
            $this->_autoloader->regist(HOOK_DIR);
            $this->_autoloader->regist(ACCESSOR_DIR);
            $this->_autoloader->regist(MODEL_DIR);
            $this->_autoloader->regist(CONTROLLER_DIR);
        } catch (\Simplight\Exception $exception) {
            $this->setError($exception); 
            exit;
        }
    }

    /**
     * Viewをセットする
     *
     * @return void
     */
    private function _setView()
    {
        $this->view = new \Simplight\View($this);
    }

    /**
     * リクエストメッセージをセットする
     *
     * @return void
     */
    private function _setRequest()
    {
        $this->request = new \Simplight\Request($this);
    }

    /**
     * コントローラを呼び出して処理を実行する
     *
     * @return void
     */
    public function main()
    {
        try {
            // リクエストから実行対象コントローラを呼び出す
            $controller_name = $this->request->getControllerClassName();
            if (!class_exists($controller_name)) {
                throw new \Simplight\Exception('ページが見つかりませんでした<br>URLを確かめてください', 'ページが見つかりません', STATUS_CODE_NOT_FOUND);
            }
            $this->_controller = new $controller_name($this);
            
            // リクエストから実行対象アクションをセットする
            $action_name = $this->request->getActionName();
            if (!method_exists($this->_controller, $action_name)) {
                throw new \Simplight\Exception('ページが見つかりませんでした<br>URLを確かめてください', 'ページが見つかりません', STATUS_CODE_NOT_FOUND);
            }
            // コントローラー処理前にPRE_HOOKを実行する
            $this->_executeHook('pre');
            
            // コントローラメソッドを実行
            $this->_controller->before_action_method();
            $this->_controller->$action_name();
            $this->_controller->after_action_method();

            // コントローラー処理後にPOST_HOOKを実行する
            $this->_executeHook('post');
            
            // レンダリングフラグが有効ならレンダリングする
            if ($this->_controller->isRender()) {
                $output = $this->view->fetch($this->_controller->getTemplatePath());
                $this->_setResponseHeader(200, strlen($output));
                echo $output;
            } else {
                $this->_setResponseHeader(200);
            }
        } catch (\Simplight\Exception $exception) {
            $this->setError($exception);
        }
    }

    /**
     * 有効なhookを実行する
     * @param string $type
     *
     * @return void
     */
    private function _executeHook($type)
    {
        if (!isset($this->_hook)) {
            $this->_hook = new \Simplight\Hook($this);   
        }
        if ($type === 'pre') {
            $this->_hook->preHookExecute();
        } else if ($type === 'post') {
            $this->_hook->postHookExecute();
        }
    }

    /**
     * アプリのタイムスタンプを取得する
     *
     * @return int
     */
    public function getTimestamp()
    {
        // @todo 時刻を変えられるようにする
        return time();
    }

    /**
     * エラー情報をセットして、画面に表示する
     * @param Exception $exception
     *
     * @return void
     */
    public function setError(\Exception $exception)
    {
        require_once CORE_DIR       . '/controller.php';
        require_once CONTROLLER_DIR . '/error.php';

        $this->_setResponseHeader($exception->getCode());
        $this->_controller = new \Simplight\Controller\Error($this, 'error/error.tpl');
        $this->_controller->error($exception);
        $this->view->display($this->_controller->getTemplatePath());
    }

    /**
     * レスポンスヘッダを生成する
     * @param int $status_code
     * @param int $bytes
     *
     * @return void
     */
    private function _setResponseHeader($status_code, $bytes = null)
    {
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        http_response_code($status_code);

        if ($status_code === STATUS_CODE_OK) {
            switch ($this->_controller->getContentType()) {
            case \Simplight\Controller::CONTENT_TYPE_HTML:
                header('Content-Type: text/html; charset=UTF-8');
                break;
            case \Simplight\Controller::CONTENT_TYPE_JSON:
                header('Content-Type: application/json; charset=UTF-8');
                break;
            case \Simplight\Controller::CONTENT_TYPE_XML:
                header('Content-Type: text/xml; charset=UTF-8');
                break;
            case \Simplight\Controller::CONTENT_TYPE_CSV:
                $fileName = $this->request->getControllerName() . '_' . $this->request->getActionName() . '.csv';
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename=' . $fileName);
                header('Content-Transfer-Encoding: binary');
                break;
            default:
                break;
            }
            if (!is_null($bytes)) {
                header('Content-Length: ' . $bytes);
            }
        }
    }
}
