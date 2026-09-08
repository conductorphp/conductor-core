<?php

declare(strict_types=1);

namespace ConductorCore\Config\Schema;

use ConductorCore\Config\Schema\Node\AnyNode;
use ConductorCore\Config\Schema\Node\CollectionNode;
use ConductorCore\Config\Schema\Node\EnumNode;
use ConductorCore\Config\Schema\Node\FileModeNode;
use ConductorCore\Config\Schema\Node\MapNode;
use ConductorCore\Config\Schema\Node\RawNode;
use ConductorCore\Config\Schema\Node\ScalarNode;

/**
 * Builds a config schema.
 *
 * ```php
 * $sb = new SchemaBuilder();
 *
 * $sb->map([
 *     'app_name'    => $sb->string()->notEmpty()->required(),
 *     'file_layout' => $sb->enum(['default', 'blue_green'])->default('default'),
 *     'databases'   => $sb->collection($sb->map([
 *         'adapter' => $sb->string()->default('default'),
 *     ]))->default([]),
 * ]);
 * ```
 *
 * A node is a mutable builder, so each call returns a FRESH one — never share a node between two
 * schemas and then modify it.
 */
final class SchemaBuilder
{
    public function string(): ScalarNode
    {
        return new ScalarNode(ScalarNode::TYPE_STRING);
    }

    public function int(): ScalarNode
    {
        return new ScalarNode(ScalarNode::TYPE_INT);
    }

    public function float(): ScalarNode
    {
        return new ScalarNode(ScalarNode::TYPE_FLOAT);
    }

    public function bool(): ScalarNode
    {
        return new ScalarNode(ScalarNode::TYPE_BOOL);
    }

    /** @param array<string, SchemaInterface> $shape */
    public function map(array $shape): MapNode
    {
        return new MapNode($shape);
    }

    /** A map or list of same-shaped entries whose keys the project chooses. */
    public function collection(SchemaInterface $values, ?SchemaInterface $keys = null): CollectionNode
    {
        return new CollectionNode($values, $keys);
    }

    /** @param list<mixed> $allowed */
    public function enum(array $allowed): EnumNode
    {
        return new EnumNode($allowed);
    }

    /** An array whose interior is not described yet. */
    public function raw(): RawNode
    {
        return new RawNode();
    }

    public function any(): AnyNode
    {
        return new AnyNode();
    }

    /**
     * A filesystem permission mode, normalized to the int `chmod()` and `mkdir()` want.
     *
     * See {@see FileModeNode} for why the int and string forms need opposite handling. That
     * conversion used to live unnamed in `ApplicationConfig::filter()`, after which the getter
     * declared `: string` for a value `mkdir()` needs as an int.
     */
    public function fileMode(): FileModeNode
    {
        return new FileModeNode();
    }
}
