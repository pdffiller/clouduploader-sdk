<?php
namespace UploadModels;

use Krizalys\Onedrive\Client;

class OneDriveModel implements \Interfaces\UploadServiceInterface {

    public static function auth($state, $config) {
        $client = self::getOneDriveClient($config);
        $url = $client->getLogInUrl(array(
            'wl.signin',
            'wl.basic',
            'wl.contacts_skydrive',
            'wl.skydrive_update'
        ), $config['ONEDRIVE_CALLBACK_URI'], array('state' => $state));
        return $url.'&state='.$state;
    }

    public static function getEmail($token, $config)
    {
        $client = self::getOneDriveClient($config);
        return $client->fetchAccountInfo()->name;
    }

    public static function uploadFile($access_token, $uploadFile, $fileName, $config) {
        $data = json_decode($access_token);
        $userId = \HttpReceiver\HttpReceiver::get('userId', 'string');
        if(isset($data->data->error)){
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }
        $folderId = self::getFolder($access_token, $config);
        if(empty($folderId)){
            return array('status' => 'error', 'msg' => 'Unknown cloud error while creating folder');
        }


        if (!isset($fileName) || strlen($fileName) == 0 || $fileName == '0') {
            $tmp = explode('/', $uploadFile);
            $fileName = $tmp[sizeof($tmp) - 1];
        }else{
            $fileName .= '_'.time().'.'.self::getExtension($uploadFile);
        }

        $data = json_decode($access_token);
        $driveData = new \stdClass();
        $driveData->redirect_uri = $config['ONEDRIVE_CALLBACK_URI'];
        $driveData->token = $data;
        $array['onedrive.client.state'] = $driveData;
        $client = self::getOneDriveClient($config, $array);
        $parent      = $client->fetchObject($folderId);
        try {
            $parent->createFile($fileName, file_get_contents($uploadFile));
        }catch(\Exception $e){
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }
        return array('status' => 'ok');

    }


    public static function getToken($config) {

        $array = array();
        $driveData = new \stdClass();
        $driveData->redirect_uri = $config['ONEDRIVE_CALLBACK_URI'];
        $driveData->token = '';
        $array['onedrive.client.state'] = $driveData;

        $client = self::getOneDriveClient($config, $array);
        $code = \HttpReceiver\HttpReceiver::get('code','string');
        $client->obtainAccessToken($config['ONEDRIVE_CLIENT_SECRET'], $code);
        $info = $client->getState();
        $access_token = json_encode($info->token, JSON_UNESCAPED_SLASHES);
        return $access_token;
    }

    private static function getOneDriveClient($config, $state = false) {

        if(!$state) {
            return new Client(array(
                'client_id' => $config['ONEDRIVE_CLIENT_ID']
            ));
        }else{

            return new Client(array(
                'client_id' => $config['ONEDRIVE_CLIENT_ID'],
                'state'     => $state['onedrive.client.state']
            ));
        }
    }

    private static function getFolder($access_token, $config) {
        $data = json_decode($access_token);
        $driveData = new \stdClass();
        $driveData->redirect_uri = $config['ONEDRIVE_CALLBACK_URI'];
        $driveData->token = $data;
        $array['onedrive.client.state'] = $driveData;
        $client = self::getOneDriveClient($config, $array);
        $parentId    = null;
        $name        = $config['SAVE_FOLDER'];
        $description = 'Test description';
        $parent      = $client->fetchObject($parentId);
        try {
            $folder = $parent->createFolder($name, $description);
            return self::getFolder($access_token, $config);
        }catch (\Exception $e){
            //Folder exists
            $objs = $parent->fetchObjects(null);

            foreach($objs as $obj){
                if($obj->getName() === $config['SAVE_FOLDER']){
                    return $obj->getId();
                }
            }
            return null;
        }

        return array('status' => 'ok', 'id' => $folder->getId());
    }

    public static function getOneDriveConfig($config){
        $data['client_id'] = $config['ONEDRIVE_CLIENT_ID'];
        $data['project_secret'] = $config['ONEDRIVE_CLIENT_SECRET'];
        $data['redirect_uri'] = $config['ONEDRIVE_CALLBACK_URI'];
        return $config;
    }

    private static function getExtension($path){
        $ext = pathinfo($path,PATHINFO_EXTENSION);
        if(strpos($path,'&') > -1){
            $ext = reset(explode('&',$ext));
        }
        return $ext;
    }

}
