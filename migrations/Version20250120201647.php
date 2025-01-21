<?php

namespace DoctrineMigration;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250120201647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'High score activities and unique records.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->platform instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on MySQL platform.');

        $this->addSql('ALTER TABLE high_score
            DROP skills,
            DROP INDEX UNIQ_BA6ECA4399E6F5DFAA9E377AB051093C,
            MODIFY old_school TINYINT(1) NOT NULL AFTER date,
            MODIFY data JSON NOT NULL,
            ADD UNIQUE INDEX unique_high_score (player_id, old_school, date)
        ');
        $this->addSql('ALTER TABLE personal_record
            DROP INDEX UNIQ_6FB873865E3DE477AA9E377A99E6F5DFB051093C,
            CHANGE skill type_id INT NOT NULL,
            MODIFY old_school TINYINT(1) NOT NULL AFTER player_id,
            CHANGE xp_gain score INT NOT NULL,
            MODIFY date DATE NOT NULL AFTER score,
            ADD activity TINYINT(1) NOT NULL AFTER old_school,
            ADD UNIQUE INDEX unique_record (player_id, old_school, activity, type_id)
        ');
        $this->addSql('ALTER TABLE daily_record
            DROP INDEX UNIQ_1A0AA83D5E3DE477AA9E377AB051093C,
            DROP date,
            MODIFY old_school TINYINT(1) NOT NULL AFTER player_id,
            CHANGE skill type_id INT NOT NULL,
            CHANGE xp_gain score INT NOT NULL,
            ADD activity TINYINT(1) NOT NULL AFTER player_id,
            ADD UNIQUE INDEX unique_record (old_school, activity, type_id)
        ');
        $this->addSql('ALTER TABLE activity_feed_item
            DROP INDEX sequence_number,
            RENAME INDEX UNIQ_7894E9FE99E6F5DFF2803B3D TO unique_sequence
        ');

        // TODO: Remove DC2Type comments
    }
}
