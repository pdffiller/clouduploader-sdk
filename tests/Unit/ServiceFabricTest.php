<?php

declare(strict_types=1);

namespace Tests\Unit;

use Fabric\ServiceFabric;
use Tests\Support\Doubles\ArrayPersistentDataStore;
use Tests\Support\Doubles\FakeDropboxHttpClient;
use Tests\Support\Doubles\ThrowingConfig;
use Tests\Support\SuperglobalTestCase;

/**
 * ServiceFabric is pure routing: pick a model, guard the arguments, translate
 * thrown exceptions into an error array. Each of those branches is asserted
 * here without any network access.
 *
 * @covers \Fabric\ServiceFabric
 */
class ServiceFabricTest extends SuperglobalTestCase
{
    private const UNKNOWN_SERVICE = 99;

    /**
     * @var FakeDropboxHttpClient
     */
    private $http;

    protected function _before()
    {
        parent::_before();

        $this->http = new FakeDropboxHttpClient();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'DROPBOX_KEY' => 'dropbox-client-id',
            'DROPBOX_SECRET' => 'dropbox-client-secret',
            'DROPBOX_REDIRECT_URI' => 'https://app.example.com/dropbox/callback',
            'BOX_CLIENT_ID' => 'box-client-id',
            'BOX_CLIENT_SECRET' => 'box-client-secret',
            'BOX_REDIRECT_URI' => 'https://app.example.com/box/callback',
            'GOOGLEDRIVE_CLIENTID' => 'gd-client-id',
            'GOOGLEDRIVE_PROJECTID' => 'gd-project',
            'GOOGLEDRIVE_AUTHURL' => 'https://accounts.google.com/o/oauth2/auth',
            'GOOGLEDRIVE_TOKEN_URL' => 'https://oauth2.googleapis.com/token',
            'GOOGLEDRIVE_AUTHPROV' => 'https://www.googleapis.com/oauth2/v1/certs',
            'GOOGLEDRIVE_CLIENTSECRET' => 'gd-secret',
            'GOOGLEDRIVE_REDIRECT2' => 'https://app.example.com/google/callback',
            'SAVE_FOLDER' => 'PDFfiller',
            'http_client_handler' => $this->http,
            'persistent_data_store' => new ArrayPersistentDataStore(),
        ];
    }

    private function fixture(): string
    {
        return codecept_data_dir('fixtures/sample.txt');
    }

    public function testServiceConstants(): void
    {
        $this->assertSame(0, ServiceFabric::DROPBOX);
        $this->assertSame(1, ServiceFabric::GOOGLEDRIVE);
        $this->assertSame(2, ServiceFabric::BOX);
    }

    public function testAuthReturnsNullForUnknownGrant(): void
    {
        $this->assertNull(ServiceFabric::auth(ServiceFabric::DROPBOX, 'refresh_token', $this->config()));
    }

    public function testAuthReturnsNullForUnknownServiceOnCodeGrant(): void
    {
        $this->assertNull(ServiceFabric::auth(self::UNKNOWN_SERVICE, 'code', $this->config()));
    }

    public function testAuthRoutesCodeGrantToDropbox(): void
    {
        $this->givenRequest(['userId' => '11']);

        $url = ServiceFabric::auth(ServiceFabric::DROPBOX, 'code', $this->config());

        $this->assertStringContainsString('dropbox.com/oauth2/authorize', $url);
        $this->assertStringContainsString('%7C11', $url);
    }

    public function testAuthRoutesCodeGrantToGoogleDrive(): void
    {
        $this->givenRequest(['userId' => '12']);

        $url = ServiceFabric::auth(ServiceFabric::GOOGLEDRIVE, 'code', $this->config());

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('state=12', $url);
    }

    public function testAuthRoutesCodeGrantToBox(): void
    {
        $this->givenRequest(['userId' => '13']);

        $url = ServiceFabric::auth(ServiceFabric::BOX, 'code', $this->config());

        $this->assertStringContainsString('box.com/api/oauth2/authorize', $url);
        $this->assertStringEndsWith('&state=13', $url);
    }

    public function testAuthWrapsTokenGrantIntoServiceEnvelope(): void
    {
        $result = ServiceFabric::auth(self::UNKNOWN_SERVICE, 'access_token', $this->config());

        $this->assertSame(['service' => self::UNKNOWN_SERVICE, 'token_data' => ''], $result);
    }

    public function testAuthTokenGrantDelegatesToDropboxModel(): void
    {
        $result = ServiceFabric::auth(ServiceFabric::DROPBOX, 'access_token', $this->config());

        $this->assertSame(ServiceFabric::DROPBOX, $result['service']);
        $this->assertSame('', $result['token_data']);
        $this->assertSame(0, $this->http->requestCount());
    }

    public function testGetTokenReturnsEmptyStringForUnknownService(): void
    {
        $this->assertSame('', ServiceFabric::getToken(self::UNKNOWN_SERVICE, $this->config()));
    }

    public function testGetTokenRoutesToDropboxModel(): void
    {
        $this->assertSame('', ServiceFabric::getToken(ServiceFabric::DROPBOX, $this->config()));
    }

    public function testUploadFileRejectsNullService(): void
    {
        $result = ServiceFabric::uploadFile(null, 'token', $this->fixture(), 'report', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    public function testUploadFileRejectsUnknownService(): void
    {
        $result = ServiceFabric::uploadFile(self::UNKNOWN_SERVICE, 'token', $this->fixture(), 'r', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testUploadFileWithoutAccessTokenIsDeniedByUser(int $service): void
    {
        $result = ServiceFabric::uploadFile($service, null, $this->fixture(), 'report', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    /**
     * @dataProvider serviceProvider
     */
    public function testUploadFileTranslatesModelExceptionsIntoCloudError(int $service): void
    {
        $result = ServiceFabric::uploadFile($service, 'token', $this->fixture(), 'report', new ThrowingConfig());

        $this->assertSame(['status' => 'error', 'msg' => 'Cloud Error'], $result);
    }

    public function testGetUsernameRejectsNullService(): void
    {
        $result = ServiceFabric::getUsername(null, 'token', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    public function testGetUsernameIsNotSupportedForBox(): void
    {
        $result = ServiceFabric::getUsername(ServiceFabric::BOX, 'token', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    public function testGetUsernameRoutesToDropboxModel(): void
    {
        $result = ServiceFabric::getUsername(ServiceFabric::DROPBOX, null, $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testGetUsernameRoutesToGoogleDriveModel(): void
    {
        $result = ServiceFabric::getUsername(ServiceFabric::GOOGLEDRIVE, null, $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testUpdateFileRejectsNullService(): void
    {
        $result = ServiceFabric::updateFile(null, 'token', 'https://e.com/f.pdf', 'f', '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    /**
     * Only Google Drive is wired into updateFile; Dropbox and Box fall through
     * the switch and come back as "Wrong service type".
     *
     * @dataProvider unsupportedUpdateServiceProvider
     */
    public function testUpdateFileIsOnlyWiredForGoogleDrive(int $service): void
    {
        $result = ServiceFabric::updateFile($service, 'token', 'https://e.com/f.pdf', 'f', '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    public function testUpdateFileWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = ServiceFabric::updateFile(
            ServiceFabric::GOOGLEDRIVE,
            null,
            'https://e.com/f.pdf',
            'f',
            '1',
            $this->config()
        );

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testUpdateFileTranslatesModelExceptionsIntoCloudErrorWithMessage(): void
    {
        $result = ServiceFabric::updateFile(
            ServiceFabric::GOOGLEDRIVE,
            'token',
            'https://e.com/f.pdf',
            'f',
            '1',
            new ThrowingConfig()
        );

        $this->assertSame('error', $result['status']);
        $this->assertSame('Cloud Error ' . ThrowingConfig::MESSAGE, $result['msg']);
    }

    public function testGetFileMetadataRejectsNullService(): void
    {
        $result = ServiceFabric::getFileMetadata(null, 'token', '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    /**
     * @dataProvider unsupportedUpdateServiceProvider
     */
    public function testGetFileMetadataIsOnlyWiredForGoogleDrive(int $service): void
    {
        $result = ServiceFabric::getFileMetadata($service, 'token', '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Wrong service type'], $result);
    }

    public function testGetFileMetadataWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = ServiceFabric::getFileMetadata(ServiceFabric::GOOGLEDRIVE, null, '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testGetFileMetadataTranslatesModelExceptionsIntoCloudErrorWithMessage(): void
    {
        $result = ServiceFabric::getFileMetadata(ServiceFabric::GOOGLEDRIVE, 'token', '1', new ThrowingConfig());

        $this->assertSame('error', $result['status']);
        $this->assertSame('Cloud Error ' . ThrowingConfig::MESSAGE, $result['msg']);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function serviceProvider(): array
    {
        return [
            'dropbox' => [ServiceFabric::DROPBOX],
            'google drive' => [ServiceFabric::GOOGLEDRIVE],
            'box' => [ServiceFabric::BOX],
        ];
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function unsupportedUpdateServiceProvider(): array
    {
        return [
            'dropbox' => [ServiceFabric::DROPBOX],
            'box' => [ServiceFabric::BOX],
        ];
    }
}
