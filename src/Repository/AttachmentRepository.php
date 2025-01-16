<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

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

namespace App\Repository;

use App\Entity\Attachments\Attachment;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @template TEntityClass of Attachment
 * @extends DBElementRepository<TEntityClass>
 */
class AttachmentRepository extends DBElementRepository
{
    /**
     * Gets the count of all private/secure attachments.
     */
    public function getPrivateAttachmentsCount(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.internal_path LIKE :like ESCAPE \'#\'');
        $qb->setParameter('like', '#%SECURE#%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all external attachments (attachments containing an external path).
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getExternalAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.external_path <> \'\'');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all attachments where a user uploaded a file (or an external file was downloaded, but the path
     * is not known)
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getUserUploadedAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.internal_path LIKE :base ESCAPE \'#\' 
            OR attachment.internal_path LIKE :media ESCAPE \'#\' 
            OR attachment.internal_path LIKE :secure ESCAPE \'#\'')
            ->andWhere('attachment.external_path = \'\'');

        $qb->setParameter('secure', '#%SECURE#%%');
        $qb->setParameter('base', '#%BASE#%%');
        $qb->setParameter('media', '#%MEDIA#%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all attachments where a file was downloaded from an external source and the source is known
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getDownloadedAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.internal_path <> \'\'')
            ->andWhere('attachment.external_path <> \'\'');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
