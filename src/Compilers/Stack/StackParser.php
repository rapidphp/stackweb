<?php

namespace StackWeb\Compilers\Stack;

use StackWeb\Compilers\ApiPhp\ApiPhpParser;
use StackWeb\Compilers\ApiPhp\Tokens\_ApiPhpToken;
use StackWeb\Compilers\CliPhp\CliPhpParser;
use StackWeb\Compilers\CliPhp\Tokens\_CliPhpToken;
use StackWeb\Compilers\Contracts\Parser;
use StackWeb\Compilers\Contracts\Token;
use StackWeb\Compilers\HtmlX\HtmlXParser;
use StackWeb\Compilers\HtmlX\Structs\_HtmlXStruct;
use StackWeb\Compilers\Stack\Structs\_ComponentStruct;
use StackWeb\Compilers\Stack\Structs\_StackStruct;
use StackWeb\Compilers\Stack\Tokens\_ComponentRenderToken;
use StackWeb\Compilers\Stack\Tokens\_ComponentSlotToken;
use StackWeb\Compilers\Stack\Tokens\_ComponentStateToken;
use StackWeb\Compilers\Stack\Tokens\_ComponentToken;
use StackWeb\Compilers\Stack\Tokens\_ImportToken;
use StackWeb\Compilers\StringReader;

class StackParser implements Parser
{

    public _StackStruct $stack;

    public function __construct(
        protected StringReader $string,
        public readonly string $stackName,
        /** @var Token[] */
        protected array $tokens,
    )
    {
    }

    public static function from(StringReader $string, string $stackName)
    {
        $tokenizer = new Tokenizer($string);
        $tokenizer->parse();

        return new static($string, $stackName, $tokenizer->getTokens());
    }



    public function parse() : void
    {
        $this->stack = new Structs\_StackStruct(
            $this->string,
            $this->string->startIndex,
            $this->string->startIndex + $this->string->length,
            $this->stackName,
            [],
            [],
        );

        foreach ($this->tokens as $token)
        {
            if ($token instanceof _ComponentToken)
            {
                $this->stack->componentNames[] = $token->name;
            }
        }

        foreach ($this->tokens as $token)
        {
            if ($token instanceof _ComponentToken)
            {
                if (array_key_exists($token->name ?? '', $this->stack->components))
                {
                    $token->syntaxError("Component [$token->name] is already defined");
                }

                $this->stack->components[$token->name] = $this->parseComponent($token);
            }
            elseif ($token instanceof _ImportToken)
            {
                $this->stack->import($token);
            }
            else
            {
                $token->syntaxError("Unknown token");
            }
        }
    }

    public _ComponentStruct $component;

    public function parseComponent(_ComponentToken $component)
    {
        $this->component = new Structs\_ComponentStruct(
            $component->reader,
            $component->startOffset,
            $component->endOffset,
            $component->name,
        );

        $render = null;
        $props = [];
        $slots = [];
        $states = [];

        foreach ($component->props as $prop)
        {
            if (array_key_exists($prop->name, $props))
            {
                $prop->syntaxError("Prop [$prop->name] is already defined");
            }

            $props[$prop->name] = new Structs\_ComponentPropStruct(
                $prop->reader,
                $prop->startOffset,
                $prop->endOffset,
                $prop->name,
                $prop->default, // TODO
            );
        }

        foreach ($component->tokens as $token)
        {
            if ($token instanceof _ComponentSlotToken)
            {
                if (array_key_exists($token->name, $slots))
                {
                    $token->syntaxError("Slot [$token->name] is already defined");
                }

                $slots[$token->name] = new Structs\_ComponentSlotStruct(
                    $token->reader, $token->startOffset, $token->endOffset,
                    $token->name,
                    is_null($token->default) ? null : $this->parseHtmlX($token, $token->default),
                );
            }
            elseif ($token instanceof _ComponentStateToken)
            {
                if (array_key_exists($token->name, $states))
                {
                    $token->syntaxError("State [$token->name] is already defined");
                }

                $states[$token->name] = new Structs\_ComponentStateStruct(
                    $token->reader, $token->startOffset, $token->endOffset,
                    $token->name,
                    is_null($token->default) ? null : $this->parseValue($token->default),
                );
            }
            elseif ($token instanceof _ComponentRenderToken)
            {
                if (isset($render))
                {
                    $token->syntaxError("Render section is already defined");
                }

                $render = $this->parseHtmlX($token, $token->content);
            }
            else
            {
                $token->syntaxError("Unknown token");
            }
        }

        if (is_null($render))
        {
            $component->syntaxError("Component not contains the render section");
        }

        $this->component->props = $props;
        $this->component->slots = $slots;
        $this->component->states = $states;
        $this->component->render = $render;

        return $this->component;
    }

    public function parseHtmlX(Token $base, array $tokens) : _HtmlXStruct
    {
        $parser = new HtmlXParser($this->stack, $this->component, $base, $tokens);
        $parser->parse();
        return $parser->getStruct();
    }

    public function parseValue(mixed $value)
    {
        if ($value instanceof _ApiPhpToken)
        {
            $parser = new ApiPhpParser($value);
            $parser->parse();
            return $parser->getStruct();
        }
        elseif ($value instanceof _CliPhpToken)
        {
            $parser = new CliPhpParser($value);
            $parser->parse();
            return $parser->getStruct();
        }

        return $value;
    }


    public function getStruct() : Token
    {
        return $this->stack;
    }

}