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

final class Version20250822093614 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_adyen_payment_link (id INT AUTO_INCREMENT NOT NULL, payment_id INT DEFAULT NULL, payment_link_id VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_36C97E03B4ED0C9B (payment_link_id), INDEX IDX_36C97E034C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_adyen_payment_link ADD CONSTRAINT FK_36C97E034C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_adyen_payment_link DROP FOREIGN KEY FK_36C97E034C3A3BB');
        $this->addSql('DROP TABLE sylius_adyen_payment_link');
    }
}
