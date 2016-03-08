<?php declare(strict_types = 1);

namespace pcrov\JsonReader\InputStream;

final class Uri implements \IteratorAggregate
{
    private $uri;

    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    public function getIterator() : \Generator
    {
        $handle = @fopen($this->uri, "rb");
        if ($handle === false) {
            throw new IOException(sprintf("Failed to open URI: %s", $this->uri));
        }

        try {
            yield from new Stream($handle);
        } finally {
            fclose($handle);
        }
    }
}
