<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\DataFixtures;


use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class PartFixtures extends Fixture
{

    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * @inheritDoc
     */
    public function load(ObjectManager $manager)
    {
        $table_name = $this->em->getClassMetadata(Part::class)->getTableName();
        $this->em->getConnection()->exec("ALTER TABLE `${table_name}` AUTO_INCREMENT = 1;");

        /** Simple part */
        $part = new Part();
        $part->setName('Part 1');
        $part->setCategory($manager->find(Category::class, 1));
        $manager->persist($part);

        /** More complex part */
        $part = new Part();
        $part->setName('Part 2');
        $part->setCategory($manager->find(Category::class, 1));
        $part->setFootprint($manager->find(Footprint::class, 1));
        $part->setManufacturer($manager->find(Manufacturer::class, 1));
        $part->setTags('test, Test, Part2');
        $part->setMass(100.2);
        $part->setNeedsReview(true);
        $part->setManufacturingStatus('active');
        $manager->persist($part);

        /** Part with orderdetails, storelocations and Attachments */
        $part = new Part();
        $part->setFavorite(true);
        $part->setName('Part 2');
        $part->setCategory($manager->find(Category::class, 1));
        $partLot1 = new PartLot();
        $partLot1->setAmount(1.0);
        $partLot1->setStorageLocation($manager->find(Storelocation::class, 1));
        $part->addPartLot($partLot1);

        $partLot2 = new PartLot();
        $partLot2->setExpirationDate(new \DateTime());
        $partLot2->setComment('Test');
        $partLot2->setNeedsRefill(true);
        $partLot2->setStorageLocation($manager->find(Storelocation::class, 3));
        $part->addPartLot($partLot2);

        $orderdetail = new Orderdetail();
        $orderdetail->setSupplier($manager->find(Supplier::class, 1));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(1.0)->setPrice(10));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(10.0)->setPrice(15));
        $part->addOrderdetail($orderdetail);

        $orderdetail = new Orderdetail();
        $orderdetail->setSupplierpartnr('BC 547');
        $orderdetail->setObsolete(true);
        $orderdetail->setSupplier($manager->find(Supplier::class, 1));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(1.0)->setPrice(10));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(10.0)->setPrice(15));
        $part->addOrderdetail($orderdetail);

        $attachment = new PartAttachment();
        $attachment->setName('TestAttachment');
        $attachment->setURL('www.foo.bar');
        $attachment->setAttachmentType($manager->find(AttachmentType::class, 1));
        $part->addAttachment($attachment);

        $attachment = new PartAttachment();
        $attachment->setName('Test2');
        $attachment->setPath('invalid');
        $attachment->setShowInTable(true);
        $attachment->setAttachmentType($manager->find(AttachmentType::class, 1));
        $part->addAttachment($attachment);

        $manager->persist($part);
        $manager->flush();
    }
}