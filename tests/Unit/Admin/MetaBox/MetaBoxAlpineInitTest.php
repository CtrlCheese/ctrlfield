<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\MetaBox;

use CtrlField\Admin\MetaBox\MetaBoxRenderer;
use CtrlField\Builder\FieldGroup;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

/**
 * The meta box hands the stored values to Alpine in x-data. An editor's text
 * containing "&quot;" must stay text: WordPress' esc_attr() keeps entities it
 * finds, so the browser would read a quote and run what follows.
 */
final class MetaBoxAlpineInitTest extends TestCase
{
    public function test_stored_entities_cannot_close_the_json_string(): void
    {
        $value = 'x&quot;+alert(1)+&quot;&#39;</script>';
        $group = FieldGroup::make('xss')->fields([Field::text('title')]);

        $method = new \ReflectionMethod(MetaBoxRenderer::class, 'alpineInit');
        $attr   = (string) $method->invoke(new MetaBoxRenderer(), ['title' => $value], [], [$group]);
        $js     = html_entity_decode($attr, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // what Alpine evaluates

        $this->assertStringNotContainsString('"', $attr, 'no raw quote ends the attribute');
        $this->assertSame(1, preg_match('/values: (\{.*?\}), conditions:/', $js, $m));
        $this->assertSame(['title' => $value], json_decode($m[1], true), 'the value comes back as the same text');
        $this->assertStringNotContainsString('&', $m[1], 'no entity left for the browser to decode');
    }
}
