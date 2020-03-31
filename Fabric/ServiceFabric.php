<?php

namespace Fabric;

class ServiceFabric
{
    const DROPBOX = 0;
    const GOOGLEDRIVE = 1;
    const BOX = 2;
    const ONEDRIVE = 3;

    public static function auth($type, $code, $config) {

        if ($code == 'code') {
            switch ($type) {
                case self::DROPBOX:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\DropBoxModel::auth($data,$config);
                case self::GOOGLEDRIVE:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\GoogleDriveModel::auth($data,$config);
                case self::BOX:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\BoxModel::auth($data,$config);
                case self::ONEDRIVE:
                    $data = \HttpReceiver\HttpReceiver::get('userId','int');
                    return \UploadModels\OneDriveModel::auth($data,$config);
            }
        }elseif($code == 'access_token'){
            $result = self::getToken($type, $config);
            return array('service' => $type, 'token_data' => $result);
        }
    }

    public static function getEmail($type, $accessToken, $config)
    {
        if (!isset($accessToken)) {
            return array('status' => 'error', 'msg' => 'deniedByUser');
        }

        switch ($type) {
            case self::GOOGLEDRIVE:
                $result = \UploadModels\GoogleDriveModel::getEmail($accessToken, $config);
                break;
            case self::ONEDRIVE:
                $result =\UploadModels\OneDriveModel::getEmail($accessToken, $config);
                break;
            case self::BOX:
                $result =\UploadModels\BoxModel::getEmail($accessToken, $config);
                break;
            default:
                throw new \RuntimeException('Cannot fetch profile.');
        }

        return $result;
    }


    public static function uploadFile($type, $access_token, $uploadFile, $fileName, $config) {

        $result = array('status' => 'error', 'msg' => 'Wrong service type');

        if(!isset($type)) {
            return $result;
        }

        switch($type){
            case self::DROPBOX:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                try {
                    $result = \UploadModels\DropBoxModel::uploadFile($access_token, $uploadFile, $fileName, $config);
                }catch (\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
            case self::GOOGLEDRIVE:
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
            case self::BOX:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
                try {
                    $result = \UploadModels\BoxModel::uploadFile($access_token, $uploadFile, $fileName, $config);
                } catch(\Exception $e){
                    $result = array('status' => 'error', 'msg' => 'Cloud Error');
                }
                break;
            case self::ONEDRIVE:
                if(!isset($access_token)) {
                    return array('status' => 'error', 'msg' => 'deniedByUser');
                }
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
            case self::DROPBOX:
                $result = \UploadModels\DropBoxModel::getToken($config);
                break;
            case self::GOOGLEDRIVE:
                $result = \UploadModels\GoogleDriveModel::getToken($config);
                break;
            case self::BOX:
                $result = \UploadModels\BoxModel::getToken($config);
                break;
            case self::ONEDRIVE:
                $result = \UploadModels\OneDriveModel::getToken($config);
                break;
        }
        return $result;
    }
}
