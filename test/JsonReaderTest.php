<?php

namespace pcrov\JsonReader;

use pcrov\JsonReader\Parser\Parser;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class JsonReaderTest extends TestCase
{
    /** @var JsonReader */
    protected $reader;

    /** @var \Traversable */
    protected $parser;

    public function setUp(): void
    {
        $this->reader = new JsonReader();
        $this->parser = new class implements Parser
        {
            private $nodes = [
                [JsonReader::OBJECT, null, null, 0],
                [JsonReader::STRING, "string name", "string value", 1],
                [JsonReader::NUMBER, "number name", "42", 1],
                [JsonReader::BOOL, "boolean true", true, 1],
                [JsonReader::BOOL, "boolean false", false, 1],
                [JsonReader::NULL, "null name", null, 1],
                [JsonReader::ARRAY, "array name", null, 1],
                [JsonReader::ARRAY, null, null, 2],
                [JsonReader::END_ARRAY, null, null, 2],
                [JsonReader::OBJECT, null, null, 2],
                [JsonReader::NUMBER, "number name", "-43.0", 3],
                [JsonReader::END_OBJECT, null, null, 2],
                [JsonReader::OBJECT, null, null, 2],
                [JsonReader::END_OBJECT, null, null, 2],
                [JsonReader::END_ARRAY, "array name", null, 1],
                [JsonReader::NUMBER, "number name", "0.44e-2", 1],
                [JsonReader::END_OBJECT, null, null, 0],
            ];

            public function read()
            {
                $nodes = &$this->nodes;

                if (($current = \current($nodes)) === false) {
                    return null;
                }
                next($nodes);

                return $current;
            }
        };
    }

    public function testJson()
    {
        $reader = $this->reader;
        $reader->json(file_get_contents(__DIR__ . "/../composer.json"));
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
        while ($reader->read());
    }

    public function testOpen()
    {
        $reader = $this->reader;
        $reader->open(__DIR__ . "/../composer.json");
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
        while ($reader->read());
    }

    public function testPsr7Stream()
    {
        $reader = $this->reader;
        $psr7 = $this->createMock(StreamInterface::class);
        $psr7->method("isReadable")->willReturn(true);
        $reader->psr7Stream($psr7);
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testStream()
    {
        $reader = $this->reader;
        $handle = fopen((__DIR__ . "/../composer.json"), "rb");
        $reader->stream($handle);
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
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
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testStateFollowingInit()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testRead()
    {
        $expecteds = [
            [
                JsonReader::OBJECT,
                null,
                [
                    'string name' => 'string value',
                    'number name' => 0.44e-2,
                    'boolean true' => true,
                    'boolean false' => false,
                    'null name' => null,
                    'array name' => [[], ['number name' => -43.0], []],
                ],
                0
            ],
            [JsonReader::STRING, 'string name', 'string value', 1],
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::BOOL, 'boolean true', true, 1],
            [JsonReader::BOOL, 'boolean false', false, 1],
            [JsonReader::NULL, 'null name', null, 1],
            [JsonReader::ARRAY, 'array name', [[], ['number name' => -43.0], []], 1],
            [JsonReader::ARRAY, null, [], 2],
            [JsonReader::END_ARRAY, null, null, 2],
            [JsonReader::OBJECT, null, ['number name' => -43.0], 2],
            [JsonReader::NUMBER, 'number name', -43.0, 3],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::OBJECT, null, [], 2],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::END_ARRAY, 'array name', null, 1],
            [JsonReader::NUMBER, 'number name', 0.44e-2, 1],
            [JsonReader::END_OBJECT, null, null, 0],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);

        foreach ($expecteds as $expected) {
            $reader->read();
            $this->assertSame($expected[0], $reader->type());
            $this->assertSame($expected[1], $reader->name());
            $this->assertSame($expected[2], $reader->value());
            $this->assertSame($expected[3], $reader->depth());
        }
    }

    public function testReadWithOptionFloatAsString()
    {
        $expecteds = [
            [
                JsonReader::OBJECT,
                null,
                [
                    'string name' => 'string value',
                    'number name' => "0.44e-2",
                    'boolean true' => true,
                    'boolean false' => false,
                    'null name' => null,
                    'array name' => [[], ['number name' => "-43.0"], []],
                ],
                0
            ],
            [JsonReader::STRING, 'string name', 'string value', 1],
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::BOOL, 'boolean true', true, 1],
            [JsonReader::BOOL, 'boolean false', false, 1],
            [JsonReader::NULL, 'null name', null, 1],
            [JsonReader::ARRAY, 'array name', [[], ['number name' => "-43.0"], []], 1],
            [JsonReader::ARRAY, null, [], 2],
            [JsonReader::END_ARRAY, null, null, 2],
            [JsonReader::OBJECT, null, ['number name' => "-43.0"], 2],
            [JsonReader::NUMBER, 'number name', "-43.0", 3],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::OBJECT, null, [], 2],
            [JsonReader::END_OBJECT, null, null, 2],
            [JsonReader::END_ARRAY, 'array name', null, 1],
            [JsonReader::NUMBER, 'number name', "0.44e-2", 1],
            [JsonReader::END_OBJECT, null, null, 0],
        ];

        $reader = new JsonReader(JsonReader::FLOATS_AS_STRINGS);
        $reader->init($this->parser);

        foreach ($expecteds as $expected) {
            $reader->read();
            $this->assertSame($expected[0], $reader->type());
            $this->assertSame($expected[1], $reader->name());
            $this->assertSame($expected[2], $reader->value());
            $this->assertSame($expected[3], $reader->depth());
        }
    }

    public function testReadName()
    {
        $expecteds = [
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::NUMBER, 'number name', -43.0, 3],
            [JsonReader::NUMBER, 'number name', 0.44e-2, 1],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        foreach ($expecteds as $expected) {
            $reader->read('number name');
            $this->assertSame($expected[0], $reader->type());
            $this->assertSame($expected[1], $reader->name());
            $this->assertSame($expected[2], $reader->value());
            $this->assertSame($expected[3], $reader->depth());
        }
    }

    public function testStateFollowingReadCompletion()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        while ($reader->read());
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }

    public function testNext()
    {
        $expecteds = [
            [JsonReader::STRING, 'string name', 'string value', 1],
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::BOOL, 'boolean true', true, 1],
            [JsonReader::BOOL, 'boolean false', false, 1],
            [JsonReader::NULL, 'null name', null, 1],
            [JsonReader::ARRAY, 'array name', [[], ['number name' => -43.0], []], 1],
            [JsonReader::NUMBER, 'number name', 0.44e-2, 1],
            [JsonReader::END_OBJECT, null, null, 0],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        foreach ($expecteds as $expected) {
            $this->assertSame($expected[0], $reader->type());
            $this->assertSame($expected[1], $reader->name());
            $this->assertSame($expected[2], $reader->value());
            $this->assertSame($expected[3], $reader->depth());
            $reader->next();
        }
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
        $expecteds = [
            [JsonReader::NUMBER, 'number name', 42, 1],
            [JsonReader::NUMBER, 'number name', 0.44e-2, 1],
        ];

        $reader = $this->reader;
        $reader->init($this->parser);
        $reader->read();
        $reader->read();

        foreach ($expecteds as $expected) {
            $reader->next('number name');
            $this->assertSame($expected[0], $reader->type());
            $this->assertSame($expected[1], $reader->name());
            $this->assertSame($expected[2], $reader->value());
            $this->assertSame($expected[3], $reader->depth());
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
        $this->assertSame(0.44e-2, $reader->value());
        $this->assertSame(1, $reader->depth());
    }

    public function testStateFollowingNextCompletion()
    {
        $reader = $this->reader;
        $reader->init($this->parser);
        while ($reader->next());
        $this->assertSame(0, $reader->depth());
        $this->assertSame(JsonReader::NONE, $reader->type());
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
        $this->assertSame(JsonReader::NONE, $reader->type());
        $this->assertNull($reader->name());
        $this->assertNull($reader->value());
    }
}
