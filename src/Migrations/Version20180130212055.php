<?php

namespace DoctrineMigrations;

use App\Service\TimeKeeper;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Migrations\Version;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Query\ParameterTypeInferer;
use PDO;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Villermen\RuneScape\HighScore\SkillHighScore;
use Villermen\RuneScape\PlayerDataConverter;

class Version20180130212055 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function __construct(Version $version)
    {
        parent::__construct($version);
    }

    public function getDescription()
    {
        return "Migrates the legacy database structure to the new Symfony layout.";
    }

    public function up(Schema $schema)
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE daily_highscore DROP FOREIGN KEY daily_highscore_ibfk_1');

        $this->addSql('ALTER TABLE activity
            RENAME activity_feed_item,
            MODIFY time DATETIME NOT NULL,
            MODIFY title VARCHAR(1000) NOT NULL,
            MODIFY description VARCHAR(10000) NOT NULL,
            ADD sequence_number INT NOT NULL,
            DROP guid,
            RENAME INDEX player_id TO IDX_7894E9FE99E6F5DF,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');
        $this->addSql('UPDATE activity_feed_item SET sequence_number = id');

        $this->addSql('ALTER TABLE daily_highscore
            RENAME daily_record,
            CHANGE time date DATE NOT NULL,
            CHANGE skill_id skill INT NOT NULL COMMENT \'(DC2Type:skill)\',
            CHANGE xp xp_gain INT NOT NULL,
            ADD UNIQUE INDEX UNIQ_1A0AA83DBF396750AA9E377A99E6F5DF (id, date, player_id),
            RENAME INDEX player_id TO IDX_1A0AA83D99E6F5DF,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');

        $this->addSql('CREATE TABLE personal_record (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            date DATE NOT NULL,
            skill INT NOT NULL COMMENT \'(DC2Type:skill)\',
            xp_gain INT NOT NULL,
            INDEX IDX_6FB8738699E6F5DF (player_id),
            UNIQUE INDEX UNIQ_6FB87386BF396750AA9E377A99E6F5DF (id, date, player_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');

        $this->addSql('ALTER TABLE player
            ADD active TINYINT(1) NOT NULL,
            DROP last_update,
            RENAME INDEX name TO UNIQ_98197A655E237E06,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');
        $this->addSql('UPDATE player SET active=true');

        $this->addSql('ALTER TABLE stats
            RENAME high_score,
            ADD old_school TINYINT(1) NOT NULL,
            CHANGE time date DATE NOT NULL,
            CHANGE data skills TEXT NOT NULL COMMENT \'(DC2Type:high_score_skill_array)\',
            ADD UNIQUE INDEX UNIQ_BA6ECA4399E6F5DFAA9E377AB051093C (player_id, date, old_school),
            RENAME INDEX player_id TO IDX_BA6ECA4399E6F5DF,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');

        $this->addSql('ALTER TABLE personal_record ADD FOREIGN KEY FK_6FB8738699E6F5DF (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE daily_record ADD FOREIGN KEY FK_1A0AA83D99E6F5DF (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE activity_feed_item ADD FOREIGN KEY FK_7894E9FE99E6F5DF (player_id) REFERENCES player (id), ADD UNIQUE INDEX UNIQ_7894E9FE99E6F5DF6F949845 (player_id, sequence_number)');
        $this->addSql('ALTER TABLE high_score ADD FOREIGN KEY FK_BA6ECA4399E6F5DF (player_id) REFERENCES player (id)');
    }

    public function postUp(Schema $schema)
    {
        $this->convertPlayerHighScoreToPersonalRecord();
        $this->convertHighScoreDataToSkillsArray();
    }

    /**
     * Converts JSON of player.highscore into entries for the newly created personal_record table.
     * Drops the highscore field after it is done.
     */
    protected function convertPlayerHighScoreToPersonalRecord()
    {
        $this->write("Converting player.highscore JSON to personal_record table entries...");

        $highScores = $this->connection->fetchAll("SELECT id, highscore FROM player WHERE highscore IS NOT NULL");

        foreach($highScores as $highScore) {
            $highScoreDecoded = json_decode($highScore["highscore"]);

            foreach($highScoreDecoded as $skillId => $highScoreData) {
                $this->connection->insert("personal_record", [
                    "player_id" => $highScore["id"],
                    "date" => $highScoreData->date,
                    "skill" => $skillId,
                    "xp_gain" =>  $highScoreData->xp
                ]);
            }
        }

        $this->connection->executeQuery("ALTER TABLE player DROP highscore");
    }

    /**
     * Converts HighScore data into their new high_score_skills_array representation
     */
    protected function convertHighScoreDataToSkillsArray()
    {
        $this->write("Converting stats.data to high_score.skills...");

        $dataConverter = new PlayerDataConverter();
        $convert = function(string $data) use ($dataConverter) {
            /** @var SkillHighScore $highScore */
            $highScore = $dataConverter->convertIndexLite($data)[PlayerDataConverter::KEY_SKILL_HIGH_SCORE];

            $serializedSkills = [];
            foreach($highScore->getSkills() as $skill) {
                $serializedSkills[] = implode(",", [
                    $skill->getSkill()->getId(),
                    $skill->getLevel(),
                    $skill->getXp(),
                    (int)$skill->getRank()
                ]);
            }

            return implode(";", $serializedSkills);
        };

        $updateStatement = $this->connection->prepare("UPDATE high_score SET skills=:skills WHERE id=:id");

        $offset = 0;
        do {
            $highScores = $this->connection->fetchAll("SELECT id, skills FROM high_score LIMIT 100 OFFSET :offset", [
                "offset" => $offset
            ], [
                "offset" => PDO::PARAM_INT
            ]);

            foreach ($highScores as $highScore) {
                $updateStatement->execute([
                    "id" => $highScore["id"],
                    "skills" => $convert($highScore["skills"])
                ]);
            }

            $offset += count($highScores);
        } while (count($highScores));
    }

    public function down(Schema $schema)
    {
        $this->throwIrreversibleMigrationException();
    }
}
