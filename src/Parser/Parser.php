<?php declare(strict_types = 1);

namespace JsonReader\Parser;

//TODO: Everything.
class Parser implements \IteratorAggregate
{

    /**
     * @var \Traversable
     */
    private $lexer;

    /**
     * @var \Iterator Iterator provided by the $lexer, which might be the lexer itself.
     */
    private $tokenIterator;

    public function __construct(Tokenizer $lexer)
    {
        $this->lexer = $lexer;
    }

    public function getIterator()
    {
        $this->initLexer();
        $iterator = $this->tokenIterator;

        while ($iterator->valid()) {

        }
    }

    private function initLexer()
    {
        $lexer = $this->lexer;

        /** @var \Iterator $iterator */
        $iterator = ($lexer instanceof \IteratorAggregate) ? $lexer->getIterator() : $lexer;
        $iterator->rewind();
        $this->tokenIterator = $iterator;
    }
}