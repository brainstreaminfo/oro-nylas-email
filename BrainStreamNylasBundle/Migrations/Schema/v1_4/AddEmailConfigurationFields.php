<?php

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_4;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddEmailConfigurationFields implements Migration
{
    /**
     * @inheritDoc
     */
    public function getMigrationVersion(): string
    {
        return 'v1_4';
    }

    /**
     * @inheritDoc
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_user');
        /*$table->addOption(
            'entity',
            [
                'class_name' => 'BrainStream\Bundle\NylasBundle\Entity\NylasUser',
            ]
        );
        $table->addOption(
            'extend',
            [
                'is_extend' => true,
                'owner' => ExtendScope::OWNER_CUSTOM,
                'state' => ExtendScope::STATE_ACTIVE
            ]
        );*/
        /** Tables generation **/
        $this->addFields($schema);
    }

    /**
     * Modifies the given schema to apply necessary changes of a database
     * The given query bag can be used to apply additional SQL queries before and after schema changes
     *
     * @param Schema   $schema
     * @param QueryBag $queries
     *
     * @return void
     */
    public function addFields(Schema $schema): void
    {
        $table = $schema->getTable('oro_user');
        if (!$table->hasColumn('is_multiple_email')) {
            $table->addColumn('is_multiple_email', 'boolean', [
                'default' => false,
                'oro_options' => [
                    'extend' => [
                        'is_extend' => true,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => 'Active',
                        //'target_entity' => 'Oro\Bundle\UserBundle\Entity\User'
                    ],
                    'entity' => [
                        'label' => 'Is multiple email',
                        //'getter' => 'isMultipleEmail',
                        //'setter' => 'setIsMultipleEmail'
                    ],
                    'form' => ['is_enabled' => true],
                    'view' => ['is_displayable' => false]
                ]
            ]);
        }
        if (!$table->hasColumn('number_of_email')) {
            $table->addColumn('number_of_email', 'integer', [
                'default' => 0,
                'oro_options' => [
                    'extend' => [
                        'is_extend' => true,
                        'owner' => ExtendScope::OWNER_CUSTOM,
                        'state' => 'Active',
                        //'target_entity' => 'Oro\Bundle\UserBundle\Entity\User'
                    ],
                    'entity' => [
                        'label' => 'Number of email',
                        //'getter' => 'getNumberOfEmail',
                        //'setter' => 'setNumberOfEmail'
                    ],
                    'form' => ['is_enabled' => true],
                    'view' => ['is_displayable' => false]
                ]
            ]);
        }
    }
}
