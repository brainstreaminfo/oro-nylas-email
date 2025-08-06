<?php

/**
 * Email Type Extension.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * Used to hide local mailbox at email compose form.
 * This is used along with template override at BrainStreamNylasBundle/Resources/views/Form/fields.html.twig
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Form\Extension
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Form\Extension;

use Oro\Bundle\EmailBundle\Form\Type\EmailType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Email Type Extension.
 *
 * Extension for hiding local mailbox in email compose form.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Form\Extension
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailTypeExtension extends AbstractTypeExtension
{
    private EntityManagerInterface $em;

    /**
     * Constructor for EmailTypeExtension.
     *
     * @param EntityManagerInterface $em The entity manager
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Get extended types.
     *
     * @return iterable
     */
    public static function getExtendedTypes(): iterable
    {
        return [EmailType::class];
    }

    /**
     * Build the form.
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The form options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(
            \Symfony\Component\Form\FormEvents::PRE_SET_DATA,
            function (\Symfony\Component\Form\FormEvent $event) {
                $form = $event->getForm();
                $data = $event->getData();
                if ($form->has('origin')) {
                    $originField = $form->get('origin');
                    $config = $originField->getConfig();
                    $options = $config->getOptions();

                    // Fetch all Nylas origin IDs
                    $conn = $this->em->getConnection();

                    $nylasOrigins = $conn->fetchAllAssociative("SELECT id, mailbox_name FROM oro_email_origin WHERE name = 'nylasemailorigin'");
                    $nylasOriginMap = [];
                    foreach ($nylasOrigins as $row) {
                        $nylasOriginMap[$row['id']] = $row['mailbox_name'];
                    }

                    // Filter choices by ID only (the part before the |)
                    $options['choices'] = array_filter(
                        $options['choices'],
                        function ($value) use ($nylasOriginMap) {
                            $parts = explode('|', $value);
                            $id = $parts[0];
                            $mailboxName = isset($parts[1]) ? $parts[1] : '';
                            return isset($nylasOriginMap[$id]) && $nylasOriginMap[$id] === $mailboxName;
                        }
                    );

                    // Remove and re-add the field with filtered choices
                    $form->remove('origin');
                    $form->add('origin', $config->getType()->getInnerType()::class, $options);
                }
            }
        );
    }
}
