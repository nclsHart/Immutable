<?php
declare(strict_types = 1);

namespace Tests\Innmind\Immutable;

use Innmind\Immutable\{
    Str as S,
    PrimitiveInterface,
    StringableInterface,
    Sequence,
    Map,
    Exception\SubstringException,
    Exception\RegexException
};
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{
    public function testInterfaces()
    {
        $str = S::of('foo');

        $this->assertSame('foo', (string) $str);
    }

    public function testOf()
    {
        $str = S::of('foo', 'ASCII');

        $this->assertInstanceOf(S::class, $str);
        $this->assertSame('foo', (string) $str);
        $this->assertSame('ASCII', (string) $str->encoding());
    }

    public function testThrowWhenInvalidType()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must be of the type string, int given');

        S::of(42);
    }

    public function testEncoding()
    {
        $this->assertInstanceOf(S::class, S::of('')->encoding());
        $this->assertSame('UTF-8', (string) S::of('')->encoding());
    }

    public function testToEncoding()
    {
        $str = S::of('foo🙏bar');
        $str2 = $str->toEncoding('ASCII');

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('UTF-8', (string) $str->encoding());
        $this->assertSame('ASCII', (string) $str2->encoding());
        $this->assertSame(7, $str->length());
        $this->assertSame(10, $str2->length());
    }

    public function testSplit()
    {
        $str = S::of('foo');

        $sequence = $str->split();
        $this->assertInstanceOf(Sequence::class, $sequence);
        $this->assertSame(S::class, (string) $sequence->type());
        $this->assertCount(3, $sequence);

        foreach ($sequence as $part) {
            $this->assertInstanceOf(S::class, $part);
        }

        $this->assertSame('f', (string) $sequence->get(0));
        $this->assertSame('o', (string) $sequence->get(1));
        $this->assertSame('o', (string) $sequence->get(2));

        $parts = S::of('🤩👍🤔', 'UTF-8')->split();

        $this->assertSame('🤩', (string) $parts->get(0));
        $this->assertSame('👍', (string) $parts->get(1));
        $this->assertSame('🤔', (string) $parts->get(2));
        $this->assertNotSame(
            '🤩',
            (string) S::of('🤩👍🤔', 'ASCII')->split()->get(0)
        );

        $sequence = $str->split('');
        $this->assertInstanceOf(Sequence::class, $sequence);
        $this->assertSame(S::class, (string) $sequence->type());
        $this->assertCount(3, $sequence);

        foreach ($sequence as $part) {
            $this->assertInstanceOf(S::class, $part);
        }

        $this->assertSame('f', (string) $sequence->get(0));
        $this->assertSame('o', (string) $sequence->get(1));
        $this->assertSame('o', (string) $sequence->get(2));

        $str = S::of('f|o|o');
        $sequence = $str->split('|');
        $this->assertInstanceOf(Sequence::class, $sequence);
        $this->assertSame(S::class, (string) $sequence->type());
        $this->assertCount(3, $sequence);

        foreach ($sequence as $part) {
            $this->assertInstanceOf(S::class, $part);
        }

        $this->assertSame('f', (string) $sequence->get(0));
        $this->assertSame('o', (string) $sequence->get(1));
        $this->assertSame('o', (string) $sequence->get(2));
    }

    public function testSplitOnZeroString()
    {
        $parts = S::of('10101')->split('0');

        $this->assertCount(3, $parts);
        $this->assertSame('1', (string) $parts->get(0));
        $this->assertSame('1', (string) $parts->get(1));
        $this->assertSame('1', (string) $parts->get(2));
    }

    public function testSplitUtf8ManipulatedAsAscii()
    {
        $str = S::of('foo🙏bar');
        $splits = $str->split();

        $this->assertSame('f', (string) $splits->get(0));
        $this->assertSame('o', (string) $splits->get(1));
        $this->assertSame('o', (string) $splits->get(2));
        $this->assertSame('🙏', (string) $splits->get(3));
        $this->assertSame('b', (string) $splits->get(4));
        $this->assertSame('a', (string) $splits->get(5));
        $this->assertSame('r', (string) $splits->get(6));

        $splits = $str->toEncoding('ASCII')->split();

        $this->assertSame('f', (string) $splits->get(0));
        $this->assertSame('o', (string) $splits->get(1));
        $this->assertSame('o', (string) $splits->get(2));
        $this->assertSame(
            '🙏',
            $splits->get(3).$splits->get(4).$splits->get(5).$splits->get(6)
        );
        $this->assertSame('b', (string) $splits->get(7));
        $this->assertSame('a', (string) $splits->get(8));
        $this->assertSame('r', (string) $splits->get(9));
    }

    public function testSplitUtf8ManipulatedAsAsciiWithDelimiter()
    {
        $str = S::of('foo🙏bar');
        $splits = $str->split('🙏');

        $this->assertSame('foo', (string) $splits->get(0));
        $this->assertSame('bar', (string) $splits->get(1));

        $splits = $str->toEncoding('ASCII')->split('🙏');

        $this->assertSame('foo', (string) $splits->get(0));
        $this->assertSame('bar', (string) $splits->get(1));

        $splits = $str->toEncoding('ASCII')->split(
            mb_substr('🙏', 0, 1, 'ASCII')
        );

        $this->assertSame('foo', (string) $splits->get(0));
        $this->assertSame(
            mb_substr('🙏', 1, null, 'ASCII').'bar',
            (string) $splits->get(1)
        );
    }

    public function testChunk()
    {
        $str = S::of('foobarbaz');

        $sequence = $str->chunk(4);
        $this->assertInstanceOf(Sequence::class, $sequence);
        $this->assertSame(S::class, (string) $sequence->type());
        $this->assertInstanceOf(S::class, $sequence->get(0));
        $this->assertInstanceOf(S::class, $sequence->get(1));
        $this->assertInstanceOf(S::class, $sequence->get(2));
        $this->assertSame('foob', (string) $sequence->get(0));
        $this->assertSame('arba', (string) $sequence->get(1));
        $this->assertSame('z', (string) $sequence->get(2));
    }

    public function testChunkUtf8ManipulatedAsAscii()
    {
        $splits = S::of('foo🙏bar')
            ->toEncoding('ASCII')
            ->chunk();

        $this->assertSame('f', (string) $splits->get(0));
        $this->assertSame('o', (string) $splits->get(1));
        $this->assertSame('o', (string) $splits->get(2));
        $this->assertSame(
            '🙏',
            $splits->get(3).$splits->get(4).$splits->get(5).$splits->get(6)
        );
        $this->assertSame('b', (string) $splits->get(7));
        $this->assertSame('a', (string) $splits->get(8));
        $this->assertSame('r', (string) $splits->get(9));

        $splits = S::of('foo🙏bar')
            ->toEncoding('ASCII')
            ->chunk(3);

        $this->assertSame('foo', (string) $splits->get(0));
        $this->assertSame(
            mb_substr('🙏', 0, 3, 'ASCII'),
            (string) $splits->get(1)
        );
        $this->assertSame(
            mb_substr('🙏', 3, 4, 'ASCII').'ba',
            (string) $splits->get(2)
        );
        $this->assertSame('r', (string) $splits->get(3));
    }

    public function testPosition()
    {
        $str = S::of('foo');

        $this->assertSame(1, $str->position('o'));
        $this->assertSame(2, $str->position('o', 2));

        $emoji = S::of('foo🙏bar');

        $this->assertSame(4, $emoji->position('bar'));
        $this->assertSame(7, $emoji->toEncoding('ASCII')->position('bar'));
    }

    public function testThrowWhenPositionNotFound()
    {
        $this->expectException(SubstringException::class);
        $this->expectExceptionMessage('Substring "o" not found');

        S::of('bar')->position('o');
    }

    public function testReplace()
    {
        $str = S::of('<body text="%body%">');

        $str2 = $str->replace('%body%', 'black');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('<body text="black">', (string) $str2);
        $this->assertSame('<body text="%body%">', (string) $str);

        $this->assertSame('foo', (string) S::of('foo')->replace('.', '/'));
        $this->assertSame('foo/bar', (string) S::of('foo.bar')->replace('.', '/'));
    }

    public function testReplaceWithDifferentEncoding()
    {
        $str = S::of('foo🙏🙏🙏bar');

        $str2 = $str->replace(
            mb_substr('🙏', 0, 1, 'ASCII'),
            'baz'
        );
        $remaining = mb_substr('🙏', 1, null, 'ASCII');
        $this->assertSame('foo🙏🙏🙏bar', (string) $str);
        $this->assertSame(
            'foobaz'.$remaining.'baz'.$remaining.'baz'.$remaining.'bar',
            (string) $str2
        );

        $str3 = $str->toEncoding('ASCII')->replace(
            mb_substr('🙏', 0, 1, 'ASCII'),
            'baz'
        );
        $this->assertSame('foo🙏🙏🙏bar', (string) $str);
        $subPray = mb_substr('🙏', 1, null, 'ASCII');
        $this->assertSame(
            'foobaz'.$subPray.'baz'.$subPray.'baz'.$subPray.'bar',
            (string) $str3
        );
    }

    public function testStr()
    {
        $str = S::of('name@example.com');

        $str2 = $str->str('@');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('@example.com', (string) $str2);
        $this->assertSame('name@example.com', (string) $str);
    }

    public function testStrUtf8ManipulatedAsAscii()
    {
        $str = S::of('foo🙏bar');

        $str2 = $str->toEncoding('ASCII')->str(mb_substr('🙏', 0, 1, 'ASCII'));
        $this->assertSame('foo🙏bar', (string) $str);
        $this->assertSame('🙏bar', (string) $str2);
    }

    public function testThrowWhenStrDelimiterNotFound()
    {
        $this->expectException(SubstringException::class);
        $this->expectExceptionMessage('Substring "foo" not found');

        S::of('name@example.com')->str('foo');
    }

    public function testToUpper()
    {
        $str = S::of('foo🙏');

        $str2 = $str->toUpper();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('FOO🙏', (string) $str2);
        $this->assertSame('foo🙏', (string) $str);
        $this->assertSame('ÉGÉRIE', (string) S::of('égérie')->toUpper());
    }

    public function testToLower()
    {
        $str = S::of('FOO🙏');

        $str2 = $str->toLower();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo🙏', (string) $str2);
        $this->assertSame('FOO🙏', (string) $str);
        $this->assertSame('égérie', (string) S::of('ÉGÉRIE')->toLower());
    }

    public function testLength()
    {
        $this->assertSame(4, S::of('foo🙏')->length());
        $this->assertSame(7, S::of('foo🙏')->toEncoding('ASCII')->length());
    }

    public function testEmpty()
    {
        $this->assertTrue(S::of('')->empty());
        $this->assertFalse(S::of('🙏')->empty());
        $this->assertFalse(S::of('🙏', 'ASCII')->substring(0, 1)->empty());
    }

    public function testReverse()
    {
        $str = S::of('foo🙏');

        $str2 = $str->reverse();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('🙏oof', (string) $str2);
        $this->assertSame('foo🙏', (string) $str);
        $this->assertSame(
            strrev('🙏').'oof',
            (string) $str->toEncoding('ASCII')->reverse()
        );
    }

    public function testPad()
    {
        $str = S::of('foo');

        $str2 = $str->rightPad(6, '0');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo000', (string) $str2);
        $this->assertSame('foo', (string) $str);

        $str2 = $str->leftPad(6, '0');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('000foo', (string) $str2);
        $this->assertSame('foo', (string) $str);

        $str2 = $str->uniPad(6, '0');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('0foo00', (string) $str2);
        $this->assertSame('foo', (string) $str);
    }

    public function testCspn()
    {
        $str = S::of('abcdhelloabcd');

        $this->assertSame(0, $str->cspn('abcd'));
        $this->assertSame(5, $str->cspn('abcd', -9));
        $this->assertSame(4, $str->cspn('abcd', -9, -5));

        $str = S::of('foo🙏bar');

        $this->assertSame(3, $str->cspn('🙏'));
        $this->assertSame(0, $str->cspn('🙏', 4));
        $this->assertSame(3, $str->cspn('🙏', 0, 4));
        $this->assertSame(3, $str->cspn(mb_substr('🙏', 0, 1, 'ASCII'), 0, 4));
        $this->assertSame(3, $str->toEncoding('ASCII')->cspn(mb_substr('🙏', 0, 1, 'ASCII'), 0, 4));
    }

    public function testRepeat()
    {
        $str = S::of('foo');

        $str2 = $str->repeat(3);
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foofoofoo', (string) $str2);
        $this->assertSame('foo', (string) $str);
        $this->assertSame('🙏🙏', (string) S::of('🙏')->repeat(2));
        $this->assertSame('🙏🙏', (string) S::of('🙏')->toEncoding('ASCII')->repeat(2));
    }

    public function testShuffle()
    {
        $str = S::of('shuffle🙏');

        $str2 = $str->shuffle();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('shuffle🙏', (string) $str);
        $this->assertSame(8, $str2->length());

        try {
            foreach ($str2->split() as $char) {
                $str->position((string) $char);
            }
        } catch (\Exception $e) {
            $this->fail('every character should be in the original string');
        }
    }

    public function testShuffleEmoji()
    {
        $str = S::of('🙏');

        $this->assertSame('🙏', (string) $str->shuffle());
        $this->assertNotSame(
            '🙏',
            (string) $str->toEncoding('ASCII')->shuffle()
        );
    }

    public function testStripSlashes()
    {
        $str = S::of("Is your name O\'reilly?");

        $str2 = $str->stripSlashes();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame("Is your name O\'reilly?", (string) $str);
        $this->assertSame("Is your name O'reilly?", (string) $str2);
    }

    public function testStripCSlahes()
    {
        $str = S::of('He\xallo');

        $str2 = $str->stripCSlashes();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('He\xallo', (string) $str);
        $this->assertSame('He' . "\n" . 'llo', (string) $str2);
    }

    public function testWordCount()
    {
        $str = S::of("Hello fri3nd, you're
                    looking          good today!");

        $this->assertSame(7, $str->wordCount());
        $this->assertSame(6, $str->wordCount('àáãç3'));
    }

    public function testWords()
    {
        $str = S::of("Hello fri3nd, you're
        looking          good today!");

        $map = $str->words();
        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame('int', (string) $map->keyType());
        $this->assertSame(S::class, (string) $map->valueType());
        $words = [
            0 => 'Hello',
            6 => 'fri',
            10 => 'nd',
            14 => 'you\'re',
            29 => 'looking',
            46 => 'good',
            51 => 'today',
        ];

        foreach ($words as $pos => $word) {
            $this->assertInstanceOf(S::class, $map->get($pos));
            $this->assertSame($word, (string) $map->get($pos));
        }

        $map = $str->words('àáãç3');
        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame('int', (string) $map->keyType());
        $this->assertSame(S::class, (string) $map->valueType());
        $words = [
            0 => 'Hello',
            6 => 'fri3nd',
            14 => 'you\'re',
            29 => 'looking',
            46 => 'good',
            51 => 'today',
        ];

        foreach ($words as $pos => $word) {
            $this->assertInstanceOf(S::class, $map->get($pos));
            $this->assertSame($word, (string) $map->get($pos));
        }
    }

    public function testPregSplit()
    {
        $str = S::of('hypertext language, programming');

        $c = $str->pregSplit('/[\s,]+/');
        $this->assertInstanceOf(Sequence::class, $c);
        $this->assertSame(S::class, (string) $c->type());
        $this->assertSame('hypertext', (string) $c->get(0));
        $this->assertSame('language', (string) $c->get(1));
        $this->assertSame('programming', (string) $c->get(2));
    }

    public function testMatches()
    {
        $str = S::of('abcdef');

        $this->assertFalse($str->matches('/^def/'));
        $this->assertTrue($str->matches('/^abc/'));

        $this->assertTrue(S::of('foo🙏bar')->matches('/🙏/'));
        $this->assertTrue(S::of('foo🙏bar')->toEncoding('ASCII')->matches('/🙏/'));
    }

    public function testThrowWhenMatchInvalidRegex()
    {
        $this->expectException(RegexException::class);
        $this->expectExceptionMessage('Backtrack limit error');

        S::of(str_repeat("x", 1000000))->matches('/x+x+y/');
    }

    public function testCapture()
    {
        $str = S::of('http://www.php.net/index.html');

        $map = $str->capture('@^(?:http://)?(?P<host>[^/]+)@i');
        $this->assertInstanceOf(Map::class, $map);
        $this->assertSame('scalar', (string) $map->keyType());
        $this->assertSame(S::class, (string) $map->valueType());
        $this->assertCount(3, $map);
        $this->assertSame('http://www.php.net', (string) $map->get(0));
        $this->assertSame('www.php.net', (string) $map->get(1));
        $this->assertSame('www.php.net', (string) $map->get('host'));
    }

    public function testCastNullValuesWhenCapturing()
    {
        $str = S::of('en;q=0.7');

        $matches = $str->capture('~(?<lang>([a-zA-Z0-9]+(-[a-zA-Z0-9]+)*|\*))(; ?q=(?<quality>\d+(\.\d+)?))?~');
        $this->assertInstanceOf(Map::class, $matches);
        $this->assertSame('scalar', (string) $matches->keyType());
        $this->assertSame(S::class, (string) $matches->valueType());
        $this->assertCount(9, $matches);
        $this->assertSame('en;q=0.7', (string) $matches->get(0));
        $this->assertSame('en', (string) $matches->get(1));
        $this->assertSame('en', (string) $matches->get(2));
        $this->assertSame('', (string) $matches->get(3));
        $this->assertSame('en', (string) $matches->get('lang'));
        $this->assertSame(';q=0.7', (string) $matches->get(4));
        $this->assertSame('0.7', (string) $matches->get(5));
        $this->assertSame('0.7', (string) $matches->get('quality'));
        $this->assertSame('.7', (string) $matches->get(6));
    }

    public function testThrowWhenGettingMatchesInvalidRegex()
    {
        $this->expectException(RegexException::class);
        $this->expectExceptionMessage('Backtrack limit error');

        S::of(str_repeat("x", 1000000))->capture('/x+x+y/');
    }

    public function testPregReplace()
    {
        $str = S::of('April 15, 2003');

        $str2 = $str->pregReplace('/(\w+) (\d+), (\d+)/i', '${1}1,$3');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('April1,2003', (string) $str2);
        $this->assertSame('April 15, 2003', (string) $str);
    }

    public function testSubstring()
    {
        $str = S::of('foobarbaz');

        $str2 = $str->substring(3);
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('barbaz', (string) $str2);
        $this->assertSame('foobarbaz', (string) $str);

        $str3 = $str->substring(3, 3);
        $this->assertInstanceOf(S::class, $str3);
        $this->assertNotSame($str, $str3);
        $this->assertSame('bar', (string) $str3);
        $this->assertSame('foobarbaz', (string) $str);

        $str4 = ($str = S::of(''))->substring(0, -1);

        $this->assertSame($str, $str4);
    }

    public function testTake()
    {
        $str = S::of('foobarbaz');

        $str2 = $str->take(3);

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo', (string) $str2);
        $this->assertSame('foobarbaz', (string) $str);
    }

    public function testTakeEnd()
    {
        $str = S::of('foobarbaz');

        $str2 = $str->takeEnd(3);

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('baz', (string) $str2);
        $this->assertSame('foobarbaz', (string) $str);
    }

    public function testDrop()
    {
        $str = S::of('foobarbaz');

        $str2 = $str->drop(3);

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('barbaz', (string) $str2);
        $this->assertSame('foobarbaz', (string) $str);
    }

    public function testDropEnd()
    {
        $str = S::of('foobarbaz');

        $str2 = $str->dropEnd(3);

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foobar', (string) $str2);
        $this->assertSame('foobarbaz', (string) $str);
    }

    public function testSubstringUtf8ManipulatedAsAscii()
    {
        $str = S::of('foo🙏bar')->toEncoding('ASCII');

        $this->assertSame('🙏bar', (string) $str->substring(3));
        $this->assertSame('🙏', (string) $str->substring(3, 4));
        $this->assertSame(
            mb_substr('🙏', 0, 1, 'ASCII'),
            (string) $str->substring(3, 1)
        );
    }

    public function testSprintf()
    {
        $str = S::of('foo %s baz');

        $str2 = $str->sprintf('bar');
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo bar baz', (string) $str2);
        $this->assertSame('foo %s baz', (string) $str);
    }

    public function testUcfirst()
    {
        $str = S::of('foo');

        $str2 = $str->ucfirst();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo', (string) $str);
        $this->assertSame('Foo', (string) $str2);
        $this->assertSame('🙏', (string) S::of('🙏')->ucfirst());
        $this->assertSame('Égérie', (string) S::of('égérie')->ucfirst());
    }

    public function testLcfirst()
    {
        $str = S::of('FOO');

        $str2 = $str->lcfirst();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('FOO', (string) $str);
        $this->assertSame('fOO', (string) $str2);
        $this->assertSame('🙏', (string) S::of('🙏')->lcfirst());
        $this->assertSame('éGÉRIE', (string) S::of('ÉGÉRIE')->lcfirst());
    }

    public function testCamelize()
    {
        $str = S::of('foo_bar baz');

        $str2 = $str->camelize();
        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo_bar baz', (string) $str);
        $this->assertSame('FooBarBaz', (string) $str2);
    }

    public function testAppend()
    {
        $str = S::of('foo');

        $str2 = $str->append(' bar');
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo', (string) $str);
        $this->assertSame('foo bar', (string) $str2);
    }

    public function testPrepend()
    {
        $str = S::of('foo');

        $str2 = $str->prepend('baz ');
        $this->assertNotSame($str, $str2);
        $this->assertSame('foo', (string) $str);
        $this->assertSame('baz foo', (string) $str2);
    }

    public function testEquals()
    {
        $this->assertTrue(S::of('foo')->equals(S::of('foo')));
        $this->assertFalse(S::of('foo')->equals(S::of('fo')));
    }

    public function testTrim()
    {
        $str = S::of(' foo ');
        $str2 = $str->trim();

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame(' foo ', (string) $str);
        $this->assertSame('foo', (string) $str2);
        $this->assertSame('f', (string) $str2->trim('o'));
    }

    public function testRightTrim()
    {
        $str = S::of(' foo ');
        $str2 = $str->rightTrim();

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame(' foo ', (string) $str);
        $this->assertSame(' foo', (string) $str2);
        $this->assertSame(' f', (string) $str2->rightTrim('o'));
    }

    public function testLeftTrim()
    {
        $str = S::of(' foo ');
        $str2 = $str->leftTrim();

        $this->assertInstanceOf(S::class, $str2);
        $this->assertNotSame($str, $str2);
        $this->assertSame(' foo ', (string) $str);
        $this->assertSame('foo ', (string) $str2);
        $this->assertSame('oo ', (string) $str2->leftTrim('f'));
    }

    public function testContains()
    {
        $str = S::of('foobar');

        $this->assertTrue($str->contains('foo'));
        $this->assertTrue($str->contains('bar'));
        $this->assertFalse($str->contains('baz'));
    }

    public function testStartsWith()
    {
        $str = S::of('foobar');

        $this->assertTrue($str->startsWith(''));
        $this->assertTrue($str->startsWith('foo'));
        $this->assertTrue($str->startsWith('foob'));
        $this->assertTrue($str->startsWith('foobar'));
        $this->assertFalse($str->startsWith('bar'));
        $this->assertFalse($str->startsWith('oobar'));
        $this->assertFalse($str->startsWith('foobar '));
    }

    public function testEndsWith()
    {
        $str = S::of('foobar');

        $this->assertTrue($str->endsWith(''));
        $this->assertTrue($str->endsWith('bar'));
        $this->assertTrue($str->endsWith('obar'));
        $this->assertTrue($str->endsWith('foobar'));
        $this->assertFalse($str->endsWith('foo'));
        $this->assertFalse($str->endsWith('fooba'));
        $this->assertFalse($str->endsWith('xfoobar'));
    }

    public function testPregQuote()
    {
        $a = S::of('foo#bar.*');
        $b = $a->pregQuote();
        $c = $a->pregQuote('o');

        $this->assertInstanceOf(S::class, $b);
        $this->assertInstanceOf(S::class, $c);
        $this->assertSame('foo#bar.*', (string) $a);
        $this->assertSame('foo\#bar\.\*', (string) $b);
        $this->assertSame('f\o\o\#bar\.\*', (string) $c);
    }
}
