<?php

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class AddUserRelation implements Migration
{

    /**
     * @inheritDoc
     */
    public function getMigrationVersion(): string
    {
        return 'v1_2';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        #ref:adbrain add comment if you get error while running migration
        $this->addOroEmailOriginForeignKeys($schema);
    }


    /**
     * Add oro_email_address foreign keys.
     */


    /**
     * Add oro_email_origin foreign keys.
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
