<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407140520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact CHANGE logo logo VARCHAR(255) DEFAULT NULL, CHANGE phone phone VARCHAR(50) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE website website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_item CHANGE contact_name contact_name VARCHAR(255) DEFAULT NULL, CHANGE contact_logo contact_logo VARCHAR(255) DEFAULT NULL, CHANGE contact_phone contact_phone VARCHAR(50) DEFAULT NULL, CHANGE contact_email contact_email VARCHAR(255) DEFAULT NULL, CHANGE p_name p_name VARCHAR(255) DEFAULT NULL, CHANGE p_logo p_logo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sale_item CHANGE contact_name contact_name VARCHAR(255) DEFAULT NULL, CHANGE contact_logo contact_logo VARCHAR(255) DEFAULT NULL, CHANGE contact_phone contact_phone VARCHAR(50) DEFAULT NULL, CHANGE contact_email contact_email VARCHAR(255) DEFAULT NULL, CHANGE p_name p_name VARCHAR(255) DEFAULT NULL, CHANGE p_logo p_logo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD roles JSON NOT NULL, DROP role, CHANGE avatar avatar VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact CHANGE logo logo VARCHAR(255) DEFAULT \'NULL\', CHANGE phone phone VARCHAR(50) DEFAULT \'NULL\', CHANGE email email VARCHAR(255) DEFAULT \'NULL\', CHANGE website website VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE purchase_item CHANGE contact_name contact_name VARCHAR(255) DEFAULT \'NULL\', CHANGE contact_logo contact_logo VARCHAR(255) DEFAULT \'NULL\', CHANGE contact_phone contact_phone VARCHAR(50) DEFAULT \'NULL\', CHANGE contact_email contact_email VARCHAR(255) DEFAULT \'NULL\', CHANGE p_name p_name VARCHAR(255) DEFAULT \'NULL\', CHANGE p_logo p_logo VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE sale_item CHANGE contact_name contact_name VARCHAR(255) DEFAULT \'NULL\', CHANGE contact_logo contact_logo VARCHAR(255) DEFAULT \'NULL\', CHANGE contact_phone contact_phone VARCHAR(50) DEFAULT \'NULL\', CHANGE contact_email contact_email VARCHAR(255) DEFAULT \'NULL\', CHANGE p_name p_name VARCHAR(255) DEFAULT \'NULL\', CHANGE p_logo p_logo VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user ADD role VARCHAR(50) NOT NULL, DROP roles, CHANGE avatar avatar VARCHAR(255) DEFAULT \'NULL\'');
    }
}
