<?php

declare(strict_types=1);

namespace FieldForge\Core\Pipeline;

use FieldForge\Builder\AdminContext;
use FieldForge\Core\Pipeline\Contracts\StageInterface;
use FieldForge\Core\Pipeline\Stages\CapabilityCheckStage;
use FieldForge\Core\Pipeline\Stages\ComputedFieldsStage;
use FieldForge\Core\Pipeline\Stages\JsonDecodeStage;
use FieldForge\Core\Pipeline\Stages\NonceValidationStage;
use FieldForge\Core\Pipeline\Stages\PersistenceStage;
use FieldForge\Core\Pipeline\Stages\RulesVerificationStage;
use FieldForge\Core\Pipeline\Stages\SanitizationStage;
use FieldForge\Core\Pipeline\Stages\SchemaValidationStage;
use FieldForge\Core\Pipeline\Stages\TypeCoercionStage;
use FieldForge\Core\Security\CapabilityChecker;
use FieldForge\Core\Security\NonceValidator;
use FieldForge\Registry\ContextRegistry;
use FieldForge\Storage\Contracts\StorageAdapterInterface;
use FieldForge\Storage\Drivers\WpPostMetaDriver;
use FieldForge\Storage\PostMetaAdapter;
use FieldForge\Storage\TermMetaAdapter;
use FieldForge\Storage\UserMetaAdapter;

/**
 * Orchestrates pipeline stages in strict order.
 *
 * Two entry points:
 *  - process(PipelineContext): pure PHP, fully testable without WP.
 *  - run(int, array):          WP integration point, builds context from WP runtime state.
 *                              Excluded from PHPStan (references WP functions and drivers).
 *
 * Stage list construction is centralised in buildDataStages() and buildFullStages()
 * so additions only need to be made in one place.
 */
class SavePipeline
{
    /** @param StageInterface[] $stages */
    public function __construct(private readonly array $stages) {}

    public function process(PipelineContext $context): void
    {
        foreach ($this->stages as $stage) {
            $stage->handle($context);
        }
    }

    /**
     * WP admin / save_post entry point (full pipeline including nonce + capability).
     *
     * @param array<string, mixed> $rawPost
     */
    public static function run(
        int $entityId,
        array $rawPost,
        ?StorageAdapterInterface $adapterOverride = null,
        ?AdminContext $contextOverride = null,
    ): void {
        if ($contextOverride !== null) {
            $adminContext = $contextOverride;
        } else {
            $postType = function_exists('get_post_type') ? get_post_type($entityId) : false;

            if ($postType === false || ! is_string($postType)) {
                return;
            }

            $adminContext = new AdminContext(postType: $postType);
        }

        $groups = ContextRegistry::resolve($adminContext);
        if (empty($groups)) {
            return;
        }

        $context  = new PipelineContext($entityId, $rawPost, array_values($groups));
        $adapter  = $adapterOverride ?? new PostMetaAdapter(new WpPostMetaDriver());
        $pipeline = new self(self::buildFullStages($adapter));

        self::dispatch($pipeline, $context, $entityId, $rawPost);
    }

    /**
     * REST API entry point for post fields (skips nonce + capability — WP REST already verified).
     */
    public static function runFromRest(int $postId, string $jsonPayload): void
    {
        $postType = function_exists('get_post_type') ? get_post_type($postId) : false;

        if ($postType === false || ! is_string($postType)) {
            return;
        }

        $groups = ContextRegistry::resolve(new AdminContext(postType: $postType));
        if (empty($groups)) {
            return;
        }

        $rawPost  = ['fieldforge_payload' => $jsonPayload];
        $context  = new PipelineContext($postId, $rawPost, array_values($groups));
        $adapter  = new PostMetaAdapter(new WpPostMetaDriver());
        $pipeline = new self(self::buildDataStages($adapter));

        self::dispatch($pipeline, $context, $postId, $rawPost);
    }

    /** REST API entry point for user meta. */
    public static function runFromRestUser(int $userId, string $jsonPayload): void
    {
        self::runFromRestWithAdapter(
            $userId,
            $jsonPayload,
            new UserMetaAdapter(),
            new AdminContext(contextType: 'user_profile'),
        );
    }

    /** REST API entry point for term meta. */
    public static function runFromRestTerm(int $termId, string $jsonPayload): void
    {
        $taxonomy = '';
        if (function_exists('get_term')) {
            $term     = get_term($termId);
            $taxonomy = ($term instanceof \WP_Term) ? $term->taxonomy : '';
        }
        self::runFromRestWithAdapter(
            $termId,
            $jsonPayload,
            new TermMetaAdapter(),
            new AdminContext(taxonomy: $taxonomy !== '' ? $taxonomy : null),
        );
    }

    // -------------------------------------------------------------------------
    // Stage factories — single source of truth for stage composition
    // -------------------------------------------------------------------------

    /**
     * Full stage list: nonce + capability + data stages.
     * Used by save_post (admin form submit).
     *
     * @return StageInterface[]
     */
    private static function buildFullStages(StorageAdapterInterface $adapter): array
    {
        return [
            new NonceValidationStage(new NonceValidator()),
            new CapabilityCheckStage(new CapabilityChecker()),
            ...self::buildDataStages($adapter),
        ];
    }

    /**
     * Data-only stages: decode → validate → coerce → sanitize → compute → persist.
     * Used by REST endpoints (auth already verified by WP REST).
     *
     * @return StageInterface[]
     */
    private static function buildDataStages(StorageAdapterInterface $adapter): array
    {
        return [
            new JsonDecodeStage(),
            new SchemaValidationStage(),
            new TypeCoercionStage(),
            new RulesVerificationStage(),
            new SanitizationStage($adapter),
            new ComputedFieldsStage(),
            new PersistenceStage($adapter),
        ];
    }

    // -------------------------------------------------------------------------

    private static function runFromRestWithAdapter(
        int $entityId,
        string $jsonPayload,
        StorageAdapterInterface $adapter,
        AdminContext $adminContext,
    ): void {
        $groups = ContextRegistry::resolve($adminContext);
        if (empty($groups)) {
            return;
        }

        $rawPost  = ['fieldforge_payload' => $jsonPayload];
        $context  = new PipelineContext($entityId, $rawPost, array_values($groups));
        $pipeline = new self(self::buildDataStages($adapter));

        self::dispatch($pipeline, $context, $entityId, $rawPost);
    }

    /** Runs the pipeline and fires before/after_save hooks. */
    private static function dispatch(
        self $pipeline,
        PipelineContext $context,
        int $entityId,
        array $rawPost,
    ): void {
        if (function_exists('do_action')) {
            do_action('fieldforge/before_save', $entityId, $rawPost);
        }

        $pipeline->process($context);

        if (function_exists('do_action')) {
            do_action('fieldforge/after_save', $entityId, $context->fields);
        }
    }
}
