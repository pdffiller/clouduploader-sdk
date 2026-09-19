<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Kunnu\Dropbox\Http\Clients\DropboxHttpClientInterface;
use Kunnu\Dropbox\Http\DropboxRawResponse;

/**
 * In-memory replacement for the Guzzle based Dropbox HTTP client.
 *
 * Responses are queued up front and handed out in FIFO order, so a test can
 * drive DropBoxModel end to end without touching the network. Every call is
 * recorded, which lets the tests assert on the request the SDK actually built.
 */
class FakeDropboxHttpClient implements DropboxHttpClientInterface
{
    /**
     * @var array<int, DropboxRawResponse|\Throwable>
     */
    private $queue = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private $requests = [];

    /**
     * @param array<string, mixed> $body decoded JSON body the fake should return
     */
    public function pushJson(array $body, int $statusCode = 200): self
    {
        $this->queue[] = new DropboxRawResponse(
            ['Content-Type' => ['application/json']],
            (string) json_encode($body),
            $statusCode
        );

        return $this;
    }

    public function pushRaw(string $body, int $statusCode = 200, array $headers = []): self
    {
        $this->queue[] = new DropboxRawResponse($headers, $body, $statusCode);

        return $this;
    }

    public function pushException(?\Throwable $exception = null): self
    {
        $this->queue[] = $exception ?: new DropboxClientException('Simulated transport failure');

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function send($url, $method, $body, $headers = [], $options = [])
    {
        $this->requests[] = [
            'url' => $url,
            'method' => $method,
            'body' => $body,
            'headers' => $headers,
            'options' => $options,
        ];

        if ($this->queue === []) {
            throw new DropboxClientException(sprintf('No queued response for %s %s', $method, $url));
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastRequest(): ?array
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function requests(): array
    {
        return $this->requests;
    }

    public function requestCount(): int
    {
        return count($this->requests);
    }
}
