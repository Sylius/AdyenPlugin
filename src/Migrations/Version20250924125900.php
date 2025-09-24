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
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractPostgreSQLMigration;

final class Version20250924125900 extends AbstractPostgreSQLMigration
{
    public function getDescription(): string
    {
        return 'Initial migration for PostgreSQL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE sylius_adyen_log_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sylius_adyen_payment_detail_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sylius_adyen_payment_link_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sylius_adyen_reference_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE sylius_adyen_shopper_reference_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE sylius_adyen_log (id INT NOT NULL, level INT NOT NULL, error_code INT NOT NULL, message VARCHAR(5000) NOT NULL, date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE sylius_adyen_payment_detail (id INT NOT NULL, payment_id INT DEFAULT NULL, amount INT NOT NULL, capture_mode VARCHAR(64) DEFAULT \'automatic\' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_36E28B124C3A3BB ON sylius_adyen_payment_detail (payment_id)');
        $this->addSql('CREATE TABLE sylius_adyen_payment_link (id INT NOT NULL, payment_id INT DEFAULT NULL, payment_link_id VARCHAR(64) NOT NULL, payment_link_url VARCHAR(1000) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_36C97E03B4ED0C9B ON sylius_adyen_payment_link (payment_link_id)');
        $this->addSql('CREATE INDEX IDX_36C97E034C3A3BB ON sylius_adyen_payment_link (payment_id)');
        $this->addSql('CREATE TABLE sylius_adyen_reference (id INT NOT NULL, refund_payment_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, psp_reference VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1C53DCD218C3BB89 ON sylius_adyen_reference (psp_reference)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1C53DCD2E739D017 ON sylius_adyen_reference (refund_payment_id)');
        $this->addSql('CREATE INDEX IDX_1C53DCD24C3A3BB ON sylius_adyen_reference (payment_id)');
        $this->addSql('CREATE TABLE sylius_adyen_shopper_reference (id INT NOT NULL, customer_id INT DEFAULT NULL, payment_method_id INT DEFAULT NULL, identifier VARCHAR(64) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_90DD1330772E836A ON sylius_adyen_shopper_reference (identifier)');
        $this->addSql('CREATE INDEX IDX_90DD13309395C3F3 ON sylius_adyen_shopper_reference (customer_id)');
        $this->addSql('CREATE INDEX IDX_90DD13305AA1164F ON sylius_adyen_shopper_reference (payment_method_id)');
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail ADD CONSTRAINT FK_36E28B124C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adyen_payment_link ADD CONSTRAINT FK_36C97E034C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adyen_reference ADD CONSTRAINT FK_1C53DCD2E739D017 FOREIGN KEY (refund_payment_id) REFERENCES sylius_refund_refund_payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adyen_reference ADD CONSTRAINT FK_1C53DCD24C3A3BB FOREIGN KEY (payment_id) REFERENCES sylius_payment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference ADD CONSTRAINT FK_90DD13309395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference ADD CONSTRAINT FK_90DD13305AA1164F FOREIGN KEY (payment_method_id) REFERENCES sylius_payment_method (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant ADD commodity_code VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP SEQUENCE sylius_adyen_log_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sylius_adyen_payment_detail_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sylius_adyen_payment_link_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sylius_adyen_reference_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE sylius_adyen_shopper_reference_id_seq CASCADE');
        $this->addSql('ALTER TABLE sylius_adyen_payment_detail DROP CONSTRAINT FK_36E28B124C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_payment_link DROP CONSTRAINT FK_36C97E034C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_reference DROP CONSTRAINT FK_1C53DCD2E739D017');
        $this->addSql('ALTER TABLE sylius_adyen_reference DROP CONSTRAINT FK_1C53DCD24C3A3BB');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference DROP CONSTRAINT FK_90DD13309395C3F3');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference DROP CONSTRAINT FK_90DD13305AA1164F');
        $this->addSql('DROP TABLE sylius_adyen_log');
        $this->addSql('DROP TABLE sylius_adyen_payment_detail');
        $this->addSql('DROP TABLE sylius_adyen_payment_link');
        $this->addSql('DROP TABLE sylius_adyen_reference');
        $this->addSql('DROP TABLE sylius_adyen_shopper_reference');
        $this->addSql('ALTER TABLE sylius_product_variant DROP commodity_code');
    }
}
