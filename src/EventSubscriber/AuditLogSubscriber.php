<?php

namespace App\EventSubscriber;

use App\Entity\Log;
use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AuditLogSubscriber implements EventSubscriber
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // 1. Process Insertions
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Log || $entity instanceof Notification) {
                continue;
            }

            $afterData = $this->serializeEntity($entity, $em);
            $details = sprintf('Created %s: %s', $this->getEntityDisplayName($entity), $this->getEntityLabel($entity));

            $log = new Log();
            $log->setAction('CREATE');
            $log->setUser($this->getCurrentUser());
            $log->setDetails($details);
            $log->setEntityClass(get_class($entity));
            $log->setEntityId($this->getEntityId($entity, $em));
            $log->setBeforeData(null);
            $log->setAfterData($afterData);

            $em->persist($log);
            $uow->computeChangeSet($em->getClassMetadata(Log::class), $log);
        }

        // 2. Process Updates
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Log || $entity instanceof Notification) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            
            // Filter out unchanged properties or internal fields if needed
            $beforeData = [];
            $afterData = [];
            $hasChanges = false;

            foreach ($changeSet as $field => $values) {
                $oldValue = $values[0];
                $newValue = $values[1];

                // Skip if values are identical
                if ($oldValue === $newValue) {
                    continue;
                }

                // Mask sensitive fields
                if ($this->isSensitiveField($field)) {
                    $oldValue = '********';
                    $newValue = '********';
                }

                $beforeData[$field] = $this->normalizeValue($oldValue);
                $afterData[$field] = $this->normalizeValue($newValue);
                $hasChanges = true;
            }

            if ($hasChanges) {
                $details = sprintf('Updated %s: %s', $this->getEntityDisplayName($entity), $this->getEntityLabel($entity));
                $log = new Log();
                $log->setAction('UPDATE');
                $log->setUser($this->getCurrentUser());
                $log->setDetails($details);
                $log->setEntityClass(get_class($entity));
                $log->setEntityId($this->getEntityId($entity, $em));
                $log->setBeforeData($beforeData);
                $log->setAfterData($afterData);

                $em->persist($log);
                $uow->computeChangeSet($em->getClassMetadata(Log::class), $log);
            }
        }

        // 3. Process Deletions
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Log || $entity instanceof Notification) {
                continue;
            }

            $beforeData = $this->serializeEntity($entity, $em);
            $details = sprintf('Deleted %s: %s', $this->getEntityDisplayName($entity), $this->getEntityLabel($entity));

            $log = new Log();
            $log->setAction('DELETE');
            $log->setUser($this->getCurrentUser());
            $log->setDetails($details);
            $log->setEntityClass(get_class($entity));
            $log->setEntityId($this->getEntityId($entity, $em));
            $log->setBeforeData($beforeData);
            $log->setAfterData(null);

            $em->persist($log);
            $uow->computeChangeSet($em->getClassMetadata(Log::class), $log);
        }
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    private function serializeEntity(object $entity, EntityManagerInterface $em): array
    {
        $metadata = $em->getClassMetadata(get_class($entity));
        $data = [];

        // Serialize fields
        foreach ($metadata->getFieldNames() as $fieldName) {
            if ($this->isSensitiveField($fieldName)) {
                $data[$fieldName] = '********';
                continue;
            }

            $value = $metadata->getFieldValue($entity, $fieldName);
            $data[$fieldName] = $this->normalizeValue($value);
        }

        // Serialize single associations (references)
        foreach ($metadata->getAssociationNames() as $assocName) {
            if ($metadata->isSingleValuedAssociation($assocName)) {
                $assocValue = $metadata->getFieldValue($entity, $assocName);
                if ($assocValue !== null) {
                    $assocMeta = $em->getClassMetadata(get_class($assocValue));
                    $identifier = $assocMeta->getIdentifierValues($assocValue);
                    
                    $label = $this->getEntityLabel($assocValue);
                    $data[$assocName] = [
                        'id' => implode(',', $identifier),
                        'class' => get_class($assocValue),
                        'label' => $label,
                    ];
                } else {
                    $data[$assocName] = null;
                }
            }
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return $this->getEntityLabel($value);
        }

        return $value;
    }

    private function getEntityId(object $entity, EntityManagerInterface $em): ?string
    {
        $metadata = $em->getClassMetadata(get_class($entity));
        $identifier = $metadata->getIdentifierValues($entity);
        return $identifier ? (string) implode(',', $identifier) : null;
    }

    private function getEntityDisplayName(object $entity): string
    {
        $parts = explode('\\', get_class($entity));
        return end($parts);
    }

    private function getEntityLabel(object $entity): string
    {
        if ($entity instanceof User) {
            return $entity->getUsername();
        }

        if (method_exists($entity, 'getName')) {
            return (string) $entity->getName();
        }

        if (method_exists($entity, 'getTitle')) {
            return (string) $entity->getTitle();
        }

        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        $reflect = new \ReflectionClass($entity);
        return $reflect->getShortName();
    }

    private function isSensitiveField(string $fieldName): bool
    {
        $sensitive = ['password', 'plainPassword', 'salt', 'token', 'apiToken', 'secret'];
        return in_array(strtolower($fieldName), $sensitive, true);
    }
}
