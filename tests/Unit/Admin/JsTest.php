<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin;

use CtrlField\Admin\Js;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsTest extends TestCase
{
    /** @return iterable<string, array{mixed}> */
    public static function attacks(): iterable
    {
        yield 'entity quote'     => ['x&quot;+alert(1)+&quot;'];
        yield 'entity apostrophe' => ["x&#39;+alert(1)+&#39;"];
        yield 'raw quotes'       => ['x"\'><img src=x onerror=alert(1)>'];
        yield 'script end'       => ['</script><script>alert(1)</script>'];
        yield 'html comment'     => ['<!--<script>'];
        yield 'backslashes'      => ['\\";alert(1);//'];
        yield 'nested'           => [['a' => ['b' => 'x&quot;}];alert(1);//']]];
        yield 'invalid utf-8'    => ["x\xB1\x31"];
    }

    #[DataProvider('attacks')]
    public function test_the_browser_reads_back_the_same_value(mixed $value): void
    {
        $attr = Js::attr($value);

        // What the HTML parser hands to Alpine: entities decoded once.
        $js = html_entity_decode($attr, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $this->assertSame(Js::json($value), $js);
        $this->assertSame(json_decode(Js::json($value), true), json_decode($js, true));
        $this->assertDoesNotMatchRegularExpression('/[&<>\']/', $js, 'no character that HTML could reinterpret');
        $this->assertSame(substr_count($js, '"'), substr_count(Js::json($value), '"'), 'every quote is one of JSON\'s own');
    }

    public function test_an_attribute_holds_no_raw_quote(): void
    {
        $this->assertStringNotContainsString('"', Js::attr(['k' => 'v"']));
        $this->assertStringNotContainsString("'", Js::attr("it's"));
    }

    public function test_json_is_safe_inside_a_script_block(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('</script', Js::json('</script><script>'));
        $this->assertStringNotContainsString('<!--', Js::json('<!--'));
    }
}
