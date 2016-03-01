<?php declare(strict_types = 1);

namespace pcrov\JsonReader\InputStream;

class Stream implements \IteratorAggregate
{
    private $stream;

    /**
     * @param resource $stream Readable stream handle, as those typically created with fopen.
     * @throws IOException
     */
    public function __construct($stream)
    {
        $mode = stream_get_meta_data($stream)["mode"];
        if (!strpbrk($mode, "r+")) {
            throw new IOException(sprintf("Stream must be readable. Given stream opened in mode: %s", $mode));
        }

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
}
