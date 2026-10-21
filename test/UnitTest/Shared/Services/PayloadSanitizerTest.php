<?php

declare(strict_types=1);

namespace UnitTest\Shared\Services;

use BackendBase\Shared\Services\PayloadSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function password_get_info;
use function password_verify;

use const PASSWORD_ARGON2ID;

final class PayloadSanitizerTest extends TestCase
{
    public function testEmptyPayloadProducesAnEmptyArray() : void
    {
        $this->assertSame([], PayloadSanitizer::sanitize(null));
        $this->assertSame([], PayloadSanitizer::sanitize([]));
    }

    public function testStripsTagsAndNullBytesWhilePreservingQuotesInValues() : void
    {
        $this->assertSame([
            'title' => 'İstanbul & "Ankara" \'İzmir\'',
            'a&#34;b' => 'ac',
        ], PayloadSanitizer::sanitize([
            '<b>title</b>' => "<p>İstanbul & \"Ankara\" 'İzmir'</p>\0",
            'a"b' => 'a<b>c</b>',
        ]));
    }

    public function testPreservesScalarTypesAndNumericArrayKeysRecursively() : void
    {
        $payload = [
            'count' => 0,
            'enabled' => false,
            'ratio' => 1.25,
            'missing' => null,
            'items' => [2 => '<b>first</b>', 7 => ['name' => '<i>second</i>']],
        ];
        $expected = $payload;
        $expected['items'] = [2 => 'first', 7 => ['name' => 'second']];

        $this->assertSame($expected, PayloadSanitizer::sanitize($payload));
    }

    public function testSanitizesEmailAddresses() : void
    {
        $this->assertSame(['email' => 'test@example.test'], PayloadSanitizer::sanitize([
            'email' => " t(e)st@example.test\n",
        ]));
    }

    public function testReplacesPlaintextPasswordsWithVerifiableArgon2idHashes() : void
    {
        $result = PayloadSanitizer::sanitize(['password' => 'test-password']);

        $this->assertArrayNotHasKey('password', $result);
        $this->assertSame(PASSWORD_ARGON2ID, $result['passwordHashAlgo']);
        $this->assertSame('argon2id', password_get_info($result['passwordHash'])['algoName']);
        $this->assertTrue(password_verify('test-password', $result['passwordHash']));
        $this->assertFalse(password_verify('wrong-password', $result['passwordHash']));
    }

    public function testAllowsHtmlOnlyAtTheConfiguredNestedPath() : void
    {
        $payload = ['profile' => [
            'name' => '<b>Editor</b>',
            'description' => '<p onclick="alert(1)">Merhaba <a href="javascript:alert(1)">link</a>'
                . '<a href="https://example.test">safe</a></p>',
        ]];

        $this->assertSame(['profile' => [
            'name' => 'Editor',
            'description' => '<p>Merhaba <a>link</a><a href="https://example.test">safe</a></p>',
        ]], PayloadSanitizer::sanitize($payload, [
            '$.profile.description' => ['allowedTags' => 'p;a|href', 'urlPrefixes' => 'https'],
        ]));
    }

    #[DataProvider('legacyTextProvider')]
    public function testPreservesLegacyTextCleaning(string $input, string $expectedKey, string $expectedValue): void
    {
        $this->assertSame([$expectedKey => $expectedValue], PayloadSanitizer::sanitize([$input => $input]));
    }

    public static function legacyTextProvider(): array
    {
        return [
            'empty text' => ['', '', ''],
            'Turkish text and quotes' => [
                'İstanbul & "Ankara" \'İzmir\'',
                'İstanbul & &#34;Ankara&#34; &#39;İzmir&#39;',
                'İstanbul & "Ankara" \'İzmir\'',
            ],
            'existing entities' => ['&amp; &#34; &lt;b&gt;', '&amp; &#34; &lt;b&gt;', '&amp; &#34; &lt;b&gt;'],
            'null bytes' => ["\0<b>\0x</b>\0", 'x', 'x'],
            'HTML comment' => ['<!-- comment -->text', 'text', 'text'],
            'PHP tag' => ['<?php echo "hidden"; ?>text', 'text', 'text'],
            'quoted greater than' => ['<b title="1>2">kept</b>', '2&#34;>kept', 'kept'],
            'space after opening bracket' => ['a < b > c', 'a  c', 'a  c'],
            'tab after opening bracket' => ["a <\tb > c", 'a  c', 'a  c'],
            'newline after opening bracket' => ["a <\nb > c", 'a  c', 'a  c'],
            'unclosed comparison' => ['a < b', 'a ', 'a '],
            'unclosed tag' => ['<b', '', ''],
            'trailing opening bracket' => ['a <', 'a ', 'a '],
            'standalone closing bracket' => ['a > b', 'a > b', 'a > b'],
            'nested malformed tags' => ['<a<<b>>tail', '', ''],
            'unterminated attribute' => ['<b title="unfinished>tail', 'tail', ''],
            'CDATA section' => ['<![CDATA[quoted]]>end', 'end', 'end'],
            'brackets inside quoted attribute' => ['<a title="< b >">x</a>', 'x', 'x'],
        ];
    }
}
