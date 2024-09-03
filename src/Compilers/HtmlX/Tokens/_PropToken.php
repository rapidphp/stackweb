<?php

namespace StackWeb\Compilers\HtmlX\Tokens;

use StackWeb\Compilers\Concerns\TokenTrait;
use StackWeb\Compilers\Contracts\Token;
use StackWeb\Compilers\StringReader;

readonly class _PropToken implements Token
{
    use TokenTrait;

    public function __construct(
        public StringReader $reader,
        public int $startOffset,
        public int $endOffset,

        public string|_PropValue $name,
        public string|true|_PropValue $value,
    )
    {
    }

}