<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260402204117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE collaboration DROP FOREIGN KEY `collaboration_ibfk_1`');
        $this->addSql('ALTER TABLE collaboration DROP FOREIGN KEY `fk_collaboration_utilisateurs`');
        $this->addSql('ALTER TABLE elements_collaboration DROP FOREIGN KEY `elements_collaboration_ibfk_1`');
        $this->addSql('ALTER TABLE liste_participation DROP FOREIGN KEY `liste_participation_ibfk_1`');
        $this->addSql('ALTER TABLE liste_participation DROP FOREIGN KEY `liste_participation_ibfk_2`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_destinataire`');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY `fk_expediteur`');
        $this->addSql('ALTER TABLE offre_emploi DROP FOREIGN KEY `fk_offre_user`');
        $this->addSql('ALTER TABLE participants DROP FOREIGN KEY `participants_ibfk_1`');
        $this->addSql('DROP TABLE action_log');
        $this->addSql('DROP TABLE collaboration');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE elements_collaboration');
        $this->addSql('DROP TABLE images');
        $this->addSql('DROP TABLE liste_participation');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE offre_emploi');
        $this->addSql('DROP TABLE participants');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE reaction');
        $this->addSql('DROP TABLE report');
        $this->addSql('ALTER TABLE formation MODIFY idFormation INT NOT NULL');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE domaine domaine VARCHAR(255) DEFAULT NULL, CHANGE niveau niveau VARCHAR(255) DEFAULT NULL, CHANGE idFormation id_formation INT AUTO_INCREMENT NOT NULL, CHANGE urlVideo url_video VARCHAR(255) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_formation)');
        $this->addSql('ALTER TABLE formation RENAME INDEX fk_formateur TO IDX_404021BF119C5519');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_1`');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY `participation_ibfk_2`');
        $this->addSql('ALTER TABLE participation MODIFY idParticipation INT NOT NULL');
        $this->addSql('ALTER TABLE participation ADD date_inscription DATE DEFAULT NULL, DROP dateInscription, CHANGE poste_actuel poste_actuel VARCHAR(255) DEFAULT NULL, CHANGE attentes attentes LONGTEXT DEFAULT NULL, CHANGE score score DOUBLE PRECISION DEFAULT NULL, CHANGE idParticipation id_participation INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_participation)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FBCAA0AE9 FOREIGN KEY (idFormation) REFERENCES formation (idFormation)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F5D419CCB FOREIGN KEY (idUtilisateur) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE participation RENAME INDEX idformation TO IDX_AB55E24FBCAA0AE9');
        $this->addSql('ALTER TABLE participation RENAME INDEX idutilisateur TO IDX_AB55E24F5D419CCB');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY `question_ibfk_1`');
        $this->addSql('ALTER TABLE question MODIFY idQuestion INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD reponse_correcte VARCHAR(255) DEFAULT NULL, DROP reponseCorrecte, CHANGE enonce enonce LONGTEXT NOT NULL, CHANGE points points DOUBLE PRECISION DEFAULT NULL, CHANGE idQuestion id_question INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_question)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494ED7EFA40C FOREIGN KEY (idQuiz) REFERENCES quiz (idQuiz)');
        $this->addSql('ALTER TABLE question RENAME INDEX idquiz TO IDX_B6F7494ED7EFA40C');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY `quiz_ibfk_1`');
        $this->addSql('ALTER TABLE quiz MODIFY idQuiz INT NOT NULL');
        $this->addSql('ALTER TABLE quiz ADD score_max DOUBLE PRECISION DEFAULT NULL, DROP scoreMax, CHANGE titre titre VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE idQuiz id_quiz INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_quiz)');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT FK_A412FA92BCAA0AE9 FOREIGN KEY (idFormation) REFERENCES formation (idFormation)');
        $this->addSql('ALTER TABLE quiz RENAME INDEX idformation TO IDX_A412FA92BCAA0AE9');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY `fk_reclamation_user`');
        $this->addSql('ALTER TABLE reclamations CHANGE user_id user_id INT DEFAULT NULL, CHANGE objet objet VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE priorite priorite VARCHAR(255) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE reponse_admin reponse_admin LONGTEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME DEFAULT NULL, CHANGE piece_jointe piece_jointe VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT FK_1CAD6B76A76ED395 FOREIGN KEY (user_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX fk_user_idx TO IDX_1CAD6B76A76ED395');
        $this->addSql('ALTER TABLE utilisateurs ADD auth_method VARCHAR(255) NOT NULL, ADD statut_compte VARCHAR(255) DEFAULT NULL, DROP authMethod, DROP statutCompte, CHANGE nom nom VARCHAR(255) NOT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE skills skills VARCHAR(255) DEFAULT NULL, CHANGE image image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_log (id INT AUTO_INCREMENT NOT NULL, actor_id INT NOT NULL, projet_id INT DEFAULT NULL, action VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, details VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX idx_actor_date (actor_id, created_at), INDEX idx_action_date (action, created_at), INDEX idx_projet_date (projet_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE collaboration (id_collaboration INT AUTO_INCREMENT NOT NULL, id_projet INT NOT NULL, date_creation DATETIME DEFAULT \'current_timestamp()\', id_user INT NOT NULL, etat VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'\'\'EN_ATTENTE\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, date_decision DATETIME DEFAULT \'NULL\', role_souhaite VARCHAR(60) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, motivation TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, disponibilite VARCHAR(60) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, portfolio VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, score_ia INT DEFAULT NULL, resume_ia TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, recommandation_ia VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, INDEX id_projet (id_projet), INDEX fk_collaboration_utilisateurs (id_user), PRIMARY KEY (id_collaboration)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE comment (commentid INT AUTO_INCREMENT NOT NULL, postid INT NOT NULL, userid INT NOT NULL, content VARCHAR(1000) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'\'\'PUBLISHED\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, createdAt DATETIME DEFAULT \'current_timestamp()\' NOT NULL, parentCommentId INT DEFAULT NULL, isEdited TINYINT DEFAULT 0, updatedAt DATETIME DEFAULT \'NULL\', PRIMARY KEY (commentid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE elements_collaboration (id_element INT AUTO_INCREMENT NOT NULL, id_collaboration INT NOT NULL, type_element VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, titre VARCHAR(200) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, contenu TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, auteur VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, responsable VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, chemin_fichier VARCHAR(500) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT \'current_timestamp()\', INDEX id_collaboration (id_collaboration), PRIMARY KEY (id_element)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE images (imageId INT AUTO_INCREMENT NOT NULL, postid INT NOT NULL, imageUrl VARCHAR(1000) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (imageId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE liste_participation (id_participation INT AUTO_INCREMENT NOT NULL, date_participation DATE NOT NULL, statut ENUM(\'en_attente\', \'acceptee\', \'refusee\') CHARACTER SET utf8mb4 DEFAULT \'\'\'en_attente\'\'\' COLLATE `utf8mb4_general_ci`, date_reponse DATE DEFAULT \'NULL\', id_offre INT DEFAULT NULL, id_user INT DEFAULT NULL, cv VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, score INT DEFAULT 0, nom_p VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, prenom_p VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, skills TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, INDEX id_offre (id_offre), INDEX id_user (id_user), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, id_expediteur INT NOT NULL, id_destinataire INT NOT NULL, contenu TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_envoi DATETIME DEFAULT \'current_timestamp()\' NOT NULL, lu TINYINT DEFAULT 0, INDEX fk_destinataire (id_destinataire), INDEX fk_expediteur (id_expediteur), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE notifications (id_notification INT AUTO_INCREMENT NOT NULL, id_user INT NOT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, message TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, is_read TINYINT DEFAULT 0, created_at DATETIME DEFAULT \'current_timestamp()\' NOT NULL, INDEX idx_created (created_at), INDEX idx_user_read (id_user, is_read), PRIMARY KEY (id_notification)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE offre_emploi (id_offre INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, type_contrat ENUM(\'CDI\', \'CDD\', \'Stage\', \'Freelance\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, salaire NUMERIC(10, 2) DEFAULT \'NULL\', date_debut DATE DEFAULT \'NULL\', date_expiration DATE DEFAULT \'NULL\', statut ENUM(\'ouverte\', \'fermee\') CHARACTER SET utf8mb4 DEFAULT \'\'\'ouverte\'\'\' COLLATE `utf8mb4_general_ci`, nom_entreprise VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, id_user INT DEFAULT NULL, INDEX fk_offre_user (id_user), PRIMARY KEY (id_offre)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE participants (id_participation INT AUTO_INCREMENT NOT NULL, id_projet INT NOT NULL, nom_participant VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, date_ajout DATETIME DEFAULT \'current_timestamp()\', INDEX id_projet (id_projet), PRIMARY KEY (id_participation)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE post (postId INT AUTO_INCREMENT NOT NULL, userid INT NOT NULL, content VARCHAR(1000) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, status VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'\'\'PUBLISHED\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, createdAt DATETIME DEFAULT \'current_timestamp()\' NOT NULL, isEdited TINYINT DEFAULT 0, isPinned TINYINT DEFAULT 0, isLocked TINYINT DEFAULT 0, updatedAt DATETIME DEFAULT \'NULL\', PRIMARY KEY (postId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE projet (id_projet INT AUTO_INCREMENT NOT NULL, titre VARCHAR(200) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, description TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, createur VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, statut VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT \'\'\'en cours\'\'\' COLLATE `utf8mb4_general_ci`, date_creation DATETIME DEFAULT \'current_timestamp()\', prix NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, PRIMARY KEY (id_projet)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE reaction (reactionid INT AUTO_INCREMENT NOT NULL, postid INT NOT NULL, type VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, userid INT NOT NULL, UNIQUE INDEX userid (userid, postid), PRIMARY KEY (reactionid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE report (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, targetId INT DEFAULT NULL, reportId INT DEFAULT NULL, reason VARCHAR(200) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, status ENUM(\'OPEN\', \'ACCEPTED\', \'REJECTED\') CHARACTER SET utf8mb4 DEFAULT \'\'\'OPEN\'\'\' NOT NULL COLLATE `utf8mb4_general_ci`, createdAt DATETIME DEFAULT \'current_timestamp()\' NOT NULL, handledBy INT DEFAULT NULL, handledAt DATETIME DEFAULT \'NULL\', targetType ENUM(\'POST\', \'COMMENT\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, reporterId INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE collaboration ADD CONSTRAINT `collaboration_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projet (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE collaboration ADD CONSTRAINT `fk_collaboration_utilisateurs` FOREIGN KEY (id_user) REFERENCES utilisateurs (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE elements_collaboration ADD CONSTRAINT `elements_collaboration_ibfk_1` FOREIGN KEY (id_collaboration) REFERENCES collaboration (id_collaboration) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE liste_participation ADD CONSTRAINT `liste_participation_ibfk_1` FOREIGN KEY (id_offre) REFERENCES offre_emploi (id_offre) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE liste_participation ADD CONSTRAINT `liste_participation_ibfk_2` FOREIGN KEY (id_user) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_destinataire` FOREIGN KEY (id_destinataire) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT `fk_expediteur` FOREIGN KEY (id_expediteur) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE offre_emploi ADD CONSTRAINT `fk_offre_user` FOREIGN KEY (id_user) REFERENCES utilisateurs (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participants ADD CONSTRAINT `participants_ibfk_1` FOREIGN KEY (id_projet) REFERENCES projet (id_projet) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE formation MODIFY id_formation INT NOT NULL');
        $this->addSql('ALTER TABLE formation CHANGE titre titre VARCHAR(100) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE domaine domaine VARCHAR(50) DEFAULT \'NULL\', CHANGE niveau niveau VARCHAR(30) DEFAULT \'NULL\', CHANGE id_formation idFormation INT AUTO_INCREMENT NOT NULL, CHANGE url_video urlVideo VARCHAR(255) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idFormation)');
        $this->addSql('ALTER TABLE formation RENAME INDEX idx_404021bf119c5519 TO fk_formateur');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FBCAA0AE9');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F5D419CCB');
        $this->addSql('ALTER TABLE participation MODIFY id_participation INT NOT NULL');
        $this->addSql('ALTER TABLE participation ADD dateInscription DATE DEFAULT \'NULL\', DROP date_inscription, CHANGE poste_actuel poste_actuel VARCHAR(150) DEFAULT \'NULL\', CHANGE attentes attentes TEXT DEFAULT NULL, CHANGE score score FLOAT DEFAULT \'NULL\', CHANGE id_participation idParticipation INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idParticipation)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_1` FOREIGN KEY (idFormation) REFERENCES formation (idFormation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT `participation_ibfk_2` FOREIGN KEY (idUtilisateur) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation RENAME INDEX idx_ab55e24fbcaa0ae9 TO idFormation');
        $this->addSql('ALTER TABLE participation RENAME INDEX idx_ab55e24f5d419ccb TO idUtilisateur');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494ED7EFA40C');
        $this->addSql('ALTER TABLE question MODIFY id_question INT NOT NULL');
        $this->addSql('ALTER TABLE question ADD reponseCorrecte VARCHAR(10) DEFAULT \'NULL\', DROP reponse_correcte, CHANGE enonce enonce TEXT NOT NULL, CHANGE points points FLOAT DEFAULT \'NULL\', CHANGE id_question idQuestion INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idQuestion)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT `question_ibfk_1` FOREIGN KEY (idQuiz) REFERENCES quiz (idQuiz) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE question RENAME INDEX idx_b6f7494ed7efa40c TO idQuiz');
        $this->addSql('ALTER TABLE quiz DROP FOREIGN KEY FK_A412FA92BCAA0AE9');
        $this->addSql('ALTER TABLE quiz MODIFY id_quiz INT NOT NULL');
        $this->addSql('ALTER TABLE quiz ADD scoreMax FLOAT DEFAULT \'NULL\', DROP score_max, CHANGE titre titre VARCHAR(100) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE id_quiz idQuiz INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (idQuiz)');
        $this->addSql('ALTER TABLE quiz ADD CONSTRAINT `quiz_ibfk_1` FOREIGN KEY (idFormation) REFERENCES formation (idFormation) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE quiz RENAME INDEX idx_a412fa92bcaa0ae9 TO idFormation');
        $this->addSql('ALTER TABLE reclamations DROP FOREIGN KEY FK_1CAD6B76A76ED395');
        $this->addSql('ALTER TABLE reclamations CHANGE objet objet VARCHAR(150) NOT NULL, CHANGE description description TEXT NOT NULL, CHANGE type type VARCHAR(50) NOT NULL, CHANGE priorite priorite VARCHAR(20) DEFAULT \'\'\'MOYENNE\'\'\', CHANGE statut statut VARCHAR(20) DEFAULT \'\'\'OUVERT\'\'\', CHANGE reponse_admin reponse_admin TEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME DEFAULT \'current_timestamp()\', CHANGE piece_jointe piece_jointe VARCHAR(255) DEFAULT \'NULL\', CHANGE user_id user_id INT NOT NULL');
        $this->addSql('ALTER TABLE reclamations ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (user_id) REFERENCES utilisateurs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamations RENAME INDEX idx_1cad6b76a76ed395 TO fk_user_idx');
        $this->addSql('ALTER TABLE utilisateurs ADD authMethod VARCHAR(50) DEFAULT \'\'\'NONE\'\'\' NOT NULL, ADD statutCompte ENUM(\'EN_ATTENTE\', \'ACTIF\', \'REJETE\') DEFAULT \'\'\'EN_ATTENTE\'\'\', DROP auth_method, DROP statut_compte, CHANGE nom nom VARCHAR(20) NOT NULL, CHANGE role role VARCHAR(20) NOT NULL, CHANGE skills skills VARCHAR(255) DEFAULT \'NULL\', CHANGE image image VARCHAR(255) DEFAULT \'NULL\'');
    }
}
