<?php declare(strict_types = 1);

namespace JsonReader\InputStream;

class File implements \IteratorAggregate
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
            while (!feof($handle)) {
                $buffer = fread($handle, 8192);
                $length = strlen($buffer);
                for ($i = 0; $i < $length; $i++) {
                    yield $buffer[$i];
                }
            }
        } finally {
            fclose($handle);
        }
    }
}