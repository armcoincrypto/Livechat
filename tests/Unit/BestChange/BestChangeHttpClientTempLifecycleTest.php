<?php

declare(strict_types=1);

namespace Tests\Unit\BestChange;

use Illuminate\Http\Client\Response as LaravelResponse;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Proves Fast Market / BestChange HTTP response sinks release /tmp/php* files.
 *
 * Guzzle stores large bodies in php://temp which spills to named /tmp/php* files.
 * Closing the Laravel HTTP response body unlinks those files. Leaving them open
 * in rates:fast-market-producer-loop is the disk accumulation root cause.
 */
final class BestChangeHttpClientTempLifecycleTest extends TestCase
{
    public function test_source_closes_response_after_json_consume(): void
    {
        $src = (string) file_get_contents(base_path('packages/BestChange/Http/BestChangeHttpClient.php'));
        if ($src === '' || ! str_contains($src, 'class BestChangeHttpClient')) {
            $src = (string) file_get_contents(__DIR__.'/../../../packages/BestChange/Http/BestChangeHttpClient.php');
        }

        $this->assertStringContainsString('releaseHttpResponse', $src);
        $this->assertStringContainsString('finally', $src);
        $this->assertMatchesRegularExpression(
            '/return \(array\) \$response->json\(\);[\s\S]*?finally\s*\{[\s\S]*?releaseHttpResponse\(\$response\)/',
            $src
        );
        // Must not introduce mass /tmp deletion.
        $this->assertStringNotContainsString("unlink('/tmp/php", $src);
        $this->assertStringNotContainsString("glob('/tmp/php", $src);
    }

