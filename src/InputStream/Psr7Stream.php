<?php declare(strict_types=1);

namespace pcrov\JsonReader\InputStream;

use Psr\Http\Message\StreamInterface;

final class Psr7Stream implements InputStream
{
    const CHUNK_SIZE = 8192;

    private $stream;

    /**
     * @throws IOException if the given stream is not readable.
     */
    public function __construct(StreamInterface $stream)
    {
        if (!$stream->isReadable()) {
            throw new IOException("Stream must be readable.");
        }

        $this->stream = $stream;
    }

    public function read()
    {
        $stream = $this->stream;

        try {
            return $stream->eof() ? null : $stream->read(self::CHUNK_SIZE);
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
