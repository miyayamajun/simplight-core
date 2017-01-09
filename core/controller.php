<?php

namespace Simplight;

/**
 * \Simplight\Controller
 * @description コントローラーの根幹となるクラス
 *
 * @author https://github.com/miyayamajun
 */
abstract class Controller
{
    const PARAM_TYPE_INT      = 1;
    const PARAM_TYPE_STRING   = 2;
    const PARAM_TYPE_EMAIL    = 3;
    const PARAM_TYPE_PASSWORD = 5;

    const CONTENT_TYPE_HTML = 1;
    const CONTENT_TYPE_JSON = 2;
    const CONTENT_TYPE_CSV  = 3;
    const CONTENT_TYPE_XML  = 4;

    protected $_app;
    protected $_request;
    protected $_view;
    protected $_tpl_path;
    protected $_content_type;
    protected $_render_flg = true;
    protected $_use_ssl = false;
    protected $_get;
    protected $_post;

    /**
     * コントローラ初期化
     * @param App Instance $app 
     * @param string $tpl_path デフォルトを使用しない場合、テンプレートファイルのパス
     *
     * @return void
     */
    public function __construct($app, $tpl_path = '')
    {
        if (!$app instanceof \Simplight\App) {
            throw new \Exception('Appインスタンス以外からの呼出に対応していません', STATUS_CODE_ERROR);
        }
        $this->_app          = $app;
        $this->_request      = $app->request;
        $this->_view         = $app->view;
        $this->_content_type = self::CONTENT_TYPE_HTML;
        $this->_setParamList();
        $this->setTemplatePath($tpl_path);
        $this->_defaultAssign();
    }

    /**
     * アクションの前に実行されるメソッド
     *
     * @return void
     */
    public function before_action_method() {}

    /**
     * アクションの後に実行されるメソッド
     *
     * @return void
     */
    public function after_action_method() {}

    /**
     * 指定が無い時に実行されるアクション
     *
     * @return void
     */
    public function root() {}

    /**
     * テンプレートのパスを指定する
     * @param string $path
     *
     * @return void
     */
    public function setTemplatePath($path = '')
    {
        if (!empty($path)) {
            $this->_tpl_path = $path;
        } else {
            $controller_name = $this->_request->getControllerName();
            $action_name     = $this->_request->getActionName();
            $this->_tpl_path = $controller_name . '/' . $action_name . '.tpl';
        }
    }

    /**
     * テンプレートのパスを取得する
     *
     * @return string app/view以降のパス
     */
    public function getTemplatePath()
    {
        return $this->_tpl_path;
    }

    /**
     * Content-Typeを指定する
     * @param int $type
     *
     * @return void
     */
    public function setContentType($type = self::CONTENT_TYPE_HTML)
    {
        $this->_content_type = $type;
    }

    /**
     * Content-Typeを取得する
     *
     * @return int
     */
    public function getContentType()
    {
        return $this->_content_type;
    }

    /**
     * smartyによるレンダリングを行うかどうか 
     *
     * @return boolean
     */
    public function isRender()
    {
        return $this->_render_flg;
    }

    /**
     * リダイレクト処理 呼び出されたらexitする
     * @param string $controller_name
     * @param string $action_name
     * @param array  $param_list
     *
     * @return void
     */
    protected function _redirect($controller_name, $action_name = 'root', $param_list = array())
    {
        $redirect_url  = $this->_use_ssl ? HTTPS_ROOT_URL : HTTP_ROOT_URL;
        $redirect_url .= "/{$controller_name}/{$action_name}";
        foreach ($param_list as $param) {
            $redirect_url .= "/{$param}";
        }
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect_url);
        exit;
    }

    /**
     * smartyにデータを渡す
     * @param mixed $key
     * @param mixed $param
     *
     * @return void
     */
    protected function _assign($key, $param)
    {
        $this->_view->assign($key, $param);
    }

    /**
     * パラメータをバリデートしてセットする
     *
     * @return void
     */
    protected function _setParamList()
    {
        $param_map   = $this->_request->getParamMap(); 
        $this->_get  = $param_map['get'];
        $this->_post = $param_map['post'];
    }

    /**
     * 基本的な変数をアサインする
     *
     * @return void
     */
    protected function _defaultAssign()
    {
        $this->_view->assign('controller', $this->_request->getControllerName());
        $this->_view->assign('action',     $this->_request->getActionName());
    }
}
