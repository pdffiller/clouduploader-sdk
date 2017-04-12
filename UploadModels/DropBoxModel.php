<?php

namespace UploadModels;

use \Dropbox as dbx;
use Dropbox\Exception;
use HttpReceiver\HttpRecieiver;


class DropBoxModel implements \Interfaces\UploadServiceInterface{

    public static function auth($state, $config) {

        $authorizeUrl = self::getDropBoxAuth($config)->start(\HttpReceiver\HttpReceiver::get('userId','string'));
        return $authorizeUrl;
    }

    public static function getToken($config) {
        try {
            list($accessToken, $userId, $urlState) = self::getDropBoxAuth($config)->finish($_GET);
            if (isset($accessToken) && strlen($accessToken) > 0) {
                return $accessToken;
            }
        }catch(dbx\Exception_BadRequest $e){

        }
        return '';
    }


    public static function uploadFile($access_token, $uploadFile, $fileName, $config) {
        if(!isset($access_token)){
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth(\HttpReceiver\HttpReceiver::get('userId','int'), $config));
        }

        if(!file_exists($uploadFile)) {
            return array('status' => 'error', 'fileNotExist');
        }


        $dbxClient = new dbx\Client($access_token, "PHP-Example/1.0");


        $f = fopen($uploadFile, "rb");
        try {
            if (!isset($fileName) || strlen($fileName) == 0 || $fileName == '0') {
                $tmp = explode('/', $uploadFile);
                $fileName = $tmp[sizeof($tmp) - 1];
            }else{
                $fileName .= '.'.self::getExtension($uploadFile);
            }

            $result = $dbxClient->uploadFile("/".$config['SAVE_FOLDER']."/". $fileName, dbx\WriteMode::add(), $f);
        }catch(Exception $e){
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth(\HttpReceiver\HttpReceiver::get('userId','int'), $config));
        }

        fclose($f);
        if(!isset($result) || !isset($result['size'])){
            return array('status' => 'error', 'msg' => 'refreshToken');
        }else {
            return array('status' => 'ok');
        }

    }


    private static function getDropBoxAuth($config) {

        $data = array('key' => $config['DROPBOX_KEY'], 'secret' => $config['DROPBOX_SECRET']);

        $appInfo = dbx\AppInfo::loadFromJson($data);
        $clientIdentifier = "my-app/1.0";
        $redirectUri = $config['DROPBOX_REDIRECT_URI'];
        $csrfTokenStore = new dbx\ArrayEntryStore($_ENV, 'dropbox-auth-csrf-token');

        return new dbx\WebAuth($appInfo, $clientIdentifier, $redirectUri, $csrfTokenStore);
    }

    private static function getExtension($path){
        $ext = pathinfo($path,PATHINFO_EXTENSION);
        if(strpos($path,'&') > -1){
            $ext = reset(explode('&',$ext));
        }
        return $ext;
    }

}