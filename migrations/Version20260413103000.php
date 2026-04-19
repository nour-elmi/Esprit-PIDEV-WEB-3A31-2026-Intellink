<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la table favori_formation pour les favoris utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favori_formation (idFavori INT AUTO_INCREMENT NOT NULL, idFormation INT NOT NULL, idUtilisateur INT NOT NULL, dateAjout DATETIME NOT NULL, INDEX IDX_582ED6A5BCAA0AE9 (idFormation), INDEX IDX_582ED6A55D419CCB (idUtilisateur), UNIQUE INDEX uniq_favori_user_formation (idUtilisateur, idFormation), PRIMARY KEY(idFavori)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE favori_formation ADD CONSTRAINT FK_582ED6A5BCAA0AE9 FOREIGN KEY (idFormation) REFERENCES formation (idFormation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favori_formation ADD CONSTRAINT FK_582ED6A55D419CCB FOREIGN KEY (idUtilisateur) REFERENCES utilisateurs (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favori_formation DROP FOREIGN KEY FK_582ED6A5BCAA0AE9');
        $this->addSql('ALTER TABLE favori_formation DROP FOREIGN KEY FK_582ED6A55D419CCB');
        $this->addSql('DROP TABLE favori_formation');
    }
}

