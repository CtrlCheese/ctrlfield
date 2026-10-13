<?php

declare(strict_types=1);

namespace CtrlField\Tests\Unit\Admin\PostTypes;

use CtrlField\Admin\PostTypes\CptMenuFolder;
use CtrlField\Builder\CPT;
use PHPUnit\Framework\TestCase;

final class CptMenuFolderTest extends TestCase
{
    protected function setUp(): void
    {
        CPT::reset();
    }

    protected function tearDown(): void
    {
        CPT::reset();
    }

    public function test_two_or_more_go_in_alphabetically(): void
    {
        CPT::make('project')->label('Project', 'Projects')->register();
        CPT::make('event')->label('Event', 'events')->register();
        CPT::make('testimonial')->label('Testimonial', 'Testimonials')->register();

        self::assertSame(['event', 'project', 'testimonial'], CptMenuFolder::grouped(CPT::all(), true));
    }

    public function test_one_alone_keeps_its_menu(): void
    {
        CPT::make('project')->label('Project', 'Projects')->register();
        CPT::make('log')->label('Log', 'Logs')->showInMenu('tools.php')->register(); // already placed elsewhere

        self::assertSame([], CptMenuFolder::grouped(CPT::all(), true));
    }

    public function test_switched_off(): void
    {
        CPT::make('project')->label('Project', 'Projects')->register();
        CPT::make('event')->label('Event', 'Events')->register();

        self::assertSame([], CptMenuFolder::grouped(CPT::all(), false));
    }
}
