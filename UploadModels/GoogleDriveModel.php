<?php
namespace UploadModels;

class GoogleDriveModel implements \Interfaces\UploadServiceInterface {

    public static function auth($state, $config) {
        $client = self::getGoogleClient($config);
        $client->setState($state);
        $client->setConfig('access_type', 'offline');
        $auth_url = $client->createAuthUrl();
        return filter_var($auth_url, FILTER_SANITIZE_URL);
    }

    public static function uploadFile($access_token, $uploadFile, $fileName, $config) {
        if (!isset($access_token)) {
            return false;
        }

        $userId = \HttpReceiver\HttpReceiver::get('userId', 'string');
        $client = self::getGoogleClient($config);
        try {
            $access_token = (array)$access_token;
            $client->setAccessToken($access_token);
        }catch (\InvalidArgumentException $e){

            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }

        $service = new \Google\Service\Drive($client);

        $folderInfo = self::getFolder($access_token, $config);
        $id = 0;
        if($folderInfo['status'] === 'ok'){
            $id = $folderInfo['id'];
        }else{
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }

        $extension = self::getExtension($uploadFile);

        if (!isset($fileName) || strlen($fileName) == 0 || $fileName == '0') {
            $tmp = explode('/', $uploadFile);
            $fileName = $tmp[sizeof($tmp) - 1];
        }else{
            $fileName .= '.'.$extension;
        }

        //Insert a file
        $file = new \Google\Service\Drive\DriveFile(array(
            'name' => $fileName,
            'parents' => array($id)
        ));

        $data = file_get_contents($uploadFile);

        try {
            $createdFile = $service->files->create($file, array(
                'data' => $data,
                'mimeType' => self::getMime($extension),
                'uploadType' => 'multipart',
                'fields' => 'id'));
        } catch(\Exception $e) {
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }

        if (isset($createdFile) && isset($createdFile['id']) && strlen($createdFile['id']) > 0) {
            return array('status' => 'ok');
        } else {
            return array('status' => 'error', 'msg' => 'refreshToken', 'url' => self::auth($userId, $config));
        }
    }


    public static function getToken($config) {

        $client = self::getGoogleClient($config);
        $code = \HttpReceiver\HttpReceiver::get('code','string');
        $client->authenticate($code);
        return $client->getAccessToken();

    }

    private static function getGoogleClient($config) {
        $client = new \Google\Client();

        $config = self::getGoogleConfig($config);

        $client->setAuthConfigFile($config);

        $userId = \HttpReceiver\HttpReceiver::get('userId', 'int');

        if(!isset($userId)){
            $userId = \HttpReceiver\HttpReceiver::get('state', 'int');
        }

        $client->setRedirectUri($config['GOOGLEDRIVE_REDIRECT2']);
        $client->addScope(\Google\Service\Drive::DRIVE);
        return $client;
    }

    private static function getFolder($access_token, $config) {
        $client = self::getGoogleClient($config);
        $client->setAccessToken($access_token);
        $service = new \Google\Service\Drive($client);

        $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and 'root' in parents and trashed=false";
        try {
            $data = $service->files->listFiles($parameters);
        }catch(\Exception $e){
            return array('status' => 'error', 'data' => $e->getMessage());
        }
        $files = $data->getFiles();
        foreach($files as $file) {
            if($file['name'] == $config['SAVE_FOLDER']){
                return array('status' => 'ok', 'id' => $file['id']);
            }
        }

        $fileMetadata = new \Google\Service\Drive\DriveFile(array(
            'name' => $config['SAVE_FOLDER'],
            'mimeType' => 'application/vnd.google-apps.folder'));
        $file = $service->files->create($fileMetadata, array(
            'fields' => 'id'));

        return array('status' => 'ok', 'id' => $file->id);
    }

    public static function getGoogleConfig($config){
        $data['client_id'] = $config['GOOGLEDRIVE_CLIENTID'];
        $data['project_id'] = $config['GOOGLEDRIVE_PROJECTID'];
        $data['auth_uri'] = $config['GOOGLEDRIVE_AUTHURL'];
        $data['token_uri'] = $config['GOOGLEDRIVE_TOKEN_URL'];
        $data['auth_provider_x509_cert_url'] = $config['GOOGLEDRIVE_AUTHPROV'];
        $data['client_secret'] = $config['GOOGLEDRIVE_CLIENTSECRET'];
        $data['redirect_uris'][] = $config['GOOGLEDRIVE_REDIRECT2'];

        $config['installed'] = $data;
        return $config;
    }

    private static function getExtension($path){
        $ext = pathinfo($path,PATHINFO_EXTENSION);
        if(strpos($path,'&') > -1){
            $ext = reset(explode('&',$ext));
        }
        return $ext;
    }

    private static function getMime($ext){
        switch($ext){
            case 'pdf':
                return 'application/pdf';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'xls':
            case 'xlt':
            case 'xla':
                return 'application/vnd.ms-excel';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'txt':
                return 'text/plain';
            case 'doc':
            case 'dot':
                return 'application/msword';
            case 'pptx':
                return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
            case 'ppt':
            case 'pot':
            case 'pps':
            case 'ppa':
                return 'application/vnd.ms-powerpoint';

        }
    }

}