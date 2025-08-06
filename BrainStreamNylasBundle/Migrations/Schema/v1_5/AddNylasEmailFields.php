<?php

/**
 * Add Nylas Email Fields Migration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_5
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add Nylas Email Fields Migration.
 *
 * Migration to add Nylas-specific fields to the email table.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_5
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class AddNylasEmailFields implements Migration
{
    /**
     * Get migration version.
     *
     * @return string
     */
    public function getMigrationVersion(): string
    {
        return 'v1_5';
    }

    /**
     * Modifies the given schema to apply necessary changes of a database
     * The given query bag can be used to apply additional SQL queries before and after schema changes
     *
     * @param Schema   $schema  The schema
     * @param QueryBag $queries The query bag
     *
     * @return void
     *
     * @throws \Exception
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->addFields($schema);
    }

    /**
     * Generate table oro_email_nylas execution.
     *
     * @param Schema $schema The schema
     *
     * @return void
     */
    protected function addFields(Schema $schema): void
    {
        $table = $schema->getTable('oro_email');
        //$table->addColumn('uid', 'string', ['notnull' => false, 'length' => 255]);
        //$table->addColumn('unread', 'boolean', ['notnull' => false]);
        //$table->addColumn('has_attachments', 'boolean');

        if (!$table->hasColumn('uid')) {
            $table->addColumn(
                'uid',
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

        if (!$table->hasColumn('unread')) {
            $table->addColumn(
                'unread',
                'boolean',
                [
                    'default' => false,
                    'oro_options' => [
                        'extend' => [
                            'is_extend' => true,
                            'owner' => ExtendScope::OWNER_CUSTOM,
                            'state' => 'Active',
                        ],
                        'entity' => [
                            'label' => 'Unread',
                        ],
                        'form' => ['is_enabled' => true],
                        'view' => ['is_displayable' => false]
                    ]
                ]
            );
        }

        if (!$table->hasColumn('has_attachments')) {
            $table->addColumn(
                'has_attachments',
                'boolean',
                [
                    'default' => false,
                    'oro_options' => [
                        'extend' => [
                            'is_extend' => true,
                            'owner' => ExtendScope::OWNER_CUSTOM,
                            'state' => 'Active',
                        ],
                        'entity' => [
                            'label' => 'Has Attachments',
                        ],
                        'form' => ['is_enabled' => true],
                        'view' => ['is_displayable' => false]
                    ]
                ]
            );
        }
    }
}
