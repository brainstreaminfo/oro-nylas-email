<?php

/**
 * Add Email Configuration Fields Migration.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_4
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_4;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add Email Configuration Fields Migration.
 *
 * Migration to add email configuration fields to the user table.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_4
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class AddEmailConfigurationFields implements Migration
{
    /**
     * Get migration version.
     *
     * @return string
     */
    public function getMigrationVersion(): string
    {
        return 'v1_4';
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
        $table = $schema->getTable('oro_user');
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
    public function addFields(Schema $schema): void
    {
        $table = $schema->getTable('oro_user');
        if (!$table->hasColumn('is_multiple_email')) {
            $table->addColumn(
                'is_multiple_email',
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
                            'label' => 'Is multiple email',
                        ],
                        'form' => ['is_enabled' => true],
                        'view' => ['is_displayable' => false]
                    ]
                ]
            );
        }
        if (!$table->hasColumn('number_of_email')) {
            $table->addColumn(
                'number_of_email',
                'integer',
                [
                    'default' => 0,
                    'oro_options' => [
                        'extend' => [
                            'is_extend' => true,
                            'owner' => ExtendScope::OWNER_CUSTOM,
                            'state' => 'Active',
                        ],
                        'entity' => [
                            'label' => 'Number of email',
                        ],
                        'form' => ['is_enabled' => true],
                        'view' => ['is_displayable' => false]
                    ]
                ]
            );
        }
    }
}