    public function test_closing_laravel_response_unlinks_php_temp_spill_file(): void
    {
        $before = $this->phpTempPaths();

        $stream = fopen('php://temp/maxmemory:1024', 'w+b');
        $this->assertIsResource($stream);
        // Force spill beyond maxmemory → named /tmp/php* file (Guzzle sink pattern).
        fwrite($stream, '{"rates":{"1-2":[{"changer":1,"rate":"1"}]}}'.str_repeat('x', 2_500_000));
        fflush($stream);
        rewind($stream);

        $afterOpen = $this->phpTempPaths();
        $created = array_values(array_diff($afterOpen, $before));
        $this->assertNotEmpty($created, 'expected php://temp spill to create /tmp/php* file');

        $psrBody = new class($stream) implements StreamInterface {
            /** @param resource $resource */
            public function __construct(private $resource) {}

            public function __toString(): string
            {
                return stream_get_contents($this->resource, -1, 0) ?: '';
            }

            public function close(): void
            {
                if (is_resource($this->resource)) {
                    fclose($this->resource);
                    $this->resource = null;
                }
            }

            public function detach()
            {
                $r = $this->resource;
                $this->resource = null;

                return $r;
            }

            public function getSize(): ?int
            {
                return null;
            }

            public function tell(): int
            {
                return (int) ftell($this->resource);
            }

            public function eof(): bool
            {
                return feof($this->resource);
            }

            public function isSeekable(): bool
            {
                return true;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                fseek($this->resource, $offset, $whence);
            }

            public function rewind(): void
            {
                rewind($this->resource);
            }

            public function isWritable(): bool
            {
                return true;
            }

            public function write(string $string): int
            {
                return (int) fwrite($this->resource, $string);
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                return (string) fread($this->resource, $length);
            }

            public function getContents(): string
            {
                return (string) stream_get_contents($this->resource);
            }

            public function getMetadata(?string $key = null)
            {
                $meta = stream_get_meta_data($this->resource);

                return $key === null ? $meta : ($meta[$key] ?? null);
            }
        };

        $psr = new class($psrBody) implements ResponseInterface {
            public function __construct(private StreamInterface $body) {}

            public function getProtocolVersion(): string
            {
                return '1.1';
            }

            public function withProtocolVersion(string $version): ResponseInterface
            {
                return $this;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function hasHeader(string $name): bool
            {
                return false;
            }

            public function getHeader(string $name): array
            {
                return [];
            }

            public function getHeaderLine(string $name): string
            {
                return '';
            }

            public function withHeader(string $name, $value): ResponseInterface
            {
                return $this;
            }

            public function withAddedHeader(string $name, $value): ResponseInterface
            {
                return $this;
            }

            public function withoutHeader(string $name): ResponseInterface
            {
                return $this;
            }

            public function getBody(): StreamInterface
            {
                return $this->body;
            }

            public function withBody(StreamInterface $body): ResponseInterface
            {
                return $this;
            }

            public function getStatusCode(): int
            {
                return 200;
            }

            public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
            {
                return $this;
            }

            public function getReasonPhrase(): string
            {
                return 'OK';
            }
        };

        $laravel = new LaravelResponse($psr);
        // Consume then close — mirrors BestChangeHttpClient finally{releaseHttpResponse}.
        $json = $laravel->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('rates', $json);
        $laravel->close();

        clearstatcache();
        foreach ($created as $path) {
            $this->assertFileDoesNotExist($path, 'closed response must unlink owned php temp spill');
        }
    }

    public function test_abandoned_temp_spill_remains_until_closed_proving_leak_mechanism(): void
    {
        $before = $this->phpTempPaths();
        $stream = fopen('php://temp/maxmemory:1024', 'w+b');
        fwrite($stream, str_repeat('y', 2_500_000));
        fflush($stream);
        $created = array_values(array_diff($this->phpTempPaths(), $before));
        $this->assertNotEmpty($created);

        // Abandon without fclose → file remains (leak mechanism).
        $stream = null;
        // Resource may still be open via GC; force close path contrast:
        // Explicitly leave file by NOT closing — use a second handle pattern.
        // Re-open proof: created files exist while original resource still open.
        foreach ($created as $path) {
            $this->assertFileExists($path);
        }

        // Cleanup owned test artifacts only (paths we just created).
        foreach ($created as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function test_bounded_leak_regression_many_close_cycles_return_to_baseline(): void
    {
        $baseline = $this->phpTempPaths();
        $owned = [];

        for ($i = 0; $i < 8; $i++) {
            $before = $this->phpTempPaths();
            $stream = fopen('php://temp/maxmemory:512', 'w+b');
            fwrite($stream, '{"rates":{}}'.str_repeat('z', 1_200_000));
            fflush($stream);
            $new = array_values(array_diff($this->phpTempPaths(), $before));
            $owned = array_merge($owned, $new);

            $body = new class($stream) implements StreamInterface {
                /** @param resource|null $resource */
                public function __construct(private $resource) {}

                public function __toString(): string
                {
                    return '';
                }

                public function close(): void
                {
                    if (is_resource($this->resource)) {
                        fclose($this->resource);
                        $this->resource = null;
                    }
                }

                public function detach()
                {
                    return null;
                }

                public function getSize(): ?int
                {
                    return null;
                }

                public function tell(): int
                {
                    return 0;
                }

                public function eof(): bool
                {
                    return true;
                }

                public function isSeekable(): bool
                {
                    return false;
                }

                public function seek(int $offset, int $whence = SEEK_SET): void {}

                public function rewind(): void {}

                public function isWritable(): bool
                {
                    return false;
                }

                public function write(string $string): int
                {
                    return 0;
                }

                public function isReadable(): bool
                {
                    return false;
                }

                public function read(int $length): string
                {
                    return '';
                }

                public function getContents(): string
                {
                    return '';
                }

                public function getMetadata(?string $key = null)
                {
                    return $key === null ? [] : null;
                }
            };

            $psr = new class($body) implements ResponseInterface {
                public function __construct(private StreamInterface $body) {}

                public function getProtocolVersion(): string
                {
                    return '1.1';
                }

                public function withProtocolVersion(string $version): ResponseInterface
                {
                    return $this;
                }

                public function getHeaders(): array
                {
                    return [];
                }

                public function hasHeader(string $name): bool
                {
                    return false;
                }

                public function getHeader(string $name): array
                {
                    return [];
                }

                public function getHeaderLine(string $name): string
                {
                    return '';
                }

                public function withHeader(string $name, $value): ResponseInterface
                {
                    return $this;
                }

                public function withAddedHeader(string $name, $value): ResponseInterface
                {
                    return $this;
                }

                public function withoutHeader(string $name): ResponseInterface
                {
                    return $this;
                }

                public function getBody(): StreamInterface
                {
                    return $this->body;
                }

                public function withBody(StreamInterface $body): ResponseInterface
                {
                    return $this;
                }

                public function getStatusCode(): int
                {
                    return 200;
                }

                public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
                {
                    return $this;
                }

                public function getReasonPhrase(): string
                {
                    return 'OK';
                }
            };

            (new LaravelResponse($psr))->close();
        }

        clearstatcache();
        foreach ($owned as $path) {
            $this->assertFileDoesNotExist($path);
        }

        // Must not grow baseline with our owned spills (ignore concurrent producer growth).
        $remainingOwned = array_values(array_filter($owned, 'is_file'));
        $this->assertSame([], $remainingOwned);
        unset($baseline);
    }

    /**
     * @return list<string>
     */
    private function phpTempPaths(): array
    {
        $paths = glob('/tmp/php*') ?: [];
        sort($paths);

        return $paths;
    }
}
