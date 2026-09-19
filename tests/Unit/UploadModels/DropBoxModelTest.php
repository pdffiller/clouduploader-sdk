<?php

declare(strict_types=1);

namespace Tests\Unit\UploadModels;

use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Tests\Support\Doubles\ArrayPersistentDataStore;
use Tests\Support\Doubles\FakeDropboxHttpClient;
use Tests\Support\SuperglobalTestCase;
use UploadModels\DropBoxModel;

/**
 * @covers \UploadModels\DropBoxModel
 */
class DropBoxModelTest extends SuperglobalTestCase
{
    private const CLIENT_ID = 'dropbox-client-id';
    private const CLIENT_SECRET = 'dropbox-client-secret';
    private const REDIRECT_URI = 'https://app.example.com/dropbox/callback';

    /**
     * @var FakeDropboxHttpClient
     */
    private $http;

    /**
     * @var ArrayPersistentDataStore
     */
    private $store;

    protected function _before()
    {
        parent::_before();

        $this->http = new FakeDropboxHttpClient();
        $this->store = new ArrayPersistentDataStore();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'DROPBOX_KEY' => self::CLIENT_ID,
            'DROPBOX_SECRET' => self::CLIENT_SECRET,
            'DROPBOX_REDIRECT_URI' => self::REDIRECT_URI,
            'SAVE_FOLDER' => 'PDFfiller',
            'http_client_handler' => $this->http,
            'persistent_data_store' => $this->store,
        ], $overrides);
    }

    private function fixture(string $name = 'sample.txt'): string
    {
        return codecept_data_dir('fixtures/' . $name);
    }

    public function testConfigKeyConstants(): void
    {
        $this->assertSame('DROPBOX_KEY', DropBoxModel::CONFIG_CLIENT_ID);
        $this->assertSame('DROPBOX_SECRET', DropBoxModel::CONFIG_CLIENT_SECRET);
        $this->assertSame('DROPBOX_REDIRECT_URI', DropBoxModel::CONFIG_REDIRECT_URL);
    }

    public function testAuthBuildsAuthorizationUrl(): void
    {
        $url = DropBoxModel::auth(777, $this->config());

        $this->assertStringStartsWith('https://dropbox.com/oauth2/authorize?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(self::REDIRECT_URI, $query['redirect_uri']);
        $this->assertStringEndsWith('|777', $query['state']);
    }

    public function testAuthStoresCsrfTokenInPersistentStore(): void
    {
        $url = DropBoxModel::auth(777, $this->config());

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        [$csrfToken] = explode('|', $query['state']);

        $this->assertSame($csrfToken, $this->store->get('state'));
    }

    public function testAuthDoesNotPerformAnyHttpCall(): void
    {
        DropBoxModel::auth(1, $this->config());

        $this->assertSame(0, $this->http->requestCount());
    }

    public function testGetTokenReturnsEmptyStringWhenCodeIsMissing(): void
    {
        $this->givenQuery(['state' => 'csrf|1']);

        $this->assertSame('', DropBoxModel::getToken($this->config()));
    }

    public function testGetTokenReturnsEmptyStringWhenStateIsMissing(): void
    {
        $this->givenQuery(['code' => 'auth-code']);

        $this->assertSame('', DropBoxModel::getToken($this->config()));
    }

    public function testGetTokenExchangesCodeForAccessToken(): void
    {
        $this->store->set('state', 'csrf-token');
        $this->givenQuery(['code' => 'auth-code', 'state' => 'csrf-token|42']);
        $this->http->pushJson([
            'access_token' => 'sl.access-token',
            'token_type' => 'bearer',
            'account_id' => 'dbid:42',
        ]);

        $this->assertSame('sl.access-token', DropBoxModel::getToken($this->config()));

        $request = $this->http->lastRequest();
        $this->assertSame('POST', $request['method']);
        $this->assertStringContainsString('oauth2/token', $request['url']);
        $this->assertStringContainsString('code=auth-code', $request['url']);
        $this->assertStringContainsString(urlencode(self::REDIRECT_URI), $request['url']);
    }

    public function testGetTokenClearsCsrfTokenAfterSuccessfulExchange(): void
    {
        $this->store->set('state', 'csrf-token');
        $this->givenQuery(['code' => 'auth-code', 'state' => 'csrf-token|42']);
        $this->http->pushJson(['access_token' => 'sl.access-token']);

        DropBoxModel::getToken($this->config());

        $this->assertFalse($this->store->has('state'));
    }

    public function testGetTokenReturnsEmptyStringOnCsrfMismatch(): void
    {
        $this->store->set('state', 'expected-csrf');
        $this->givenQuery(['code' => 'auth-code', 'state' => 'forged-csrf|42']);

        $this->assertSame('', DropBoxModel::getToken($this->config()));
        $this->assertSame(0, $this->http->requestCount());
    }

    public function testGetTokenReturnsEmptyStringWhenDropboxRejectsTheCode(): void
    {
        $this->store->set('state', 'csrf-token');
        $this->givenQuery(['code' => 'auth-code', 'state' => 'csrf-token|42']);
        $this->http->pushException(new DropboxClientException('invalid_grant'));

        $this->assertSame('', DropBoxModel::getToken($this->config()));
    }

    public function testUploadFileWithoutAccessTokenReturnsRefreshTokenUrl(): void
    {
        $this->givenRequest(['userId' => '99']);

        $result = DropBoxModel::uploadFile('', $this->fixture(), 'report', $this->config());

        $this->assertSame('error', $result['status']);
        $this->assertSame('refreshToken', $result['msg']);
        $this->assertStringContainsString('oauth2/authorize', $result['url']);
        $this->assertStringContainsString('%7C99', $result['url']);
    }

    public function testUploadFileWithMissingLocalFileReturnsError(): void
    {
        $result = DropBoxModel::uploadFile('token', '/no/such/file.pdf', 'report', $this->config());

        $this->assertSame('error', $result['status']);
        $this->assertSame('fileNotExist', $result[0]);
        $this->assertSame(0, $this->http->requestCount());
    }

    public function testUploadFileReturnsFileIdOnSuccess(): void
    {
        $this->http->pushJson(['id' => 'id:abc123', 'name' => 'report.txt', 'size' => 31]);

        $result = DropBoxModel::uploadFile('token', $this->fixture(), 'report', $this->config());

        $this->assertSame(['status' => 'ok', 'file_id' => 'id:abc123'], $result);
    }

    public function testUploadFileUsesGivenFileNameWithSourceExtension(): void
    {
        $this->http->pushJson(['id' => 'id:abc123']);

        DropBoxModel::uploadFile('token', $this->fixture(), 'report', $this->config());

        $args = json_decode($this->http->lastRequest()['headers']['Dropbox-API-Arg'], true);
        $this->assertSame('/PDFfiller/report.txt', $args['path']);
        $this->assertTrue($args['autorename']);
    }

    public function testUploadFileFallsBackToSourceFileNameWhenNameIsEmpty(): void
    {
        $this->http->pushJson(['id' => 'id:abc123']);

        DropBoxModel::uploadFile('token', $this->fixture(), '', $this->config());

        $args = json_decode($this->http->lastRequest()['headers']['Dropbox-API-Arg'], true);
        $this->assertSame('/PDFfiller/sample.txt', $args['path']);
    }

    public function testUploadFileSendsBearerToken(): void
    {
        $this->http->pushJson(['id' => 'id:abc123']);

        DropBoxModel::uploadFile('token-abc', $this->fixture(), 'report', $this->config());

        $this->assertSame('Bearer token-abc', $this->http->lastRequest()['headers']['Authorization']);
    }

    public function testUploadFileReturnsRefreshTokenUrlWhenApiFails(): void
    {
        $this->givenRequest(['userId' => '55']);
        $this->http->pushException(new DropboxClientException('insufficient_space'));

        $result = DropBoxModel::uploadFile('token', $this->fixture(), 'report', $this->config());

        $this->assertSame('error', $result['status']);
        $this->assertSame('refreshToken', $result['msg']);
        $this->assertStringContainsString('oauth2/authorize', $result['url']);
        $this->assertStringContainsString('%7C55', $result['url']);
    }

    public function testGetUsernameWithoutAccessTokenIsDeniedByUser(): void
    {
        $result = DropBoxModel::getUsername(null, $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'deniedByUser'], $result);
        $this->assertSame(0, $this->http->requestCount());
    }

    public function testGetUsernameReturnsAccountEmail(): void
    {
        $this->http->pushJson([
            'account_id' => 'dbid:42',
            'email' => 'user@example.com',
            'email_verified' => true,
        ]);

        $result = DropBoxModel::getUsername('token', $this->config());

        $this->assertSame(['status' => 'ok', 'username' => 'user@example.com'], $result);
        $this->assertStringContainsString('users/get_current_account', $this->http->lastRequest()['url']);
    }

    public function testGetUsernameReturnsCloudErrorWithDetails(): void
    {
        $this->http->pushException(new DropboxClientException('expired_access_token'));

        $result = DropBoxModel::getUsername('token', $this->config());

        $this->assertSame('error', $result['status']);
        $this->assertSame('Cloud Error', $result['msg']);
        $this->assertSame('expired_access_token', $result['details']);
    }

    public function testUpdateFileIsNotImplemented(): void
    {
        $result = DropBoxModel::updateFile('token', 'https://example.com/f.pdf', 'f', 'id:1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Not implemented'], $result);
    }

    public function testGenerateRemoteFilenameTakesTheLastPathSegment(): void
    {
        $result = $this->callPrivateStatic(
            DropBoxModel::class,
            'generateRemoteFilename',
            ['/tmp/uploads/2026/report.pdf']
        );

        $this->assertSame('report.pdf', $result);
    }

    public function testGetExtensionReadsPlainPath(): void
    {
        $result = $this->callPrivateStatic(DropBoxModel::class, 'getExtension', ['/tmp/report.pdf']);

        $this->assertSame('pdf', $result);
    }

    public function testImplementsUploadServiceInterface(): void
    {
        $this->assertInstanceOf(\Interfaces\UploadServiceInterface::class, $this->makeEmpty(DropBoxModel::class));
    }
}
