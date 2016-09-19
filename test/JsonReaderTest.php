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
            private $nodes = [
                [JsonReader::OBJECT, null, null, 0],
                [JsonReader::STRING, "string name", "string value", 1],
                [JsonReader::NUMBER, "number name", 42, 1],
                [JsonReader::BOOL, "boolean true", true, 1],
                [JsonReader::BOOL, "boolean false", false, 1],
                [JsonReader::NULL, "null name", null, 1],
                [JsonReader::ARRAY, "array name", null, 1],
                [JsonReader::ARRAY, null, null, 2],
                [JsonReader::END_ARRAY, null, null, 2],
                [JsonReader::OBJECT, null, null, 2],
                [JsonReader::NUMBER, "number name", 43, 3],
                [JsonReader::END_OBJECT, null, null, 2],
                [JsonReader::OBJECT, null, null, 2],
                [JsonReader::END_OBJECT, null, null, 2],
                [JsonReader::END_ARRAY, "array name", null, 1],
                [JsonReader::NUMBER, "number name", 44, 1],
                [JsonReader::END_OBJECT, null, null, 0],
            ];

            public function getIterator() : \Generator
            {
                yield from $this->nodes;
            }
        };
    }

    public function testJson()
    {
        $reader = $this->reader;
        $reader->json(file_get_contents(__DIR__ . "/../composer.json"));
        while ($reader->read());
    }

    public function testOpen()
    {
        $reader = $this->reader;
        $reader->open(__DIR__ . "/../composer.json");
        while ($reader->read());
    }

    public function testStream()
    {
        $reader = $this->reader;
        $handle = fopen((__DIR__ . "/../composer.json"), "rb");
        $reader->stream($handle);
        while ($reader->read());
        fclose($handle);
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
        $this->assertSame(0, $reader->depth());
        $this->assertSame(0, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testStateFollowingInit()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $this->assertSame(0, $reader->depth());
        $this->assertSame(0, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testRead()
    {
        $expected = [
            [
                JsonReader::OBJECT,
                null,
                [
                    'string name' => 'string value',
                    'number name' => 44,
                    'boolean true' => true,
                    'boolean false' => false,
                    'null name' => null,
                    'array name' => [[], ['number name' => 43], []],
                ],
                0
            ],
            [JsonReader::STRING, 'string name', 'string value', 1],
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::BOOL, 'boolean true', true, 1],
            [JsonReader::BOOL, 'boolean false', false, 1],
            [JsonReader::NULL, 'null name', null, 1],
            [JsonReader::ARRAY, 'array name', [[], ['number name' => 43], []], 1],
            [JsonReader::ARRAY, null, [], 2],
            [JsonReader::END_ARRAY, null, null, 2],
            [JsonReader::OBJECT, null, ['number name' => 43], 2],
            [JsonReader::NUMBER, 'number name', 43, 3],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::OBJECT, null, [], 2],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::END_ARRAY, 'array name', null, 1],
            [JsonReader::NUMBER, 'number name', 44, 1],
            [JsonReader::END_OBJECT, null, null, 0],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);

        $i = 0;
        while ($reader->read()) {
            $this->assertSame($expected[$i][0], $reader->type());
            $this->assertSame($expected[$i][1], $reader->name());
            $this->assertSame($expected[$i][2], $reader->value());
            $this->assertSame($expected[$i][3], $reader->depth());
            $i++;
        }
    }

    public function testReadName()
    {
        $expected = [
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::NUMBER, 'number name', 43, 3],
            [JsonReader::NUMBER, 'number name', 44, 1],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        $i = 0;
        while ($reader->read('number name')) {
            $this->assertSame($expected[$i][0], $reader->type());
            $this->assertSame($expected[$i][1], $reader->name());
            $this->assertSame($expected[$i][2], $reader->value());
            $this->assertSame($expected[$i][3], $reader->depth());
            $i++;
        }
    }

    public function testStateFollowingReadCompletion()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        while ($reader->read());
        $this->assertSame(0, $reader->depth());
        $this->assertSame(0, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testNext()
    {
        $expected = [
            [JsonReader::STRING, 'string name', 'string value', 1],
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::BOOL, 'boolean true', true, 1],
            [JsonReader::BOOL, 'boolean false', false, 1],
            [JsonReader::NULL, 'null name', null, 1],
            [JsonReader::ARRAY, 'array name', [[], ['number name' => 43], []], 1],
            [JsonReader::NUMBER, 'number name', 44, 1],
            [JsonReader::END_OBJECT, null, null, 0],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        $i = 0;
        do {
            $this->assertSame($expected[$i][0], $reader->type());
            $this->assertSame($expected[$i][1], $reader->name());
            $this->assertSame($expected[$i][2], $reader->value());
            $this->assertSame($expected[$i][3], $reader->depth());
            $i++;
        } while ($reader->next());
    }

    public function testNextRootFalse()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $this->assertFalse($reader->next());
    }

    public function testNextNameOver()
    {
        $expected = [
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::NUMBER, 'number name', 44, 1],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        $i = 0;
        while ($reader->next('number name')) {
            $this->assertSame($expected[$i][0], $reader->type());
            $this->assertSame($expected[$i][1], $reader->name());
            $this->assertSame($expected[$i][2], $reader->value());
            $this->assertSame($expected[$i][3], $reader->depth());
            $i++;
        }
    }

    public function testNextNameDescend()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read('number name');
        $reader->read('number name');
        $reader->next('number name');

        $this->assertSame(JsonReader::NUMBER, $reader->type());
        $this->assertSame('number name', $reader->name());
        $this->assertSame(44, $reader->value());
        $this->assertSame(1, $reader->depth());
    }

    public function testStateFollowingNextCompletion()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        while ($reader->next());
        $this->assertSame(0, $reader->depth());
        $this->assertSame(0, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testClose()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->close();
        $this->assertSame(0, $reader->depth());
        $this->assertSame(0, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }
}
