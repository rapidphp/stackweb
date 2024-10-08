<?php

namespace StackWeb\Tests\Compilers;

use StackWeb\Compilers\StringReader;
use StackWeb\Compilers\SyntaxError;
use StackWeb\Tests\TestCase;

class StringReaderTest extends TestCase
{

    public function test_read()
    {
        $string = new StringReader("Hello World", 'test');

        $this->assertSame('H', $string->read());
        $this->assertSame('e', $string->read());
        $this->assertSame('ll', $string->read(2));

        $this->assertSame('o', $string->read(silent: true));
        $this->assertSame('o', $string->read());
        $this->assertSame(' ', $string->read());

        $this->assertSame('World', $string->read(10, silent: true));
        $this->assertSame(null, $string->read(10, forceLength: true, silent: true));

        $string->offset = 9999;
        $this->assertSame(null, $string->read());
    }

    public function test_simple_read_while()
    {
        $string = new StringReader("1234,56789", 'test');

        $res = $string->readWhile(
            fn ($value) => is_numeric($value),
            breaker: $breaker,
            broken: $broken,
        );

        $this->assertSame('1234', $res);
        $this->assertSame(4, $string->offset);
        $this->assertSame(',', $breaker);
        $this->assertSame(true, $broken);


        $res = $string->readWhile(fn ($value) => is_numeric($value));
        $this->assertSame('', $res);


        $string->read();

        $res = $string->readWhile(
            fn ($value) => is_numeric($value),
            breaker: $breaker,
            broken: $broken,
        );

        $this->assertSame('56789', $res);
        $this->assertSame(null, $breaker);
        $this->assertSame(false, $broken);
    }

    public function test_read_while_steps()
    {
        $string = new StringReader("12345,9-5!", 'test');

        $res = $string->readWhile(
            fn ($value) => is_numeric($value),
            step: 2,
        );
        $this->assertSame('1234', $res);

        $string->offset = 0;
        $res = $string->readWhile(
            fn ($value) => is_numeric($value[0]),
            step: 2,
        );
        $this->assertSame('12345,9-5!', $res);
    }

    public function test_read_while_jumps()
    {
        $string = new StringReader("12345,9-!", 'test');

        $res = $string->readWhile(
            fn ($value) => is_numeric($value),
            step: 2,
            jump: 1
        );
        $this->assertSame('12345', $res);

        $string->offset = 0;
        $res = $string->readWhile(
            fn ($value) => is_numeric($value[0]),
            jump: 2,
        );
        $this->assertSame('1359', $res);
    }

    public function test_read_while_options()
    {
        $string = new StringReader("12,34", 'test');

        $res = $string->readWhile(
            fn ($value) => is_numeric($value),
            skipBreaker: true,
            includeBreaker: true,
        );
        $this->assertSame('12,', $res);
        $this->assertSame(3, $string->offset);
    }

    public function test_read_while_dont_include()
    {
        $string = new StringReader("12.34,56", 'test');

        $res = $string->readWhile(
            function ($value)
            {
                if (is_numeric($value))
                {
                    return true;
                }
                elseif ($value == '.')
                {
                    return StringReader::DONT_INCLUDE;
                }
                else
                {
                    return false;
                }
            },
        );
        $this->assertSame('1234', $res);
        $this->assertSame(5, $string->offset);
    }

    public function test_read_while_replace_with()
    {
        $string = new StringReader("12.34,56", 'test');

        $res = $string->readWhile(
            function ($value)
            {
                if (is_numeric($value))
                {
                    return true;
                }
                elseif ($value == '.')
                {
                    return [StringReader::REPLACE_WITH, ','];
                }
                else
                {
                    return false;
                }
            },
        );
        $this->assertSame('12,34', $res);
        $this->assertSame(5, $string->offset);
    }


    public function test_simple_read_until()
    {
        $string = new StringReader("1234,56789", 'test');

        $res = $string->readUntil(
            fn ($value) => $value == ',',
            breaker: $breaker,
            broken: $broken,
        );

        $this->assertSame('1234', $res);
        $this->assertSame(4, $string->offset);
        $this->assertSame(',', $breaker);
        $this->assertSame(true, $broken);


        $res = $string->readUntil(fn ($value) => $value == ',');
        $this->assertSame('', $res);


        $string->read();

        $res = $string->readUntil(
            fn ($value) => $value == ',',
            breaker: $breaker,
            broken: $broken,
        );

        $this->assertSame('56789', $res);
        $this->assertSame(null, $breaker);
        $this->assertSame(false, $broken);
    }

