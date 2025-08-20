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
use Doctrine\Migrations\AbstractMigration;

final class Version20250820131406 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_reference DROP FOREIGN KEY FK_7FD033A84C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_reference ADD CONSTRAINT FK_7FD033A84C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_reference DROP FOREIGN KEY FK_7FD033A84C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_reference ADD CONSTRAINT FK_7FD033A84C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
