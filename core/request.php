<?php

namespace Simplight;

/**
 * Request
 * クライアントからのリクエストメッセージを処理するクラス
 *
 * @author https://github.com/miyayamajun
 */
final class Request
{
    const METHOD_GET    = 0;
    const METHOD_POST   = 1;
    const METHOD_PUT    = 2;
    const METHOD_DELETE = 3;

    const CONTROLLER_KEY   = 0;
    const ACTION_KEY       = 1;
    const PARAMS_START_KEY = 2; 
    
    const DEFAULT_CONTROLLER_NAME = 'root';
    const DEFAULT_ACTION_NAME     = 'root';

    private $_request_uri;
    private $_controller_name;
    private $_action_name;
    private $_param_map;
    private $_method_type;

    /**
     * コンストラクタ
     * @param AppObject $app
     *
     * @return RequestObject
     */
    public function __construct($app)
    {
        if (!$app instanceof \Simplight\App) {
            throw new \Exception('Appインスタンス以外からの呼出に対応していません', STATUS_CODE_ERROR);
        }
        $this->_setup();
    }

    /**
     * インスタンスメンバを取得する
     *
     * @return mixed
     */
    // getControllerName: コントローラ名取得
    public function getControllerName()
    {
        return $this->_controller_name;
    }
    
    // getControllerName: コントローラのクラス名取得
    public function getControllerClassName()
    {
        return '\\Simplight\\Controller\\' . ucfirst($this->_controller_name);
    }
    
    // getActionName: アクション名取得
    public function getActionName()
    {
        return $this->_action_name;
    }
    
    // getMethodType: メソッドタイプ取得
    public function getMethodType()
    {
        return $this->_method_type;
    }
    
    // getParamMap: パラメータ配列取得
    public function getParamMap()
    {
        return $this->_param_map;
    }

    /**
     * URIから必要なパラメータをセットする
     *
     * @return void
     */
    private function _setup()
    {
        $this->_setRequestUri();
        $this->_setControllerName();
        $this->_setActionName();
        $this->_setExtraParams();
        $this->_setHttpMethod();
    }

    /**
     * URIから配列を生成する(ルートの'/'は排除する)
     *
     * @return void
     */  
    private function _setRequestUri()
    {
        $request_uri_str    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $uri_params_str     = strstr($request_uri_str, '?');
        $request_uri        = str_replace($uri_params_str, '', $request_uri_str);
        $request_uri_list   = explode('/', htmlspecialchars($request_uri, ENT_QUOTES));
        $this->_request_uri = array_slice($request_uri_list, 1);
    }

    /**
     * URIからコントローラ名をセットする
     *
     * @return void
     */
    private function _setControllerName()
    {
        $controller_name = self::DEFAULT_CONTROLLER_NAME;
        if (!empty($this->_request_uri) && !empty($this->_request_uri[self::CONTROLLER_KEY])) {
            $controller_name = $this->_request_uri[self::CONTROLLER_KEY];
        }
        $this->_controller_name = $controller_name;
    }

    /**
     * URIからアクション名をセットする
     *
     * @return void
     */
    private function _setActionName()
    {
        $action_name = self::DEFAULT_ACTION_NAME;
        if (!empty($this->_request_uri) && !empty($this->_request_uri[self::ACTION_KEY])) {
            $action_name = $this->_request_uri[self::ACTION_KEY];
        }
        $this->_action_name = $action_name;
    }
    
    /**
     * リクエストメッセージからパラメータをセットする
     *
     * @return void
     */
    private function _setExtraParams()
    {
        $param_map = array(
            'get'  => array(),
            'post' => array(),
        );
        foreach ($_GET as $key => $param) {
            $get_key   = htmlspecialchars($key,   ENT_QUOTES);
            $get_param = htmlspecialchars($param, ENT_QUOTES);
            $param_map['get'][$get_key] = $get_param;
        }
        foreach ($_POST as $key => $param) {
            $post_key   = htmlspecialchars($key,   ENT_QUOTES);
            $post_param = htmlspecialchars($param, ENT_QUOTES);
            $param_map['post'][$post_key] = $post_param;
        }
        $this->_param_map = $param_map;
    }

    /**
     * REQUEST_METHOD or _hidden_methodからリクエストメソッドをセットする
     *
     * @return void
     */
    private function _setHttpMethod()
    {
        $method_type    = null;
        $request_method = !isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        
        if ('GET' === $request_method) {
            $method_type = self::METHOD_GET;
        } else if ('PUT' === $request_method) {
            $method_type = self::METHOD_PUT;
        } else if ('DELETE' === $request_method) {
            $method_type = self::METHOD_DELETE;
        } else if ('POST' === $request_method) {
            $method_type = self::METHOD_POST;
            if (isset($this->_params_array['post']['_hidden_method'])) {
                if (in_array($this->_params_array['post']['_hidden_method'], array('put', 'PUT'), true)) {
                    $method_type = self::METHOD_PUT;
                } else if (in_array($this->_params_array['post']['_hidden_method'], array('DELETE', 'delete'), true)) {
                    $method_type = self::METHOD_DELETE;
                }
            }
        }
        if (is_null($method_type)) {
            throw new \Exception('予期せぬHTTPメソッドです', STATUS_CODE_ERROR);
        }
        $this->_method_type = $method_type;
    }
}
