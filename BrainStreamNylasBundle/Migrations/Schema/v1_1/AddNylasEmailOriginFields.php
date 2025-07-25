<?php

namespace BrainStream\Bundle\NylasBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddNylasEmailOriginFields implements Migration
{
    public function getMigrationVersion(): string
    {
        return 'v1_1';
    }

    public function up(Schema $schema, QueryBag $queries): void
    {
        // Ensure NylasEmailOrigin is recognized as extendable
        $table = $schema->getTable('oro_email_origin');
        $table->addOption(
            'entity',
            [
                'class_name' => 'BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin'
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

        $this->addFields($schema);
    }

    private function addFields(Schema $schema): void
    {
        $table = $schema->getTable('oro_email_origin');

        $fields = [
            'account_id' => ['type' => 'string', 'length' => 255, 'notnull' => false, 'label' => 'Account ID (Nylas Grant ID)'],
            'provider' => ['type' => 'string', 'length' => 255, 'notnull' => false, 'label' => 'Provider'],
            'token_type' => ['type' => 'string', 'length' => 255, 'notnull' => false, 'label' => 'Token Type'],
            'is_default' => ['type' => 'boolean', 'default' => false, 'notnull' => true, 'label' => 'Is Default'],
            'created_at' => ['type' => 'datetime', 'notnull' => false, 'label' => 'Created At'],
        ];

        foreach ($fields as $fieldName => $options) {
            if (!$table->hasColumn($fieldName)) {
                $table->addColumn(
                    $fieldName,
                    $options['type'],
                    [
                        'notnull' => $options['notnull'],
                        'length' => $options['length'] ?? null,
                        'default' => $options['default'] ?? null,
                        'oro_options' => [
                            'extend' => [
                                'is_extend' => true,
                                'owner' => ExtendScope::OWNER_CUSTOM,
                                'state' => ExtendScope::STATE_ACTIVE,
                                'target_entity' => 'BrainStream\Bundle\NylasBundle\Entity\NylasEmailOrigin'
                            ],
                            'entity' => ['label' => $options['label']],
                            'form' => ['is_enabled' => true],
                            'view' => ['is_displayable' => false]
                        ]
                    ]
                );
            }
        }
    }
}
