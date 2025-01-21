<?php

namespace DoctrineMigration;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20180130210435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pre-Symfony database structure.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->platform instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on MySQL platform.');

        $this->addSql('CREATE TABLE activity (
            id int(11) NOT NULL AUTO_INCREMENT,
            player_id int(11) NOT NULL,
            time timestamp NULL DEFAULT NULL,
            guid varchar(255) NOT NULL,
            title text NOT NULL,
            description text NOT NULL,
            PRIMARY KEY (id),
            INDEX player_id (player_id)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci');

        $this->addSql('CREATE TABLE daily_highscore (
            id int(11) NOT NULL AUTO_INCREMENT,
            player_id int(11) NOT NULL,
            time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            skill_id int(11) NOT NULL,
            xp int(11) NOT NULL,
            PRIMARY KEY (id),
            INDEX player_id (player_id)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci');

        $this->addSql('CREATE TABLE player (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(12) NOT NULL,
            highscore text,
            last_update timestamp NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX name (name)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci');

        $this->addSql('CREATE TABLE stats (
            id int(11) NOT NULL AUTO_INCREMENT,
            player_id int(11) NOT NULL,
            time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            data text NOT NULL,
            PRIMARY KEY (id),
            INDEX player_id (player_id)
        ) ENGINE=InnoDB DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci');

        $this->addSql('ALTER TABLE activity ADD FOREIGN KEY activity_ibfk_1 (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE daily_highscore ADD FOREIGN KEY daily_highscore_ibfk_1 (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE stats ADD FOREIGN KEY stats_ibfk_1 (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }
}
