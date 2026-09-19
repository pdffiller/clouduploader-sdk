<?php

declare(strict_types=1);

namespace Tests\Unit;

use HttpReceiver\HttpReceiver;
use Tests\Support\SuperglobalTestCase;

/**
 * @covers \HttpReceiver\HttpReceiver
 */
class HttpReceiverTest extends SuperglobalTestCase
{
    public function testReturnsEmptyStringForUnknownType(): void
    {
        $this->givenRequest(['userId' => '42']);

        $this->assertSame('', HttpReceiver::get('userId', 'array'));
    }

    public function testCastsExistingValueToInt(): void
    {
        $this->givenRequest(['userId' => '42']);

        $this->assertSame(42, HttpReceiver::get('userId', 'int'));
    }

    public function testCastsNonNumericValueToZero(): void
    {
        $this->givenRequest(['userId' => 'not-a-number']);

        $this->assertSame(0, HttpReceiver::get('userId', 'int'));
    }

    public function testCastsNumericPrefixToInt(): void
    {
        $this->givenRequest(['userId' => '42abc']);

        $this->assertSame(42, HttpReceiver::get('userId', 'int'));
    }

    public function testReturnsZeroForMissingIntParameter(): void
    {
        $this->assertSame(0, HttpReceiver::get('userId', 'int'));
    }

    public function testReturnsEmptyStringForMissingStringParameter(): void
    {
        $this->assertSame('', HttpReceiver::get('code', 'string'));
    }

    public function testReturnsPlainStringUntouched(): void
    {
        $this->givenRequest(['code' => 'abc-123_XYZ']);

        $this->assertSame('abc-123_XYZ', HttpReceiver::get('code', 'string'));
    }

    public function testStripsTagsFromStringParameter(): void
    {
        $this->givenRequest(['code' => '<b>bold</b>code']);

        $this->assertSame('boldcode', HttpReceiver::get('code', 'string'));
    }

    public function testEscapesScriptPayload(): void
    {
        $this->givenRequest(['code' => '<script>alert("xss")</script>']);

        $this->assertSame('alert(&quot;xss&quot;)', HttpReceiver::get('code', 'string'));
    }

    public function testEscapesHtmlSpecialCharsThatSurviveStripTags(): void
    {
        $this->givenRequest(['code' => 'a & b > c']);

        $this->assertSame('a &amp; b &gt; c', HttpReceiver::get('code', 'string'));
    }

    public function testReadsFromRequestRegardlessOfSource(): void
    {
        $_GET['state'] = '7';
        $_REQUEST['state'] = '7';

        $this->assertSame(7, HttpReceiver::get('state', 'int'));
    }
}
