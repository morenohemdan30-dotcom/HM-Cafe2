<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028120221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create gaming setups and bookings tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE gaming_setups (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                price_per_hour NUMERIC(10, 2) NOT NULL,
                specs LONGTEXT NOT NULL,
                image VARCHAR(255) DEFAULT NULL,
                sort_order INT NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE bookings (
                id INT AUTO_INCREMENT NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(255) NOT NULL,
                setup_id INT NOT NULL,
                booking_date DATE NOT NULL,
                start_time TIME NOT NULL,
                hours INT NOT NULL,
                status VARCHAR(255) NOT NULL,
                payment_method VARCHAR(255) DEFAULT NULL,
                payment_reference VARCHAR(255) DEFAULT NULL,
                payment_status VARCHAR(255) DEFAULT NULL,
                message LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_BOOKINGS_SETUP (setup_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_BOOKINGS_SETUP
                    FOREIGN KEY (setup_id)
                    REFERENCES gaming_setups (id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
        ");

        $this->addSql("
            CREATE TABLE messenger_messages (
                id BIGINT AUTO_INCREMENT NOT NULL,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                available_at DATETIME NOT NULL,
                delivered_at DATETIME DEFAULT NULL,
                INDEX IDX_MESSENGER_QUEUE (
                    queue_name,
                    available_at,
                    delivered_at,
                    id
                ),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4
              COLLATE `utf8mb4_unicode_ci`
              ENGINE = InnoDB
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE gaming_setups');
        $this->addSql('DROP TABLE messenger_messages');
    }
}