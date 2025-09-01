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

final class Version20250901134539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update the max length of the log message.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_log CHANGE message message VARCHAR(5000) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_log CHANGE message message VARCHAR(1000) NOT NULL');
    }
}
