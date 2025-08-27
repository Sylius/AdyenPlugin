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

final class Version20250827075851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update foreign key constraints and rename indexes for Adyen plugin tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail DROP FOREIGN KEY FK_36E28B124C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail ADD CONSTRAINT FK_36E28B124C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX uniq_7fd033a818c3bb89 TO UNIQ_1C53DCD218C3BB89');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX uniq_7fd033a8e739d017 TO UNIQ_1C53DCD2E739D017');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX idx_7fd033a84c3a3bb TO IDX_1C53DCD24C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX uniq_681c2e9a772e836a TO UNIQ_FEDC250A772E836A');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX idx_681c2e9a9395c3f3 TO IDX_FEDC250A9395C3F3');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX idx_681c2e9a5aa1164f TO IDX_FEDC250A5AA1164F');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail DROP FOREIGN KEY FK_36E28B124C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail ADD CONSTRAINT FK_36E28B124C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX idx_1c53dcd24c3a3bb TO IDX_7FD033A84C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX uniq_1c53dcd218c3bb89 TO UNIQ_7FD033A818C3BB89');
        $this->addSql('ALTER TABLE sylius_adyen_reference RENAME INDEX uniq_1c53dcd2e739d017 TO UNIQ_7FD033A8E739D017');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX idx_fedc250a5aa1164f TO IDX_681C2E9A5AA1164F');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX idx_fedc250a9395c3f3 TO IDX_681C2E9A9395C3F3');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX uniq_fedc250a772e836a TO UNIQ_681C2E9A772E836A');
    }
}
