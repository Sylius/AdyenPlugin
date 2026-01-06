<?php

/*
 * This file is part of the Sylius Adyen Plugin package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AdyenPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

final class Version20260105150445 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix character set for sylius_adyen_payment_detail table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
    }
}
