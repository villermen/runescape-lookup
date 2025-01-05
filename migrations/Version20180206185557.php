<?php

namespace DoctrineMigration;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20180206185557 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Include skill in record index.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on MySQL platform.');

        $this->addSql('DROP INDEX UNIQ_1A0AA83DBF396750AA9E377A99E6F5DF ON daily_record');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1A0AA83D5E3DE477AA9E377AB051093C ON daily_record (skill, date, old_school)');
        $this->addSql('DROP INDEX UNIQ_6FB87386BF396750AA9E377A99E6F5DF ON personal_record');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FB873865E3DE477AA9E377A99E6F5DFB051093C ON personal_record (skill, date, player_id, old_school)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on MySQL platform.');

        $this->addSql('DROP INDEX UNIQ_1A0AA83D5E3DE477AA9E377AB051093C ON daily_record');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1A0AA83DBF396750AA9E377A99E6F5DF ON daily_record (id, date, player_id)');
        $this->addSql('DROP INDEX UNIQ_6FB873865E3DE477AA9E377A99E6F5DFB051093C ON personal_record');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FB87386BF396750AA9E377A99E6F5DF ON personal_record (id, date, player_id)');
    }
}
