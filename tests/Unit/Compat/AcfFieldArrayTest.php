<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Compat;

use CtrlField\Compat\Acf\AcfApi;
use CtrlField\Fields\Field;
use PHPUnit\Framework\TestCase;

/** The field array handed to ACF filters carries the keys ACF always sets (themes read them unchecked). */
final class AcfFieldArrayTest extends TestCase
{
    public function test_post_object_has_multiple_and_allow_null(): void
    {
        $single = AcfApi::fieldArray(Field::postObject('related'));
        $this->assertSame(0, $single['multiple']);
        $this->assertSame(0, $single['allow_null']);

        $this->assertSame(1, AcfApi::fieldArray(Field::postObject('related')->multiple())['multiple']);
    }

    public function test_select_and_taxonomy_have_their_keys(): void
    {
        $this->assertArrayHasKey('multiple', AcfApi::fieldArray(Field::select('theme')->options(['a' => 'A'])));
        $tax = AcfApi::fieldArray(Field::taxonomyTerm('cats')->taxonomy('category'));
        $this->assertSame('category', $tax['taxonomy']);
        $this->assertArrayHasKey('field_type', $tax);
    }

    public function test_other_types_get_no_extra_keys(): void
    {
        $this->assertArrayNotHasKey('multiple', AcfApi::fieldArray(Field::text('title')));
    }
}
