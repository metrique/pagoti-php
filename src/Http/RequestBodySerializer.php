<?php

namespace Metrique\Pagoti\Http;

use GuzzleHttp\Psr7\MultipartStream;
use Metrique\Pagoti\Exceptions\PagotiException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SplFileInfo;

class RequestBodySerializer
{
    public function serialize(RequestInterface $request, array $body, StreamFactoryInterface $streamFactory): RequestInterface
    {
        if ($this->shouldSendMultipart($body)) {
            $stream = new MultipartStream($this->multipartElements($body));

            return $request
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $stream->getBoundary())
                ->withBody($stream);
        }

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
    }

    private function shouldSendMultipart(array $body): bool
    {
        return array_key_exists('image', $body) && $body['image'] !== null;
    }

    private function multipartElements(array $body): array
    {
        $elements = [];

        foreach ($body as $name => $value) {
            if ($value === null) {
                continue;
            }

            if ($name === 'image') {
                $elements[] = $this->multipartFileElement($name, $value);
                continue;
            }

            $elements[] = [
                'name' => $name,
                'contents' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            ];
        }

        return $elements;
    }

    private function multipartFileElement(string $name, mixed $value): array
    {
        if (is_string($value)) {
            return [
                'name' => $name,
                'contents' => $this->openFile($value),
                'filename' => basename($value),
            ];
        }

        if (is_resource($value)) {
            return [
                'name' => $name,
                'contents' => $value,
                'filename' => 'upload',
            ];
        }

        if ($value instanceof SplFileInfo) {
            return [
                'name' => $name,
                'contents' => $this->openFile($value->getPathname()),
                'filename' => $value->getBasename(),
            ];
        }

        throw new PagotiException('The image field must be a file path, stream resource, or SplFileInfo instance.');
    }

    /**
     * @return resource
     */
    private function openFile(string $path): mixed
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new PagotiException("The image file [{$path}] does not exist or is not readable.");
        }

        $resource = @fopen($path, 'r');

        if ($resource === false) {
            throw new PagotiException("The image file [{$path}] could not be opened for reading.");
        }

        return $resource;
    }
}
