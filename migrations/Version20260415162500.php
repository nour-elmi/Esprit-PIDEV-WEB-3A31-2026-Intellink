<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415162500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la table favori_projet pour les projets favoris des utilisateurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favori_projet (id_favori INT AUTO_INCREMENT NOT NULL, id_user INT NOT NULL, id_projet INT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_FAVORI_USER (id_user), INDEX IDX_FAVORI_PROJET (id_projet), UNIQUE INDEX uniq_favori_user_projet (id_user, id_projet), PRIMARY KEY(id_favori)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE favori_projet ADD CONSTRAINT FK_FAVORI_PROJET FOREIGN KEY (id_projet) REFERENCES projet (id_projet) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE favori_projet DROP FOREIGN KEY FK_FAVORI_PROJET');
        $this->addSql('DROP TABLE favori_projet');
    }
}

