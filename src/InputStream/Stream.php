<?php declare(strict_types = 1);

namespace pcrov\JsonReader\InputStream;

use pcrov\JsonReader\InvalidArgumentException;

final class Stream implements \IteratorAggregate
{
    private $stream;

    /**
     * @param resource $stream Readable stream handle, as those typically
     *                         created with fopen.
     * @throws InvalidArgumentException if the given argument is not a valid stream resource.
     * @throws IOException if the given stream resource is not readable.
     */
    public function __construct($stream)
    {
        $this->validateStream($stream);
        $this->stream = $stream;
    }

    public function getIterator() : \Generator
    {
        $stream = $this->stream;

        while (($buffer = @fread($stream, 8192)) !== false) {
            yield from new StringInput($buffer);
            if (@feof($stream)) {
                break;
            }
        }
    }

    private function validateStream($stream)
    {
        if (
            !is_resource($stream) ||
            get_resource_type($stream) !== "stream" ||
            stream_get_meta_data($stream)["stream_type"] === "dir"
        ) {
            throw new InvalidArgumentException("A valid stream resource must be provided.");
        }

        $mode = stream_get_meta_data($stream)["mode"];
        if (!strpbrk($mode, "r+")) {
            throw new IOException(sprintf("Stream must be readable. Given stream opened in mode: %s", $mode));
        }
    }
}
