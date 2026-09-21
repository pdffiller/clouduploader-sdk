<?php

declare(strict_types=1);

namespace Tests\Unit\UploadModels;

use Tests\Support\SuperglobalTestCase;
use UploadModels\GoogleDriveModel;

/**
 * Google's client builds its own Guzzle transport internally and the model
 * offers no seam to replace it, so everything that would hit the Drive API is
 * covered through the guard clauses and the pure helpers instead.
 *
 * @covers \UploadModels\GoogleDriveModel
 */
class GoogleDriveModelTest extends SuperglobalTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'GOOGLEDRIVE_CLIENTID' => 'gd-client-id',
            'GOOGLEDRIVE_PROJECTID' => 'gd-project',
            'GOOGLEDRIVE_AUTHURL' => 'https://accounts.google.com/o/oauth2/auth',
            'GOOGLEDRIVE_TOKEN_URL' => 'https://oauth2.googleapis.com/token',
            'GOOGLEDRIVE_AUTHPROV' => 'https://www.googleapis.com/oauth2/v1/certs',
            'GOOGLEDRIVE_CLIENTSECRET' => 'gd-secret',
            'GOOGLEDRIVE_REDIRECT2' => 'https://app.example.com/google/callback',
            'SAVE_FOLDER' => 'PDFfiller',
        ];
    }

    public function testGetGoogleConfigBuildsInstalledSection(): void
    {
        $result = GoogleDriveModel::getGoogleConfig($this->config());

        $this->assertSame([
            'client_id' => 'gd-client-id',
            'project_id' => 'gd-project',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_secret' => 'gd-secret',
            'redirect_uris' => ['https://app.example.com/google/callback'],
        ], $result['installed']);
    }

    public function testGetGoogleConfigKeepsTheOriginalKeys(): void
    {
        $result = GoogleDriveModel::getGoogleConfig($this->config());

        $this->assertSame('PDFfiller', $result['SAVE_FOLDER']);
        $this->assertSame('gd-client-id', $result['GOOGLEDRIVE_CLIENTID']);
    }

    public function testAuthBuildsAuthorizationUrl(): void
    {
        $url = GoogleDriveModel::auth('state-42', $this->config());

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('gd-client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('state-42', $query['state']);
        $this->assertSame('https://app.example.com/google/callback', $query['redirect_uri']);
    }

    public function testAuthRequestsFullDriveScope(): void
    {
        $url = GoogleDriveModel::auth('state-42', $this->config());

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(\Google\Service\Drive::DRIVE, $query['scope']);
    }

    public function testUploadFileWithoutAccessTokenReturnsFalse(): void
    {
        $this->assertFalse(GoogleDriveModel::uploadFile(null, '/tmp/f.pdf', 'f', $this->config()));
    }

    public function testGetUsernameWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = GoogleDriveModel::getUsername(null, $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testUpdateFileWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = GoogleDriveModel::updateFile(null, 'https://example.com/f.pdf', 'f', 'file-1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testGetFileMetadataWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = GoogleDriveModel::getFileMetadata(null, 'file-1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
    }

    public function testGetExtensionReadsPlainPath(): void
    {
        $result = $this->callPrivateStatic(GoogleDriveModel::class, 'getExtension', ['/tmp/report.pdf']);

        $this->assertSame('pdf', $result);
    }

    public function testGetExtensionOfPathWithoutExtension(): void
    {
        $result = $this->callPrivateStatic(GoogleDriveModel::class, 'getExtension', ['/tmp/report']);

        $this->assertSame('', $result);
    }

    /**
     * @dataProvider mimeProvider
     */
    public function testGetMimeMapsKnownExtensions(string $extension, string $expected): void
    {
        $result = $this->callPrivateStatic(GoogleDriveModel::class, 'getMime', [$extension]);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function mimeProvider(): array
    {
        return [
            'pdf' => ['pdf', 'application/pdf'],
            'txt' => ['txt', 'text/plain'],
            'doc' => ['doc', 'application/msword'],
            'dot' => ['dot', 'application/msword'],
            'docx' => [
                'docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'xls' => ['xls', 'application/vnd.ms-excel'],
            'xlt' => ['xlt', 'application/vnd.ms-excel'],
            'xla' => ['xla', 'application/vnd.ms-excel'],
            'xlsx' => [
                'xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'pptx' => [
                'pptx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
            'ppt' => ['ppt', 'application/vnd.ms-powerpoint'],
            'pot' => ['pot', 'application/vnd.ms-powerpoint'],
            'pps' => ['pps', 'application/vnd.ms-powerpoint'],
            'ppa' => ['ppa', 'application/vnd.ms-powerpoint'],
        ];
    }

    public function testGetMimeReturnsNullForUnknownExtension(): void
    {
        $result = $this->callPrivateStatic(GoogleDriveModel::class, 'getMime', ['png']);

        $this->assertNull($result);
    }

    public function testImplementsUploadServiceInterface(): void
    {
        $this->assertInstanceOf(
            \Interfaces\UploadServiceInterface::class,
            $this->makeEmpty(GoogleDriveModel::class)
        );
    }
}
