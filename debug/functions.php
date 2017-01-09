<?php

/**
 * php.logに出力する
 * @param mixed $data
 */ 
function myLog($data)
{
    error_log(print_r($data, true));      
}

/**
 * データを展開する
 * @param mixed $data
 */
function dump($data)
{
    echo '<pre>';
    var_export($data);
    echo '</pre>';
}
