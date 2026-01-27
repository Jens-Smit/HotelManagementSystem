<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix schema issues for PMS entities
 */
final class Version20260126233340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix schema issues for PMS entities';
    }

    public function up(Schema $schema): void
    {
        // Update rooms table
        $this->addSql('ALTER TABLE rooms
            CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE ical_url_booking ical_url_booking VARCHAR(500) DEFAULT NULL,
            CHANGE ical_url_homeaway ical_url_homeaway VARCHAR(500) DEFAULT NULL,
            CHANGE last_sync_booking last_sync_booking DATETIME DEFAULT NULL,
            CHANGE last_sync_homeaway last_sync_homeaway DATETIME DEFAULT NULL,
            CHANGE version version INT DEFAULT 1 NOT NULL');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_7CA11A96989D9B62 ON rooms (slug)');

        // Update bookings table
        $this->addSql('ALTER TABLE bookings
            DROP INDEX unique_booking,
            ADD UNIQUE INDEX unique_booking (room_id, start_date, end_date, status)');

        $this->addSql('ALTER TABLE bookings
            CHANGE id id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE room_id room_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE guest_phone guest_phone VARCHAR(50) DEFAULT NULL,
            CHANGE external_id external_id VARCHAR(100) DEFAULT NULL,
            CHANGE version version INT DEFAULT 1 NOT NULL');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_7A853C35C92940F6 ON bookings (booking_hash)');

        // Update availability_snapshots table
        $this->addSql('ALTER TABLE availability_snapshots
            CHANGE room_id room_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // Revert changes
        $this->addSql('ALTER TABLE rooms
            CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE ical_url_booking ical_url_booking VARCHAR(500) DEFAULT NULL,
            CHANGE ical_url_homeaway ical_url_homeaway VARCHAR(500) DEFAULT NULL,
            CHANGE last_sync_booking last_sync_booking DATETIME DEFAULT NULL,
            CHANGE last_sync_homeaway last_sync_homeaway DATETIME DEFAULT NULL,
            CHANGE version version INT NOT NULL');

        $this->addSql('DROP INDEX UNIQ_7CA11A96989D9B62 ON rooms');

        $this->addSql('ALTER TABLE bookings
            DROP INDEX unique_booking,
            ADD UNIQUE INDEX unique_booking (room_id, start_date, end_date, status)');

        $this->addSql('ALTER TABLE bookings
            CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE room_id room_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            CHANGE guest_phone guest_phone VARCHAR(50) DEFAULT NULL,
            CHANGE external_id external_id VARCHAR(100) DEFAULT NULL,
            CHANGE version version INT NOT NULL');

        $this->addSql('DROP INDEX UNIQ_7A853C35C92940F6 ON bookings');

        $this->addSql('ALTER TABLE availability_snapshots
            CHANGE room_id room_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}