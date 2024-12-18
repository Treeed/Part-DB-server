<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Helpers\Trees\StructuralDBElementIterator;
use App\Helpers\Trees\TreeViewNode;
use RecursiveIteratorIterator;

/**
 * @see \App\Tests\Repository\StructuralDBElementRepositoryTest
 * @template TEntityClass of AbstractStructuralDBElement
 * @extends AttachmentContainingDBElementRepository<TEntityClass>
 */
class StructuralDBElementRepository extends AttachmentContainingDBElementRepository
{
    /**
     * @var array An array containing all new entities created by getNewEntityByPath.
     * This is used to prevent creating multiple entities for the same path.
     */
    private array $new_entity_cache = [];

    /**
     * Finds all nodes for the given parent node, ordered by name in a natural sort way
     * @param  AbstractStructuralDBElement|null  $parent
     * @param  string  $nameOrdering  The ordering of the names. Either ASC or DESC
     * @return array
     */
    public function findNodesForParent(?AbstractStructuralDBElement $parent, string $nameOrdering = "ASC"): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e')
            ->orderBy('NATSORT(e.name)', $nameOrdering);

        if ($parent !== null) {
            $qb->where('e.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->where('e.parent IS NULL');
        }
        //@phpstan-ignore-next-line [parent is only defined by the sub classes]
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds all nodes without a parent node. They are our root nodes.
     *
     * @return AbstractStructuralDBElement[]
     */
    public function findRootNodes(): array
    {
        return $this->findNodesForParent(null);
    }

    /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @param  AbstractStructuralDBElement|null  $parent  the parent the root elements should have
     * @phpstan-param TEntityClass|null $parent
     *
     * @return TreeViewNode[]
     */
    public function getGenericNodeTree(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findNodesForParent($parent);
        foreach ($entities as $entity) {
            /** @var AbstractStructuralDBElement $entity */
            //Make a recursive call to find all children nodes
            $children = $this->getGenericNodeTree($entity);
            $node = new TreeViewNode($entity->getName(), null, $children);
            //Set the ID of this entity to later be able to reconstruct the URL
            $node->setId($entity->getID());
            $result[] = $node;
        }

        return $result;
    }

    /**
     * Gets a flattened hierarchical tree. Useful for generating option lists.
     *
     * @param AbstractStructuralDBElement|null $parent This entity will be used as root element. Set to null, to use global root
     * @phpstan-param TEntityClass|null $parent
     * @return AbstractStructuralDBElement[] a flattened list containing the tree elements
     * @phpstan-return array<int, TEntityClass>
     */
    public function getFlatList(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findNodesForParent($parent);

        $elementIterator = new StructuralDBElementIterator($entities);
        $recursiveIterator = new RecursiveIteratorIterator($elementIterator, RecursiveIteratorIterator::SELF_FIRST);
        //$result = iterator_to_array($recursiveIterator);

        //We can not use iterator_to_array here, or we get only the parent elements
        foreach ($recursiveIterator as $item) {
            $result[] = $item;
        }

        return $result;
    }

    public function getEntityFromPath(
        string $path,
        string $separator = '->',
        bool $strictCase = true,
        bool $allowAltNames = false,
        bool $allowCreation = false
    ): array
    {
        $parent = null;
        $result = [];
        foreach (explode($separator, $path) as $name) {
            $name = trim($name);
            if ('' === $name) {
                continue;
            }

            $entity = $this->getSingleEntity($name, $parent, $allowCreation, $strictCase, $allowAltNames);

            if($entity === null) {
                return [];
            }

            $result[] = $entity;
            $parent = $entity;
        }

        return $result;
    }

    public function getSingleEntity(
        string $name,
        ?AbstractStructuralDBElement $parent = null,
        bool $allowCreation = false,
        bool $respectParent = true,
        bool $strictCase = true,
        bool $allowAltNames = false,
    ) : ?AbstractStructuralDBElement
    {


        //See if we already have an element with this name and parent in the database
        //$entity = $this->findOneBy(['name' => $name, 'parent' => $parent]);
        $entity = $this->getFromDB($name, false, $parent, $strictCase, $respectParent);
        if($entity === null && $allowAltNames) {
            $entity = $this->getFromDB($name, true, $parent, $strictCase, $respectParent);
        }

        if ($entity instanceof AbstractStructuralDBElement) {
            return $entity;
        }

        if($allowCreation) {
            return $this->newEntity($name, $parent);
        }

        return null;
    }

    private function newEntity(string $name, ?AbstractStructuralDBElement $parent = null): AbstractStructuralDBElement
    {

        //Use the cache to prevent creating multiple entities for the same path
        $entity = $this->getNewEntityFromCache($name, $parent);

        if (null === $entity) {
            $class = $this->getClassName();
            /** @var AbstractStructuralDBElement $entity */
            $entity = new $class;
            $entity->setName($name);
            $entity->setAlternativeNames($name);
            $entity->setParent($parent);

            $this->setNewEntityToCache($entity);
        }
        return $entity;
    }

    private function getFromDB(string $name,
                               bool $useAltName,
                               ?AbstractStructuralDBElement
                               $parent, bool $strictCase,
                               bool $respectParent){

        $qb = $this->createQueryBuilder('e');

        $caseCommand = $strictCase ? '' : 'LOWER';
        $nameKey = $useAltName ? 'e.alternative_names' : 'e.name';
        $nameParameter = ":name";

        #$qb->where($qb->expr()->like("$caseCommand(e.$nameKey)", "$caseCommand(:name)"));
        if(!$strictCase){
            $nameKey = "LOWER($nameKey)";
            $nameParameter = "LOWER($nameParameter)";
        }

        $qb->where($qb->expr()->like($nameKey, $nameParameter));
        $qb->setParameter('name', $name);

        if($respectParent) {
            $qb->andWhere($qb->expr()->like("e.parent", ":parent"));
            $qb->setParameter('parent', $parent);
        }


        $result = $qb->getQuery()->getResult();

        if (count($result) >= 1) {
            return $result[0];
        }
        return null;
    }

    public function getEntityFromPathStrict(string $path, string $separator = '->', bool $allowCreation = false): array
    {
        return $this->getEntityFromPath($path, $separator, true, false, $allowCreation);
    }

    public function getSingleEntityLax(string $name, bool $allowCreation) : ?AbstractStructuralDBElement
    {
        return $this->getSingleEntity($name, null, $allowCreation, false, false, true);
    }

    private function getNewEntityFromCache(string $name, ?AbstractStructuralDBElement $parent): ?AbstractStructuralDBElement
    {
        $key = $parent instanceof AbstractStructuralDBElement ? $parent->getFullPath('%->%').'%->%'.$name : $name;
        return $this->new_entity_cache[$key] ?? null;
    }

    private function setNewEntityToCache(AbstractStructuralDBElement $entity): void
    {
        $key = $entity->getFullPath('%->%');
        $this->new_entity_cache[$key] = $entity;
    }

}
