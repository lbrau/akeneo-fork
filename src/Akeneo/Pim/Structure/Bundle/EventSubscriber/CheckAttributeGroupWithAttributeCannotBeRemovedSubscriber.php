<?php

declare(strict_types=1);

/**
 * @copyright 2023 Akeneo SAS (https://www.akeneo.com)
 * @license https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Akeneo\Pim\Structure\Bundle\EventSubscriber;

use Akeneo\Pim\Structure\Component\Exception\AttributeGroupOtherCannotBeRemoved;
use Akeneo\Pim\Structure\Component\Exception\AttributeGroupWithAttributeCannotBeRemoved;
use Akeneo\Pim\Structure\Component\Model\AttributeGroupInterface;
use Akeneo\Tool\Component\StorageUtils\Event\RemoveEvent;
use Akeneo\Tool\Component\StorageUtils\StorageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckAttributeGroupWithAttributeCannotBeRemovedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            StorageEvents::PRE_REMOVE => 'preRemove',
        ];
    }

    public function preRemove(RemoveEvent $event): void
    {
        $attributeGroup = $event->getSubject();
        if (!$attributeGroup instanceof AttributeGroupInterface) {
            return;
        }

        if (0 < $attributeGroup->getAttributes()->count()) {
            throw AttributeGroupWithAttributeCannotBeRemoved::createFromAttributeGroup($attributeGroup);
        }
    }
}
