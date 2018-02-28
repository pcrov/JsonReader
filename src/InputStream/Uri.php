<?php declare(strict_types=1);

namespace pcrov\JsonReader\InputStream;

final class Uri implements InputStream
{
    private $handle;
    private $stream;

    /**
     * @throws IOException
     * @throws \pcrov\JsonReader\InvalidArgumentException
     */
    public function __construct(string $uri)
    {
        $handle = @\fopen($uri, "rb");
        if ($handle === false) {
            throw new IOException(\sprintf("Failed to open URI: %s", $uri));
        }
        $this->handle = $handle;
        $this->stream = new Stream($handle);
    }

    public function read()
    {
        $data = $this->stream->read();
        if ($data === null) {
            $this->close();
        }

        return $data;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function close()
    {
        if (\is_resource($this->handle)) {
            \fclose($this->handle);
        }
    }
}
