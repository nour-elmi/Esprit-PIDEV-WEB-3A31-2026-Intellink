<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lier progression_formation a participation avec suppression en cascade';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE progression_formation ADD idParticipation INT DEFAULT NULL');
        $this->addSql('UPDATE progression_formation pf
            INNER JOIN participation p
                ON p.idFormation = pf.idFormation
               AND p.idUtilisateur = pf.idUtilisateur
            SET pf.idParticipation = p.idParticipation');
        $this->addSql('ALTER TABLE progression_formation CHANGE idParticipation idParticipation INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_progression_participation ON progression_formation (idParticipation)');
        $this->addSql('CREATE INDEX IDX_AA5B1AB88622711D ON progression_formation (idParticipation)');
        $this->addSql('ALTER TABLE progression_formation ADD CONSTRAINT FK_AA5B1AB88622711D FOREIGN KEY (idParticipation) REFERENCES participation (idParticipation) ON DELETE CASCADE');
        $this->addSql('DROP INDEX uniq_progression_user_formation ON progression_formation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE progression_formation DROP FOREIGN KEY FK_AA5B1AB88622711D');
        $this->addSql('DROP INDEX uniq_progression_participation ON progression_formation');
        $this->addSql('DROP INDEX IDX_AA5B1AB88622711D ON progression_formation');
        $this->addSql('CREATE UNIQUE INDEX uniq_progression_user_formation ON progression_formation (idUtilisateur, idFormation)');
        $this->addSql('ALTER TABLE progression_formation DROP idParticipation');
    }
}

