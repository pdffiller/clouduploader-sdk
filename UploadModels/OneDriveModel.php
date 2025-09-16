<?php
namespace UploadModels;

use Krizalys\Onedrive\Client;
use Krizalys\Onedrive\Onedrive;

class OneDriveModel implements \Interfaces\UploadServiceInterface {

    public static function auth($state, $config) {
        $client = self::getOneDriveClient($config);
        $url = $client->getLogInUrl(array(
            'files.read',
            'files.read.all',
            'files.readwrite',
            'files.readwrite.all',
            'offline_access',
        ), $config['ONEDRIVE_CALLBACK_URI'], array('state' => $state));
        return $url.'&state='.$state;
    }

    /**
     * @throws \Krizalys\Onedrive\Exception\ConflictException
     * @throws \Exception
     */
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
        $parent = $client->getDriveItemById($folderId['id']);
        try {
            $parent->upload($fileName, file_get_contents($uploadFile));
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

    private static function getOneDriveClient($config, $state = false): Client
    {
        $options = [];
        if ($state) {
            $options['state'] = $state['onedrive.client.state'];
        }
        return Onedrive::client($config['ONEDRIVE_CLIENT_ID'], $options);
    }

    /**
     * @throws \Krizalys\Onedrive\Exception\ConflictException
     * @throws \Exception
     */
    private static function getFolder($access_token, $config): array
    {
        $data = json_decode($access_token);
        $driveData = new \stdClass();
        $driveData->redirect_uri = $config['ONEDRIVE_CALLBACK_URI'];
        $driveData->token = $data;
        $array['onedrive.client.state'] = $driveData;
        $client = self::getOneDriveClient($config, $array);
        $name        = $config['SAVE_FOLDER'];

        try {
            $folder = $client->getDriveItemByPath("/{$name}");
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $root = $client->getRoot();
                $folder = $root->createFolder($name, ['description' => 'Test description']);
            } else {
                throw $e;
            }
        }

        return ['status' => 'ok', 'id' => $folder->id];
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