    public function test_read_until_dont_include()
    {
        $string = new StringReader("12.34,56", 'test');

        $res = $string->readUntil(
            function ($value)
            {
                if ($value == ',')
                {
                    return true;
                }
                elseif ($value == '.')
                {
                    return StringReader::DONT_INCLUDE;
                }
                else
                {
                    return false;
                }
            },
        );
        $this->assertSame('1234', $res);
        $this->assertSame(5, $string->offset);
    }

    public function test_read_until_replace_with()
    {
        $string = new StringReader("12.34,56", 'test');

        $res = $string->readUntil(
            function ($value)
            {
                if ($value == ',')
                {
                    return true;
                }
                elseif ($value == '.')
                {
                    return [StringReader::REPLACE_WITH, ','];
                }
                else
                {
                    return false;
                }
            },
        );
        $this->assertSame('12,34', $res);
        $this->assertSame(5, $string->offset);
    }

    public function test_silent_process()
    {
        $string = new StringReader("Hello World", 'test');
        $string->offset = 6;

        $read = $string->silent(fn() => $string->read(100));

        $this->assertSame('World', $read);
        $this->assertSame(6, $string->offset);
    }

    public function test_read_escape()
    {
        $string = new StringReader('Hi"', 'test');
        $this->assertSame('Hi', $string->readEscape('"'));

        $string = new StringReader('Hi\\" Foo \\n Bar " Exclude', 'test');
        $this->assertSame('Hi\\" Foo \\n Bar ', $string->readEscape('"'));

        $string->offset = 0;
        $this->assertSame("Hi\" Foo \n Bar ", $string->readEscape('"', translate: true));
    }

    public function test_read_range()
    {
        $string = new StringReader('Range}', 'test');
        $this->assertSame('Range', $string->readRange('{', '}'));

        $string = new StringReader('Range {Deep} }', 'test');
        $this->assertSame('Range {Deep} ', $string->readRange('{', '}'));

        $string = new StringReader('Range {Deep "String}}" } "String}}" }', 'test');
        $this->assertSame('Range {Deep "String}}" } "String}}" ', $string->readRange('{', '}', ['"']));

        // Without escapes:
        $string->offset = 0;
        $this->assertSame('Range {Deep "String}', $string->readRange('{', '}'));

        $string = new StringReader('This {Is "A }\\"} So}"} "Complex } \\"} String} {}" ! {Amazing} }', 'test');
        $this->assertSame('This {Is "A }\\"} So}"} "Complex } \\"} String} {}" ! {Amazing} ', $string->readRange('{', '}', ['"']));
    }

    public function test_read_trig()
    {
        $string = new StringReader('A,B', 'test');
        $this->assertSame('A', $string->readTrig(','));

        $string = new StringReader('A "S,T,R" B,', 'test');
        $this->assertSame('A "S,T,R" B', $string->readTrig(',', ['"']));

        $string->offset = 0;
        $this->assertSame('A "S', $string->readTrig(','));

        $string = new StringReader('Foo { ,, } "," { "," } ,', 'test');
        $this->assertSame('Foo { ,, } "," { "," } ', $string->readTrig(',', ['"'], [['{', '}', ['"']]]));
    }

    public function test_white_spaces()
    {
        $string = new StringReader(" \r\n\tA", 'test');
        $string->readWhiteSpaces();

        $this->assertSame('A', $string->read());
    }

    public function test_read_if()
    {
        $string = new StringReader("Foo Bar", 'test');

        $this->assertSame('F', $string->readIf('F'));
        $this->assertSame(1, $string->offset);

        $this->assertSame('oo', $string->readIf('oo'));
        $this->assertSame(3, $string->offset);

        $this->assertSame(null, $string->readIf('Bar'));
        $this->assertSame(3, $string->offset);

        $this->assertSame(' Bar', $string->readIf(['Foo', ' Bar']));
        $this->assertSame(7, $string->offset);
    }

    public function test_line_detection()
    {
        $string = new StringReader("A\nB\nC", 'test');

        $this->assertSame(1, $string->getLine());

        $string->offset = 1;
        $this->assertSame(1, $string->getLine());

        $string->offset = 2;
        $this->assertSame(2, $string->getLine());

        $string->offset = 9999;
        $this->assertSame(3, $string->getLine());
    }

    public function test_relative_line_detection()
    {
        $string = new StringReader("A\nB", 'test', startLine: 99);

        $string->offset = 2;
        $this->assertSame(100, $string->getLine());
    }

    public function test_syntax_error()
    {
        $string = new StringReader("A\nB", 'test');

        $string->offset = 2;
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage("Syntax Error: Foo in [test] on line 2");

        $string->syntaxError('Foo');
    }

}