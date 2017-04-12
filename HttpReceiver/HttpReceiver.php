<?php

namespace HttpReceiver;

class HttpReceiver{

    public static function get($name, $type){

        switch($type){
            case 'int':
                return intval($_REQUEST[$name]);
            case 'string':
                return htmlspecialchars(strip_tags($_REQUEST[$name]));
        }
        return '';
    }

}