<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration sécurisée pour le module Utilisateur/Message/Reclamation.
 */
final class Version20260402140948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration sécurisée : Création de messenger_messages et protection des tables du groupe.';
    }

    public function up(Schema $schema): void
    {
        // 1. Création de la table système de Symfony (Obligatoire)
        //$this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // ⚠️ TOUTES LES COMMANDES "DROP TABLE" ONT ÉTÉ SUPPRIMÉES ICI POUR SAUVER LES TABLES DE VOS CAMARADES !
        // ⚠️ LES COMMANDES "DROP FOREIGN KEY" QUI CRÉAIENT DES ERREURS ONT ÉTÉ RETIRÉES.

        // 2. Mise à jour de la table MESSAGES (sans casser les clés existantes)
        $this->addSql('ALTER TABLE messages CHANGE id_expediteur id_expediteur INT DEFAULT NULL, CHANGE id_destinataire id_destinataire INT DEFAULT NULL, CHANGE contenu contenu LONGTEXT NOT NULL, CHANGE date_envoi date_envoi DATETIME NOT NULL, CHANGE lu lu TINYINT(1) DEFAULT NULL');

        // 3. Mise à jour de la table RECLAMATIONS
        $this->addSql('ALTER TABLE reclamations CHANGE user_id user_id INT DEFAULT NULL, CHANGE objet objet VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE priorite priorite VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE reponse_admin reponse_admin LONGTEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME DEFAULT NULL');

        // 4. Mise à jour propre de la table UTILISATEURS
        // Au lieu de DROP (supprimer) vos colonnes, on les renomme proprement pour Doctrine sans perdre vos données.
        $this->addSql('ALTER TABLE utilisateurs CHANGE authMethod auth_method VARCHAR(255) NOT NULL, CHANGE statutCompte statut_compte VARCHAR(255) DEFAULT NULL, CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE role role VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Par sécurité, on bloque le retour en arrière pour ne pas abîmer la base.
        $this->throwIrreversibleMigrationException('Cette migration ne peut pas être annulée en toute sécurité.');
    }
}