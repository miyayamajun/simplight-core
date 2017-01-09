<?php

namespace Simplight;

/**
 * \Simplight\Exception
 * @description 例外処理
 *
 * @author https://github.com
 */
class Exception extends \Exception
{
    private $_title = '';

    /**
     * コンストラクタ
     * @param string $message エラーメッセージ
     * @param string $title
     * @param int    $error_code
     */
    public function __construct($message, $title = '', $error_code = STATUS_CODE_ERROR)
    {
        parent::__construct($message, $error_code);
        $this->_title = $title;
    }

    /**
     * エラーページのタイトルを取得する
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->_title;
    }
}
