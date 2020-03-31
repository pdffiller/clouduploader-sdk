<?php
namespace Interfaces;

interface UploadServiceInterface{

    public static function auth($state,$config);
    public static function uploadFile($access_token, $uploadFile, $fileName, $config);
    public static function getToken($config);
    public static function getEmail($accessToken, $config);
}