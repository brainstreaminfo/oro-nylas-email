<?php

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_3;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class AddNylasEmailFolderFields implements Migration
{

    /**
     * @inheritDoc
     */
    public function getMigrationVersion(): string
    {
        return 'v1_3';
    }

    /**
     * @inheritDoc
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
        /** Tables generation **/
        $this->addFields($schema);
    }


    /**
     * Create oro_email_origin table
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
