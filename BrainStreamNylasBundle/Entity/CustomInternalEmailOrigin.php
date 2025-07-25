<?php
// src/Acme/Bundle/EmailBundle/Entity/EmailOriginExtension.php

namespace BrainStream\Bundle\NylasBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oro\Bundle\EmailBundle\Entity\InternalEmailOrigin as BaseInternalEmailOrigin;

#[ORM\Entity]
#[ORM\Table(name: "oro_email_origin")]
class CustomInternalEmailOrigin extends BaseInternalEmailOrigin {}
