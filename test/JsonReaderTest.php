<?php

namespace pcrov\JsonReader;

class JsonReaderTest extends \PHPUnit_Framework_TestCase
{
    /** @var JsonReader */
    protected $reader;

    /** @var \Traversable */
    protected $parser;

    public function setUp()
    {
        $this->reader = new JsonReader();
        $this->parser = new class implements \IteratorAggregate
        {
            /** @var array */
            private $nodes = [];

            public function setNodes(array $nodes)
            {
                $this->nodes = $nodes;
            }

            public function getIterator() : \Generator
            {
                yield from $this->nodes;
            }
        };
    }

    public function testReadNoParser()
    {
        $this->expectException(Exception::class);
        $this->reader->read();
    }

    public function testNextNoParser()
    {
        $this->expectException(Exception::class);
        $this->reader->next();
    }

    public function testInitialState()
    {
        $reader = $this->reader;
        $this->assertSame(0, $reader->getDepth());
        $this->assertSame(0, $reader->getNodeType());
        $this->assertNull($reader->getName());
        $this->assertNull($reader->getValue());
    }

    public function testStateFollowingInit()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $this->assertSame(0, $reader->getDepth());
        $this->assertSame(0, $reader->getNodeType());
        $this->assertNull($reader->getName());
        $this->assertNull($reader->getValue());
    }
}
