<?php
/**
 * Use to hide local mailbox at https://oronylasext.local/email/user-emails ->compose email ->from
 * this is used along with template override at BrainStreamNylasBundle/Resources/views/Form/fields.html.twig
 */

namespace BrainStream\Bundle\NylasBundle\Form\Extension;

use Oro\Bundle\EmailBundle\Form\Type\EmailType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Doctrine\ORM\EntityManagerInterface;

class EmailTypeExtension extends AbstractTypeExtension
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getExtendedTypes(): iterable
    {
        return [EmailType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        //return;
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
                    //$nylasOriginIds = $conn->fetchFirstColumn("SELECT id FROM oro_email_origin WHERE name = 'nylasemailorigin'");

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
