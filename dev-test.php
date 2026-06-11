<?php
/**
 * FieldForge — Local development test registrations.
 *
 * This file registers a sample CPT, taxonomy, options page, and field group
 * so every Cycle 6 acceptance criterion can be verified visually and via CLI.
 *
 * HOW TO ENABLE
 * Add this one line at the bottom of fieldforge.php (before the closing tag):
 *
 *   if ( defined('FIELDFORGE_DEV_TEST') && FIELDFORGE_DEV_TEST ) {
 *       require_once __DIR__ . '/dev-test.php';
 *   }
 *
 * Then define the constant from wp-config.php or from wp-env's "config" block:
 *   define( 'FIELDFORGE_DEV_TEST', true );
 *
 * NEVER load this file in production.
 */

declare(strict_types=1);

use FieldForge\Builder\CPT;
use FieldForge\Builder\OptionsPage;
use FieldForge\Builder\Taxonomy;
use FieldForge\Fields\Field;
use FieldForge\Builder\FieldGroup;

add_action('init', static function (): void {

    // ------------------------------------------------------------------
    // 1. Custom Post Type — "Portfolio"
    // ------------------------------------------------------------------
    CPT::make('portfolio')
        ->label('Portfolio', 'Portfolios')
        ->menuIcon('dashicons-portfolio')
        ->supports(['title', 'editor', 'thumbnail'])
        ->register();

    // ------------------------------------------------------------------
    // 2. Taxonomy — "Portfolio Category"
    //    Attached to the CPT above.
    //    Has two term-level fields (text + select).
    // ------------------------------------------------------------------
    Taxonomy::make('portfolio_cat')
        ->label('Portfolio Category', 'Portfolio Categories')
        ->attachTo(['portfolio'])
        ->hierarchical(true)
        ->termFields([
            Field::text('color_hex')->label('Brand Color (hex)'),
            Field::select('display_style')
                ->label('Display Style')
                ->options([
                    'grid' => 'Grid',
                    'list' => 'List',
                    'hero' => 'Hero Banner',
                ]),
        ])
        ->register();

    // ------------------------------------------------------------------
    // 3. Field Group — attached to the "portfolio" post type
    //    (rendered in the meta box — visible after Cycle 7)
    // ------------------------------------------------------------------
    FieldGroup::make('portfolio_details')
        ->title('Portfolio Details')
        ->where('post_type', '==', 'portfolio')
        ->fields([
            Field::text('client_name')
                ->label('Client Name')
                ->required()
                ->setIndex(true),

            Field::select('project_type')
                ->label('Project Type')
                ->options([
                    'web'    => 'Web Design',
                    'brand'  => 'Branding',
                    'print'  => 'Print',
                ]),

            Field::text('client_contact')
                ->label('Client Contact Email')
                ->visibleWhen('project_type', '==', 'web'),

            Field::number('year_completed')
                ->label('Year Completed'),

            Field::image('featured_screenshot')
                ->label('Featured Screenshot'),

            Field::repeater('team_members')
                ->label('Team Members')
                ->fields([
                    Field::text('member_name')->label('Name'),
                    Field::text('member_role')->label('Role'),
                ]),
        ])
        ->register();

    // ------------------------------------------------------------------
    // 4. Options Page — "Agency Settings" (top-level menu)
    // ------------------------------------------------------------------
    OptionsPage::make('agency_settings')
        ->title('Agency Settings')
        ->menuSlug('agency-settings')
        ->icon('dashicons-admin-settings')
        ->position(75)
        ->fields([
            Field::text('agency_name')->label('Agency Name'),
            Field::email('agency_email')->label('Contact Email'),
            Field::url('agency_website')->label('Website URL'),
            Field::image('agency_logo')->label('Agency Logo'),
            Field::select('default_currency')
                ->label('Default Currency')
                ->options([
                    'usd' => 'USD — US Dollar',
                    'eur' => 'EUR — Euro',
                    'gbp' => 'GBP — British Pound',
                ]),
        ])
        ->register();

    // ------------------------------------------------------------------
    // 5. Sub-menu Options Page — "Agency > Social Links"
    // ------------------------------------------------------------------
    OptionsPage::make('agency_social')
        ->title('Social Links')
        ->menuSlug('agency-social')
        ->parent('agency-settings')
        ->fields([
            Field::url('twitter_url')->label('Twitter / X'),
            Field::url('linkedin_url')->label('LinkedIn'),
            Field::url('instagram_url')->label('Instagram'),
        ])
        ->register();

    // ------------------------------------------------------------------
    // 6. Options Page — "Testimonials" (sub-menu under FieldForge)
    //    Theme reads data via: fieldforge_get_options('key', 'testimonials')
    // ------------------------------------------------------------------
    OptionsPage::make('testimonials')
        ->title('Testimonials')
        ->menuSlug('fieldforge-testimonials')
        ->parent('fieldforge')
        ->fields([
            Field::repeater('testimonials_list')
                ->label('Testimonials')
                ->fields([
                    Field::text('name')->label('Name')->required(),
                    Field::text('role')->label('Role / Company'),
                    Field::textarea('quote')->label('Quote')->required(),
                    Field::image('photo')->label('Photo'),
                    Field::number('rating')
                        ->label('Rating (1–5)')
                        ->default(5),
                ]),
        ])
        ->register();

    // ------------------------------------------------------------------
    // 7. Pro — Clone Field demo (only when FieldForge Pro is active)
    //    Registers a library group + a consumer group showing both
    //    display modes (seamless and group) side by side.
    // ------------------------------------------------------------------
    if (\FieldForge\Registry\FeatureRegistry::has('clone')) {

        // Library group — no ->where() conditions, never shown on any screen.
        // Available for cloning only.
        FieldGroup::make('contact_info_fields')
            ->title('Contact Info Fields')
            ->fields([
                Field::text('full_name')->label('Full Name'),
                Field::email('email')->label('Email'),
                Field::text('phone')->label('Phone'),
            ])
            ->register();

        // Consumer group — attached to portfolio.
        // Shows two clones of the same library group:
        //   • client_contact  → seamless mode → flat keys: client_full_name, client_email, client_phone
        //   • agency_contact  → group mode    → nested key: agency_contact.full_name / .email / .phone
        FieldGroup::make('portfolio_contacts')
            ->title('Portfolio Contacts (Pro — Clone Demo)')
            ->where('post_type', '==', 'portfolio')
            ->fields([
                Field::clone('client_contact')
                    ->from('contact_info_fields')
                    ->prefix('client')
                    ->label('Client Contact'),

                Field::clone('agency_contact')
                    ->from('contact_info_fields')
                    ->prefix('agency_contact')
                    ->label('Agency Contact')
                    ->display('group'),
            ])
            ->register();
    }

}, 1); // priority 1 so FieldForge service providers (priority 20) see these registrations
