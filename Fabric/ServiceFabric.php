<?php

namespace Fabric;
use \Dropbox as dbx;

class ServiceFabric{

    const DROPBOX = 0;
    const GOOGLEDRIVE = 1;
    const BOX = 2;
    const ONEDRIVE = 3;

    public static function auth($type, $code, $config) {

        if ($code == 'code') {
            switch ($type) {
                case SELF::DROPBOX:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\DropBoxModel::auth($data,$config);
                    break;
                case SELF::GOOGLEDRIVE:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\GoogleDriveModel::auth($data,$config);
                    break;
                case SELF::BOX:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\BoxModel::auth($data,$config);
                    break;
                case SELF::ONEDRIVE:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\OneDriveModel::auth($data,$config);
                    break;
            }
        }elseif($code == 'access_token'){
            $result = self::getToken($type, $config);
            $data = array('service' => $type, 'token_data' => $result);
            return $data;

        }

    }


    public static function uploadFile($type, $access_token, $uploadFile, $fileName, $config) {

        $result = array('status' => 'error', 'msg' => 'Wrong service type');

        if(!isset($type)) {
            return $result;
        }

        switch($type){
            case SELF::DROPBOX:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                $result = array();
                try {
                    $result = \UploadModels\DropBoxModel::uploadFile($access_token, $uploadFile, $fileName, $config);
                }catch (dbx\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
            case SELF::GOOGLEDRIVE:
                $result = array();
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                try {
                    $result = \UploadModels\GoogleDriveModel::uploadFile($access_token, $uploadFile, $fileName,
                        $config);
                }catch(\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
            case SELF::BOX:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                $result = array();
                try {
                    $result = \UploadModels\BoxModel::uploadFile($access_token, $uploadFile, $fileName, $config);
                } catch(\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
            case SELF::ONEDRIVE:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                $result = array();
                try {
                    $result = \UploadModels\OneDriveModel::uploadFile($access_token, $uploadFile, $fileName, $config);
                } catch(\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
        }
        return $result;
    }

    public static function getToken($type, $config){

        $result = '';
        switch($type){
            case SELF::DROPBOX:
                $result = \UploadModels\DropBoxModel::getToken($config);
                break;
            case SELF::GOOGLEDRIVE:
                $result = \UploadModels\GoogleDriveModel::getToken($config);
                break;
            case SELF::BOX:
                $result = \UploadModels\BoxModel::getToken($config);
                break;
            case SELF::ONEDRIVE:
                $result = \UploadModels\OneDriveModel::getToken($config);
                break;
        }
        return $result;
    }

}