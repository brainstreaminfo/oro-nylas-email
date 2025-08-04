<?php

/**
 * Add User Relation Migration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_2
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add User Relation Migration.
 *
 * Migration to add user relations to email origin table.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_2
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class AddUserRelation implements Migration
{
    /**
     * Get migration version.
     *
     * @return string
     */
    public function getMigrationVersion(): string
    {
        return 'v1_2';
    }

    /**
     * Up migration.
     *
     * @param Schema   $schema  The schema
     * @param QueryBag $queries The query bag
     *
     * @return void
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        // Add comment if you get error while running migration
        $this->addOroEmailOriginForeignKeys($schema);
    }

    /**
     * Add oro_email_origin foreign keys.
     *
     * @param Schema $schema The schema
     *
     * @return void
     */
    private function addOroEmailOriginForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_email_origin');
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_organization'),
            ['organization_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_user'),
            ['owner_id'],
            ['id'],
            ['onUpdate' => null, 'onDelete' => 'CASCADE']
        );
    }
}
