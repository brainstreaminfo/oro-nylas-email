<?php

/**
 * Add Nylas Email Folder Fields Migration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_3
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add Nylas Email Folder Fields Migration.
 *
 * Migration to add Nylas-specific fields to the email folder table.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_3
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class AddNylasEmailFolderFields implements Migration
{
    /**
     * Get migration version.
     *
     * @return string
     */
    public function getMigrationVersion(): string
    {
        return 'v1_3';
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
        $table = $schema->getTable('oro_email_folder');
        $table->addOption(
            'entity',
            [
                'class_name' => 'BrainStream\Bundle\NylasBundle\Entity\EmailFolder',
            ]
        );
        $table->addOption(
            'extend',
            [
                'is_extend' => true,
                'owner' => ExtendScope::OWNER_CUSTOM,
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );
        // Tables generation
        $this->addFields($schema);
    }

    /**
     * Add fields to the schema.
     *
     * @param Schema $schema The schema
     *
     * @return void
     */
    private function addFields(Schema $schema): void
    {
        $table = $schema->getTable('oro_email_folder');

        if (!$table->hasColumn('folder_uid')) {
            $table->addColumn(
                'folder_uid',
                'string',
                [
                    'notnull' => false,
                    'length' => 255,
                    'oro_options' => [
                        'extend' => [
                            'is_extend' => true,
                            'owner' => ExtendScope::OWNER_CUSTOM,
                            'state' => 'Active'
                        ],
                        'entity' => ['label' => 'Folder Uid'],
                        'form' => ['is_enabled' => true],
                        'view' => ['is_displayable' => false]
                    ]
                ]
            );
        }
    }
}
