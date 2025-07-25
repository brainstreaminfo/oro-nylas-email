<?php

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_5;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddNylasEmailFields implements Migration
{

    /**
     * Modifies the given schema to apply necessary changes of a database
     * The given query bag can be used to apply additional SQL queries before and after schema changes
     *
     * @param Schema   $schema
     * @param QueryBag $queries
     *
     * @return void
     *
     * @throws \Exception
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->addFields($schema);
    }


    /**
     * Generate table oro_email_nylas execution
     *
     * @param Schema $schema
     */
    protected function addFields(Schema $schema)
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
            $table->addColumn('unread', 'boolean', [
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
            ]);
        }

        if (!$table->hasColumn('has_attachments')) {
            $table->addColumn('has_attachments', 'boolean', [
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
            ]);
        }
    }
}
