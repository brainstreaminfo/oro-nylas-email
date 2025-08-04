<?php

/**
 * Custom Internal Email Origin Entity.
 *
 * This file is part of the BrainStream Nylas Bundle.
 *
 * @category BrainStream
 * @package  BrainStream\Bundle\NylasBundle\Entity
 * @author   BrainStream Team
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/brainstreaminfo/oro-nylas-email
 */

namespace BrainStream\Bundle\NylasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin as BaseInternalEmailOrigin;

/**
 * Custom Internal Email Origin Entity.
 *
 * Extends the base internal email origin for Nylas integration.
 *
 * @package BrainStream\Bundle\NylasBundle\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: "oro_email_origin")]
class CustomInternalEmailOrigin extends BaseInternalEmailOrigin
{
}
