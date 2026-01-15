<?php

namespace UploadModels;

use Exception;
use HttpReceiver\HttpReceiver;
use Interfaces\UploadServiceInterface;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\Exceptions\DropboxClientException;

class DropBoxModel implements UploadServiceInterface
{
    const CONFIG_CLIENT_ID = 'DROPBOX_KEY';
    const CONFIG_CLIENT_SECRET = 'DROPBOX_SECRET';
    const CONFIG_REDIRECT_URL = 'DROPBOX_REDIRECT_URI';

    /**
     * @var DropboxApp
     */
    protected $app;

    /**
     * @var Dropbox
     */
    private $service;
    /**
     * @var string
     */
    protected $redirectUrl;
    /**
     * @var string
     */
    private $clientId;
    /**
     * @var string
     */
    private $clientSecret;
    /**
     * @var null|string
     */
    private $accessToken;

    /**
     * DropBoxModel constructor.
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUrl
     * @param string|null $accessToken
     */
    private function __construct($clientId, $clientSecret, $redirectUrl, $accessToken = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl = $redirectUrl;
        $this->accessToken = $accessToken;
    }

    private function init(array $serviceConfig = []) {
        $this->app = new DropboxApp($this->clientId, $this->clientSecret, $this->accessToken);
        $this->service = new Dropbox($this->app, $serviceConfig);
    }

    /**
     * @param int $userId
     * @param array $config
     * @return string
     */
    public static function auth($userId, $config)
    {
        return self::create($config)->getAuthUrl($userId);
    }

    /**
     * @param array $config
     * @return string
     */
    public static function getToken($config)
    {
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            return '';
        }
        $code = $_GET['code'];
        $state = $_GET['state'];

        try {
            return self::create($config)->getAccessToken($code, $state);
        } catch (DropboxClientException $exception) {
            return '';
        }
    }

    public static function updateFile($access_token, $fileUrl, $fileNameWithoutExtension, $fileId, $config)
    {
        return ['status' => 'error', 'msg' => 'Not implemented'];
    }

    /**
     * @param string $access_token
     * @param string $uploadFile
     * @param string $fileName
     * @param array $config
     * @return array
     */
    public static function uploadFile($access_token, $uploadFile, $fileName, $config)
    {
        if (empty($access_token)) {
            return [
                'status' => 'error',
                'msg' => 'refreshToken',
                'url' => self::auth(HttpReceiver::get('userId', 'int'), $config)
            ];
        }

        if (!file_exists($uploadFile)) {
            return ['status' => 'error', 'fileNotExist'];
        }
        if (empty($fileName) && $fileName !== '0') {
            $remoteFilename = self::generateRemoteFilename($uploadFile);
        } else {
            $remoteFilename = $fileName .'.' . self::getExtension($uploadFile);
        }
        $remotePath = "/" . $config['SAVE_FOLDER'] . "/" . $remoteFilename;

        try {
            $fileMetadata = self::create($config, $access_token)->upload($uploadFile, $remotePath);
            $fileId = $fileMetadata->getId();
            return ['status' => 'ok', 'file_id' => $fileId];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'msg' => 'refreshToken',
                'url' => self::create($config, $access_token)->getAuthUrl(HttpReceiver::get('userId', 'int')),
            ];
        }
    }

    /**
     * @param int $userId
     * @return string
     */
    private function getAuthUrl($userId)
    {
        return $this->service->getAuthHelper()->getAuthUrl($this->redirectUrl, [], $userId);
    }

    /**
     * @param string $code
     * @param string $state
     * @return string
     */
    private function getAccessToken($code, $state)
    {
        return $this->service->getAuthHelper()->getAccessToken($code, $state, $this->redirectUrl)->getToken();
    }

    /**
     * @param string $localFilename
     * @param string $remoteFilename
     * @return mixed
     */

    private function upload($localFilename, $remoteFilename)
    {
        return $this->service->upload($localFilename, $remoteFilename, ['autorename' => true]);
    }

    /**
     * @param string $path
     * @return string
     */
    private static function getExtension($path)
    {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (strpos($path, '&') > -1) {
            $ext = reset(explode('&', $ext));
        }
        return $ext;
    }

    /**
     * @param string $access_token
     * @param array $config
     * @return array
     */
    public static function getUsername($access_token, $config)
    {
        if (!isset($access_token)) {
            return ['status' => 'error', 'msg' => 'deniedByUser'];
        }

        try {
            $account = self::create($config, $access_token)->getCurrentAccount();
            $emailAddress = $account->getEmail();
            
            return ['status' => 'ok', 'username' => $emailAddress];
        } catch (Exception $e) {
            return ['status' => 'error', 'msg' => 'Cloud Error', 'details' => $e->getMessage()];
        }
    }

    /**
     * @return mixed
     */
    private function getCurrentAccount()
    {
        return $this->service->getCurrentAccount();
    }

    /**
     * @param string $localPath
     * @return string
     */
    private static function generateRemoteFilename($localPath)
    {
        $parts = explode('/', $localPath);
        return end($parts);
    }

    /**
     * @param array $config
     * @param string|null $accessToken
     * @return self
     */
    private static function create($config, $accessToken = null)
    {
        $self = new self(
            $config[self::CONFIG_CLIENT_ID],
            $config[self::CONFIG_CLIENT_SECRET],
            $config[self::CONFIG_REDIRECT_URL],
            $accessToken
        );
        /** @see \Kunnu\Dropbox\Dropbox::__construct() */
        $serviceConfig = array_intersect_key($config, [
            'http_client_handler' => null,
            'random_string_generator' => null,
            'persistent_data_store' => null
        ]);
        $self->init($serviceConfig);

        return $self;
    }
}
