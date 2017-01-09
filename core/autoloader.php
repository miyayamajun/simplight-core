<?php

namespace Simplight;

/**
 * \Simplight\Autoloader
 * @description 登録したディレクトリのファイルを自動で読み込みrequireする 
 *
 * @author https://github.com/miyayamajun
 */
final class Autoloader
{
    private $_app;
    private $_class_map;

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
    }

    /**
     * regist: 引数のディレクトリ以下のファイルを自動読込対象として登録する
     * @access public
     * @param string $target_dir
     *
     * @return void
     */
    public function regist($target_dir)
    {
        /**
         * ディレクトリが存在しなければnull
         */
        if (!is_dir($target_dir)) {
            return;
        }
        
        $iterator = new \RecursiveDirectoryIterator($target_dir);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                $class_name = '';
                $file_path  = $fileinfo->getPathname();
                if (!preg_match('/\.php$/', $file_path)) {
                    continue;
                }

                /**
                 * ディレクトリ階層を配列化してクラス名を自動生成する
                 * 生成したクラス名はロード時のキーになる
                 */
                $class_name_list = explode('/', 'simplight/' . preg_replace('/\.(.*)$/', '', str_replace(array(APP_DIR . '/', SRC_DIR . '/'), '', $file_path)));
                foreach ($class_name_list as $class_name_node) {
                    if (in_array($class_name_node, array('src', 'core'))) {
                        continue;
                    }
                    if (!empty($class_name)) {
                        $class_name .= '\\';
                    }
                    foreach (explode('_', $class_name_node) as $class_name_sub_node) {
                        $class_name .= ucfirst($class_name_sub_node);
                    }
                }
                $this->_class_map[$class_name] = $file_path;
            }
        }
        spl_autoload_register(array($this, '_load'));
    }

    /**
     * _load: splのautoloaderから呼び出されるメソッド
     * @access private
     * @param  string $class_name
     *
     * @return void
     */
    private function _load($class_name)
    {
        if (!isset($this->_class_map[$class_name])) {
            require_once CORE_DIR . '/exception.php';
            if (preg_match('/Controller/', $class_name)) {
                $exception = new \Simplight\Exception('ページが見つかりませんでした<br>URLを確かめてください', 'ページが見つかりません', STATUS_CODE_NOT_FOUND);
                $this->_app->setError($exception);
            } else {
                $exception = new \Simplight\Exception($class_name . 'が見つかりません', 'エラー', STATUS_CODE_NOT_FOUND);
                $this->_app->setError($exception);
            }
            exit;
        }
        require_once $this->_class_map[$class_name];
    }
}
