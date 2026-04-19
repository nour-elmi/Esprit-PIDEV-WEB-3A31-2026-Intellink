<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260411182245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversation_utilisateur (conversation_id INT NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_F3031EC39AC0396 (conversation_id), INDEX IDX_F3031EC3FB88E14F (utilisateur_id), PRIMARY KEY(conversation_id, utilisateur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, requester_id INT NOT NULL, receiver_id INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F284D94ED442CF4 (requester_id), INDEX IDX_F284D94CD53EDB6 (receiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_F3031EC39AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_F3031EC3FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94ED442CF4 FOREIGN KEY (requester_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE message ADD sender_id INT NOT NULL, ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP auteur, CHANGE contenu content LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES utilisateurs (id)');
        $this->addSql('CREATE INDEX IDX_B6BD307FF624B39D ON message (sender_id)');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_1CAD6B76A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('DROP INDEX fk_user_idx ON reclamations');
        $this->addSql('CREATE INDEX IDX_1CAD6B76A76ED395 ON reclamations (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_F3031EC39AC0396');
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_F3031EC3FB88E14F');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94ED442CF4');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('DROP TABLE conversation_utilisateur');
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('DROP INDEX IDX_B6BD307FF624B39D ON message');
        $this->addSql('ALTER TABLE message ADD auteur VARCHAR(255) NOT NULL, DROP sender_id, DROP created_at, CHANGE content contenu LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY FK_1CAD6B76A76ED395');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY FK_1CAD6B76A76ED395');
        $this->addSql('DROP INDEX idx_1cad6b76a76ed395 ON reclamations');
        $this->addSql('CREATE INDEX fk_user_idx ON reclamations (user_id)');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_1CAD6B76A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
    }
}
