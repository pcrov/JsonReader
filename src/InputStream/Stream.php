<?php declare(strict_types=1);

namespace pcrov\JsonReader\InputStream;

use pcrov\JsonReader\InvalidArgumentException;

final class Stream implements InputStream
{
    const CHUNK_SIZE = 8192;

    private $handle;

    /**
     * @param resource $handle Readable stream handle, as those typically
     *                         created with fopen.
     * @throws InvalidArgumentException if the given argument is not a valid stream resource.
     * @throws IOException if the given stream resource is not readable.
     */
    public function __construct($handle)
    {
        if (
            !\is_resource($handle) ||
            \get_resource_type($handle) !== "stream" ||
            \stream_get_meta_data($handle)["stream_type"] === "dir"
        ) {
            throw new InvalidArgumentException("A valid stream resource must be provided.");
        }

        $mode = \stream_get_meta_data($handle)["mode"];
        if (!\strpbrk($mode, "r+")) {
            throw new IOException(\sprintf("Stream must be readable. Given stream opened in mode: %s", $mode));
        }

        $this->handle = $handle;
    }

    public function read()
    {
        $handle = $this->handle;
        if (!\is_resource($handle) || @\feof($handle)) {
            return null;
        }

        $data = @\fread($handle, self::CHUNK_SIZE);
        if ($data === false || ($data === "" && @\feof($handle))) {
            return null;
        }

        return $data;
    }
}
