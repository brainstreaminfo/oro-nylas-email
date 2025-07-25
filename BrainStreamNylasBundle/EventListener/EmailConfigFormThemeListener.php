<?php

/**
 * Used to hide mailboxes list and add mail box button in https://oronylasext.local/config/system/platform/email_configuration
 *
 */
namespace BrainStream\Bundle\NylasBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ViewEvent;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Form\FormRenderer;

class EmailConfigFormThemeListener
{
    private $twig;

    public function __construct(TwigEnvironment $twig)
    {
        $this->twig = $twig;
    }

    public function onKernelView(ViewEvent $event)
    {
        $request = $event->getRequest();
        if ($request->attributes->get('_route') === 'oro_config_configuration_system') {
            $result = $event->getControllerResult();

            if (is_array($result) && isset($result['form'])) {
                $form = $result['form'];

                // Check if we have a Form object or FormView
                if (method_exists($form, 'createView')) {
                    // If it's a Form object, create the view
                    $formView = $form->createView();
                } else {
                    // If it's already a FormView, use it directly
                    $formView = $form;
                }

                // Set the theme on the parent form
                $this->twig
                    ->getRuntime(FormRenderer::class)
                    ->setTheme(
                        $formView,
                        ['@BrainStreamNylas/Form/fields.html.twig']
                    );

                // Set the theme on the mailbox grid child
                if (isset($formView->children['oro_email___mailbox_grid'])) {
                    $this->twig
                        ->getRuntime(FormRenderer::class)
                        ->setTheme(
                            $formView->children['oro_email___mailbox_grid'],
                            ['@BrainStreamNylas/Form/fields.html.twig']
                        );
                }
            }
        }
    }
}
