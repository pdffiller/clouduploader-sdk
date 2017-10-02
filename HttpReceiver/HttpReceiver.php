<?php

namespace HttpReceiver;

class HttpReceiver
{

    public static function get($name, $type)
    {
        $data = isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
        switch ($type) {
            case 'int':
                return intval($data);
            case 'string':
                return htmlspecialchars(strip_tags($data));
        }
        return '';
    }

}
