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

final class Version20250903190544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename sylius_adyen_token table to sylius_adyen_shopper_reference';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE sylius_adyen_token TO sylius_adyen_shopper_reference');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference RENAME INDEX uniq_fedc250a772e836a TO UNIQ_90DD1330772E836A');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference RENAME INDEX idx_fedc250a9395c3f3 TO IDX_90DD13309395C3F3');
        $this->addSql('ALTER TABLE sylius_adyen_shopper_reference RENAME INDEX idx_fedc250a5aa1164f TO IDX_90DD13305AA1164F');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('RENAME TABLE sylius_adyen_shopper_reference TO sylius_adyen_token');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX IDX_90DD13305AA1164F TO IDX_FEDC250A5AA1164F');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX IDX_90DD13309395C3F3 TO IDX_FEDC250A9395C3F3');
        $this->addSql('ALTER TABLE sylius_adyen_token RENAME INDEX UNIQ_90DD1330772E836A TO UNIQ_FEDC250A772E836A');
    }
}
