<?php

namespace Base\Drive\People;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The drive's people by default: every account of drive.user_class, by its identifier and its
 * e-mail, in one group ("accounts").
 */
final class AccountPeople implements PeopleInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%drive.user_class%')] private readonly string $userClass = 'App\Entity\User',
    ) {
    }

    public function grouped(): array
    {
        $accounts = [];
        foreach ($this->entityManager->getRepository($this->userClass)->findAll() as $user) {
            $email = method_exists($user, 'getEmail') ? $user->getEmail() : null;
            $accounts[$user->getId()] = $user->getUserIdentifier().($email ? ' · '.$email : '');
        }
        asort($accounts, \SORT_NATURAL | \SORT_FLAG_CASE);

        return array_filter(['accounts' => $accounts]);
    }

    public function names(): array
    {
        return array_replace([], ...array_values($this->grouped()));
    }
}
