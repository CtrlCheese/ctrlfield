<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Integrations\Blade;

use CtrlField\Integrations\Blade\FieldHelpers;
use PHPUnit\Framework\TestCase;

/** Shared theme fields: same keys and values, texts changed by chaining. */
final class FieldHelpersTest extends TestCase
{
    public function test_keys_values_and_defaults(): void
    {
        $bg = FieldHelpers::background('light');

        $this->assertSame('background', $bg->getKey());
        $this->assertSame(['no-bg', 'light', 'medium', 'dark'], array_keys($bg->getOptions()));
        $this->assertSame('light', $bg->getDefault());
        $this->assertSame(['2' => '2 columns', '3' => '3 columns'], FieldHelpers::columns(2, 3)->getOptions());
    }

    public function test_texts_can_be_replaced_by_chaining(): void
    {
        $bg = FieldHelpers::background()->label('Fundo')->instructions('Cor de fundo da seção.');

        $this->assertSame('Cor de fundo da seção.', $bg->getInstructions());
        $this->assertSame('Fundo', $bg->getDefinition()['label']);
        $this->assertSame('', FieldHelpers::autoplay()->instructions('')->getInstructions());
    }
}
