<?php

declare(strict_types=1);

namespace CtrlField\Integrations\Blade;

use Illuminate\View\Compilers\BladeCompiler;

/**
 * Registers @relationship / @endrelationship Blade directives.
 *
 * Usage in a Blade view:
 *
 *     <ul>
 *     (at)relationship('team_members')
 *         <li><a href="{{ get_permalink($post->ID) }}">{{ $post->post_title }}</a></li>
 *     (at)endrelationship
 *     </ul>
 *
 * (at) = the @ sign; written out so PHPDoc does not read it as a tag.
 */
final class RelationshipDirective
{
    public static function register(BladeCompiler $compiler): void
    {
        $compiler->directive('relationship', static function (string $expression): string {
            return "<?php foreach(ctrlfield_get_relationship({$expression}) as \$post): ?>";
        });

        $compiler->directive('endrelationship', static function (): string {
            return '<?php endforeach; ?>';
        });
    }
}
