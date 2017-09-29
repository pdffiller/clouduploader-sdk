<?php

namespace HttpReceiver;

class HttpReceiver
{

    public static function get($name, $type)
    {
        $data = array_key_exists($name, $_REQUEST) ? $_REQUEST[$name] : null;
        switch ($type) {
            case 'int':
                return intval($data);
            case 'string':
                return htmlspecialchars(strip_tags($data));
        }
        return '';
    }

}
