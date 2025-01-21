<?php

namespace DoctrineMigration;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20250120201646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'High score activities and unique records: Data preparation.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->platform instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on MySQL platform.');

        // Add nullable column for migrated data (made non-null in migration).
        $this->addSql('ALTER TABLE high_score
            ADD data JSON DEFAULT NULL
        ');

        // Only keep latest records.
        $this->addSql('DELETE FROM personal_record WHERE id NOT IN (
            SELECT MAX(id)
            FROM personal_record
            GROUP BY player_id, old_school, skill
        )');
        $this->addSql('DELETE FROM daily_record WHERE `date` != DATE_SUB(CURRENT_DATE, INTERVAL 1 DAY)');
    }

    public function postUp(Schema $schema): void
    {
        $batchSize = 5000;
        $progress = 0;
        $startTotal = $this->connection->fetchOne('SELECT COUNT(id) FROM high_score WHERE data IS NULL');
        $fetchStatement = $this->connection->prepare('SELECT id, skills FROM high_score WHERE data IS NULL LIMIT :batchSize');
        $fetchStatement->bindValue('batchSize', $batchSize, ParameterType::INTEGER);
        $updateStatement = $this->connection->prepare('UPDATE high_score SET data = :data WHERE id = :id');

        do {
            $this->connection->beginTransaction();

            $highScores = $fetchStatement->executeQuery()->fetchAllAssociative();
            foreach ($highScores as $highScore) {
                $data = [
                    'skills' => [],
                    'activities' => [],
                ];
                foreach (explode(';', $highScore['skills']) as $skill) {
                    [$id, $level, $xp, $rank] = explode(',', $skill);
                    $data['skills'][] = [
                        'id' => $id,
                        'rank' => $rank,
                        'level' => $level,
                        'xp' => $xp,
                    ];
                }

                $updateStatement->bindValue('data', json_encode($data));
                $updateStatement->bindValue('id', $highScore['id'], ParameterType::INTEGER);
                $updateStatement->executeStatement();
            }

            $this->connection->commit();

            $progress += count($highScores);
            $this->write(sprintf('Migrated %s/%s high score entries.', $progress, $startTotal));
        } while (count($highScores) === $batchSize);
    }
}
