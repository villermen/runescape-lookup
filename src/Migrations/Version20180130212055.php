<?php

namespace DoctrineMigrations;

use App\Service\TimeKeeper;
use DateTime;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Version20180130212055 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

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
            RENAME INDEX player_id TO IDX_7894E9FE99E6F5DF,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');

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
            CHANGE data data VARCHAR(10000) NOT NULL,
            ADD UNIQUE INDEX UNIQ_BA6ECA4399E6F5DFAA9E377AB051093C (player_id, date, old_school),
            RENAME INDEX player_id TO IDX_BA6ECA4399E6F5DF,
            CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci
        ');

        $this->addSql('ALTER TABLE personal_record ADD FOREIGN KEY FK_6FB8738699E6F5DF (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE daily_record ADD FOREIGN KEY FK_1A0AA83D99E6F5DF (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE activity_feed_item ADD FOREIGN KEY FK_7894E9FE99E6F5DF (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE high_score ADD FOREIGN KEY FK_BA6ECA4399E6F5DF (player_id) REFERENCES player (id)');
    }

    public function postUp(Schema $schema)
    {
        $this->convertPlayerHighScoreToPersonalRecord();
        $this->deduplicateActivityTimes();
    }

    /**
     * Converts JSON of player.highscore into entries for the newly created personal_record table.
     * Drops the highscore field after it is done.
     */
    private function convertPlayerHighScoreToPersonalRecord()
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
     * Player activities are now sorted by time instead of ID, so activity time must be unique per player.
     * This method increments the time for every later activity per player with a duplicate time.
     * Applies the unique constraint after it has finished.
     */
    private function deduplicateActivityTimes()
    {
        $this->write("Incrementing time of activity_feed_item rows with duplicate player and time combinations...");

        $timeKeeper = $this->container->get(TimeKeeper::class);

        $updateStatement = $this->connection->prepare("UPDATE activity_feed_item SET time=:time WHERE id=:id");

        $players = $this->connection->fetchAll("SELECT id FROM player");

        // Loop through players, to not fetch everything at once
        foreach($players as $player) {
            $activities = $this->connection->fetchAll(
                "SELECT id, time FROM activity_feed_item WHERE player_id = :id ORDER BY id ASC",
                ["id" => $player["id"]]
            );

            $previousTime = (new DateTime())->setTimestamp(0);

            foreach($activities as $activity) {
                $activityTime = new DateTime($activity["time"]);

                $laterTime = $timeKeeper->getLaterTime($activityTime, $previousTime);

                if ($laterTime !== $activityTime) {
                    $updateStatement->execute([
                        "id" => $activity["id"],
                        "time" => $laterTime->format("Y-m-d H:i:s")
                    ]);
                }

                $previousTime = $laterTime;
            }
        }

        // Add unique constraint
        $this->connection->executeQuery("ALTER TABLE activity_feed_item ADD UNIQUE INDEX UNIQ_7894E9FE99E6F5DF6F949845 (player_id, time)");
    }

    public function down(Schema $schema)
    {
        $this->throwIrreversibleMigrationException();
    }
}
