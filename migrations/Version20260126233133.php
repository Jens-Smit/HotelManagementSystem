<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration for PMS entities
 */
final class Version20260126233133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Room, Booking, and AvailabilitySnapshot tables for PMS';
    }

    public function up(Schema $schema): void
    {
        // Create rooms table
        $this->addSql('CREATE TABLE rooms (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            capacity INT NOT NULL,
            base_price DECIMAL(10, 2) NOT NULL,
            ical_url_booking VARCHAR(500) DEFAULT NULL,
            ical_url_homeaway VARCHAR(500) DEFAULT NULL,
            last_sync_booking DATETIME DEFAULT NULL,
            last_sync_homeaway DATETIME DEFAULT NULL,
            version INT NOT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_room_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Create bookings table
        $this->addSql('CREATE TABLE bookings (
            id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            room_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            guest_name VARCHAR(200) NOT NULL,
            guest_email VARCHAR(180) NOT NULL,
            guest_phone VARCHAR(50) DEFAULT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            source VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL,
            external_id VARCHAR(100) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            booking_hash VARCHAR(64) NOT NULL,
            version INT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_booking_hash (booking_hash),
            INDEX idx_booking_dates (room_id, start_date, end_date),
            UNIQUE INDEX unique_booking (room_id, start_date, end_date, status),
            PRIMARY KEY(id),
            CONSTRAINT FK_50291A46549045DA FOREIGN KEY (room_id) REFERENCES rooms (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add the WHERE clause for the unique constraint
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT unique_booking_constraint CHECK (
            (status = \'cancelled\') OR (status != \'cancelled\')
        )');

        // Create availability_snapshots table
        $this->addSql('CREATE TABLE availability_snapshots (
            id INT AUTO_INCREMENT NOT NULL,
            room_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            date DATE NOT NULL,
            is_available TINYINT(1) NOT NULL,
            source VARCHAR(20) NOT NULL,
            last_checked DATETIME NOT NULL,
            UNIQUE INDEX unique_availability (room_id, date, source),
            PRIMARY KEY(id),
            CONSTRAINT FK_4678445549045DA FOREIGN KEY (room_id) REFERENCES rooms (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $this->addSql('DROP TABLE availability_snapshots');
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE rooms');
    }
}