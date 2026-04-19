<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415034747 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_CONV_USER_CONV');
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_CONV_USER_USER');
        $this->addSql('DROP INDEX idx_conv_user_c ON conversation_utilisateur');
        $this->addSql('CREATE INDEX IDX_F3031EC39AC0396 ON conversation_utilisateur (conversation_id)');
        $this->addSql('DROP INDEX idx_conv_user_u ON conversation_utilisateur');
        $this->addSql('CREATE INDEX IDX_F3031EC3FB88E14F ON conversation_utilisateur (utilisateur_id)');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_CONV_USER_CONV FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_CONV_USER_USER FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_FRIEND_REQUEST_REQUESTER');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_FRIEND_REQUEST_RECEIVER');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_FRIEND_REQUEST_REQUESTER');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_FRIEND_REQUEST_RECEIVER');
        $this->addSql('ALTER TABLE friend_request CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94ED442CF4 FOREIGN KEY (requester_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES utilisateurs (id)');
        $this->addSql('DROP INDEX idx_friend_request_requester ON friend_request');
        $this->addSql('CREATE INDEX IDX_F284D94ED442CF4 ON friend_request (requester_id)');
        $this->addSql('DROP INDEX idx_friend_request_receiver ON friend_request');
        $this->addSql('CREATE INDEX IDX_F284D94CD53EDB6 ON friend_request (receiver_id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_FRIEND_REQUEST_REQUESTER FOREIGN KEY (requester_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_FRIEND_REQUEST_RECEIVER FOREIGN KEY (receiver_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_MSG_EXP');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_MSG_EXP');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F10335F61 FOREIGN KEY (expediteur_id) REFERENCES utilisateurs (id)');
        $this->addSql('DROP INDEX idx_msg_exp ON message');
        $this->addSql('CREATE INDEX IDX_B6BD307F10335F61 ON message (expediteur_id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MSG_EXP FOREIGN KEY (expediteur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_1CAD6B76A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('DROP INDEX fk_user_idx ON reclamations');
        $this->addSql('CREATE INDEX IDX_1CAD6B76A76ED395 ON reclamations (user_id)');
        $this->addSql('ALTER TABLE utilisateurs ADD password_recovery_method VARCHAR(255) NOT NULL, CHANGE is_google_authenticator_enabled is_google_authenticator_enabled TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_F3031EC39AC0396');
        $this->addSql('ALTER TABLE conversation_utilisateur DROP FOREIGN KEY FK_F3031EC3FB88E14F');
        $this->addSql('DROP INDEX idx_f3031ec39ac0396 ON conversation_utilisateur');
        $this->addSql('CREATE INDEX IDX_CONV_USER_C ON conversation_utilisateur (conversation_id)');
        $this->addSql('DROP INDEX idx_f3031ec3fb88e14f ON conversation_utilisateur');
        $this->addSql('CREATE INDEX IDX_CONV_USER_U ON conversation_utilisateur (utilisateur_id)');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_F3031EC39AC0396 FOREIGN KEY (conversation_id) REFERENCES conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_utilisateur ADD CONSTRAINT FK_F3031EC3FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94ED442CF4');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94ED442CF4');
        $this->addSql('ALTER TABLE friend_request DROP FOREIGN KEY FK_F284D94CD53EDB6');
        $this->addSql('ALTER TABLE friend_request CHANGE status status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_FRIEND_REQUEST_REQUESTER FOREIGN KEY (requester_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_FRIEND_REQUEST_RECEIVER FOREIGN KEY (receiver_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_f284d94ed442cf4 ON friend_request');
        $this->addSql('CREATE INDEX IDX_FRIEND_REQUEST_REQUESTER ON friend_request (requester_id)');
        $this->addSql('DROP INDEX idx_f284d94cd53edb6 ON friend_request');
        $this->addSql('CREATE INDEX IDX_FRIEND_REQUEST_RECEIVER ON friend_request (receiver_id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94ED442CF4 FOREIGN KEY (requester_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F284D94CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F10335F61');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F10335F61');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_MSG_EXP FOREIGN KEY (expediteur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_b6bd307f10335f61 ON message');
        $this->addSql('CREATE INDEX IDX_MSG_EXP ON message (expediteur_id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F10335F61 FOREIGN KEY (expediteur_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY FK_1CAD6B76A76ED395');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY FK_1CAD6B76A76ED395');
        $this->addSql('DROP INDEX idx_1cad6b76a76ed395 ON reclamations');
        $this->addSql('CREATE INDEX fk_user_idx ON reclamations (user_id)');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_1CAD6B76A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE utilisateurs DROP password_recovery_method, CHANGE is_google_authenticator_enabled is_google_authenticator_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }
}
