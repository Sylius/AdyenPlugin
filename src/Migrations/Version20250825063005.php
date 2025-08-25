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

final class Version20250825063005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sylius_adyen_payment_detail table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_adyen_payment_detail (id INT AUTO_INCREMENT NOT NULL, payment_id INT DEFAULT NULL, amount INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_36E28B124C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail ADD CONSTRAINT FK_36E28B124C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail DROP FOREIGN KEY FK_36E28B124C3A3BB');
        $this->addSql('DROP TABLE sylius_adyen_payment_detail');
    }
}
