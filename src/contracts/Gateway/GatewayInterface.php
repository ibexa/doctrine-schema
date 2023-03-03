<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\DoctrineSchema\Gateway;

/**
 * @internal
 *
 * @template TData of array
 */
interface GatewayInterface
{
    public function getMetadata(): DoctrineSchemaMetadataInterface;

    public function countAll(): int;

    /**
     * @param \Doctrine\Common\Collections\Expr\Expression|array<string, \Doctrine\Common\Collections\Expr\Expression|scalar|array<scalar>> $criteria
     */
    public function countBy($criteria): int;

    /**
     * @return array<int, TData> Array of results. Should be serializable.
     */
    public function findAll(?int $limit = null, int $offset = 0): array;

    /**
     * @param \Doctrine\Common\Collections\Expr\Expression|array<string, \Doctrine\Common\Collections\Expr\Expression|scalar|array<scalar>|null> $criteria Map of column names to values that will be used as part of WHERE query
     * @param array<string, string>|null $orderBy Map of column names to "ASC" or "DESC", that will be used in SORT query
     *
     * @phpstan-param array<string, "ASC"|"DESC">|null $orderBy
     *
     * @return array<int, TData> Array of results. Should be serializable.
     */
    public function findBy($criteria, ?array $orderBy = null, ?int $limit = null, int $offset = 0): array;

    /**
     * @param array<string, \Doctrine\Common\Collections\Expr\Expression|scalar|array<scalar>|null> $criteria Map of column names to values that will be used as part of WHERE query
     * @param array<string, string>|null $orderBy Map of column names to "ASC" or "DESC", that will be used in SORT query
     *
     * @phpstan-param array<string, "ASC"|"DESC">|null $orderBy
     *
     * @return TData|null Single result or null. Should be serializable.
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?array;

    /**
     * @return TData|null Single result. Should be serializable.
     */
    public function findById(int $id): ?array;
}
