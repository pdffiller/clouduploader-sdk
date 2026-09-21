<?php

declare(strict_types=1);

namespace Tests\Unit\UploadModels;

use Tests\Support\SuperglobalTestCase;
use UploadModels\BoxModel;

/**
 * Apibox talks to Box over cURL from inside the model, so the upload/token
 * flows cannot be driven from a unit test. What is reachable offline - URL
 * building, the "Not implemented" contract and the private helpers - is
 * covered here.
 *
 * @covers \UploadModels\BoxModel
 */
class BoxModelTest extends SuperglobalTestCase
{
    /**
     * @return array<string, string>
     */
    private function config(): array
    {
        return [
            'BOX_CLIENT_ID' => 'box-client-id',
            'BOX_CLIENT_SECRET' => 'box-client-secret',
            'BOX_REDIRECT_URI' => 'https://app.example.com/box/callback',
            'SAVE_FOLDER' => 'PDFfiller',
        ];
    }

    public function testAuthBuildsAuthorizationUrlWithState(): void
    {
        $url = BoxModel::auth(42, $this->config());

        $this->assertStringStartsWith('https://www.box.com/api/oauth2/authorize?', $url);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('box-client-id', $query['client_id']);
        $this->assertSame('https://app.example.com/box/callback', $query['redirect_uri']);
        $this->assertSame('42', $query['state']);
    }

    public function testAuthAppendsStateToTheEndOfTheUrl(): void
    {
        $url = BoxModel::auth('user-7', $this->config());

        $this->assertStringEndsWith('&state=user-7', $url);
    }

    /**
     * Apibox::get_code() returns null as soon as the incoming request carries a
     * refresh_token, so the model hands back a bare query fragment. Pinned here
     * because callers redirect to whatever comes out of auth().
     */
    public function testAuthDegradesToABareStateFragmentWhenRefreshTokenIsPresent(): void
    {
        $this->givenRequest(['refresh_token' => 'stored-refresh-token']);

        $this->assertSame('&state=42', BoxModel::auth(42, $this->config()));
    }

    public function testUpdateFileIsNotImplemented(): void
    {
        $result = BoxModel::updateFile('token', 'https://example.com/f.pdf', 'f', '1', $this->config());

        $this->assertSame(['status' => 'error', 'msg' => 'Not implemented'], $result);
    }

    public function testGetBoxCreatesApiboxFromConfig(): void
    {
        $box = $this->callPrivateStatic(BoxModel::class, 'getBox', [$this->config()]);

        $this->assertInstanceOf(\Apibox\Apibox::class, $box);
        $this->assertSame('box-client-id', $box->client_id);
        $this->assertSame('box-client-secret', $box->client_secret);
        $this->assertSame('https://app.example.com/box/callback', $box->redirect_uri);
    }

    public function testGetBoxLeavesCredentialsEmptyWhenClientIdIsMissing(): void
    {
        $box = $this->callPrivateStatic(BoxModel::class, 'getBox', [[
            'BOX_CLIENT_ID' => '',
            'BOX_CLIENT_SECRET' => 'box-client-secret',
            'BOX_REDIRECT_URI' => 'https://app.example.com/box/callback',
        ]]);

        $this->assertSame('', $box->client_id);
        $this->assertSame('', $box->client_secret);
    }

    public function testGetExtensionReadsPlainPath(): void
    {
        $result = $this->callPrivateStatic(BoxModel::class, 'getExtension', ['/tmp/report.pdf']);

        $this->assertSame('pdf', $result);
    }

    public function testGetExtensionOfPathWithoutExtension(): void
    {
        $result = $this->callPrivateStatic(BoxModel::class, 'getExtension', ['/tmp/report']);

        $this->assertSame('', $result);
    }

    /**
     * The query-string branch works, but `reset(explode(...))` passes a
     * function result by reference and therefore raises a notice on every call.
     * Asserted explicitly so the day it gets fixed this test points at the fix.
     */
    public function testGetExtensionStripsQueryStringAndRaisesNotice(): void
    {
        $notices = [];
        set_error_handler(static function (int $errno, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_NOTICE | E_WARNING | E_DEPRECATED);

        try {
            $result = $this->callPrivateStatic(
                BoxModel::class,
                'getExtension',
                ['https://files.example.com/doc.pdf&token=abc']
            );
        } finally {
            restore_error_handler();
        }

        $this->assertSame('pdf', $result);
        $this->assertContains('Only variables should be passed by reference', $notices);
    }

    public function testImplementsUploadServiceInterface(): void
    {
        $this->assertInstanceOf(\Interfaces\UploadServiceInterface::class, $this->makeEmpty(BoxModel::class));
    }
}
