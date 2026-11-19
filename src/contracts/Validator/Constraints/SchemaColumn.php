<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Validator\Constraints;

use Attribute;
use Ibexa\DoctrineSchema\Validator\Constraints\SchemaMappingMarkerValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Maps a property to a schema column, whose definition is turned into validation constraints.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class SchemaColumn extends Constraint
{
    /**
     * @param non-empty-string|null $column defaults to the snake_case property name
     * @param non-empty-string|null $table defaults to the table of the class' {@see SchemaTable}
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly ?string $column = null,
        public readonly ?string $table = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }

    public function validatedBy(): string
    {
        return SchemaMappingMarkerValidator::class;
    }

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
