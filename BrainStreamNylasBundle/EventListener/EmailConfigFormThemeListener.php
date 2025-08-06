<?php

/**
 * Email Configuration Form Theme Listener.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * Used to hide mailboxes list and add mail box button in email configuration.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ViewEvent;
use Twig\Environment as TwigEnvironment;
use Symfony\Component\Form\FormRenderer;

/**
 * Email Configuration Form Theme Listener.
 *
 * Listener for customizing email configuration form themes.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\EventListener
 * @author   BrainStream Team <info@brainstream.tech>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */
class EmailConfigFormThemeListener
{
    private TwigEnvironment $twig;

    /**
     * Constructor.
     *
     * @param TwigEnvironment $twig The Twig environment
     */
    public function __construct(TwigEnvironment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Handle kernel view event.
     *
     * @param ViewEvent $event The view event
     *
     * @return void
     */
    public function onKernelView(ViewEvent $event): void
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
