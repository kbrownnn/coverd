<?php

namespace App\Security;

use App\Entity\Client;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClientVoter extends Voter
{
    public const EDIT = 'EDIT';
    public const VIEW = 'VIEW';
    public const TRANSFER = 'TRANSFER';

    protected function supports($attribute, $subject)
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::TRANSFER])) {
            return false;
        }

        if (!$subject instanceof Client) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $client = $subject;

        switch ($attribute) {
            case self::VIEW:
                return $this->canView($client, $user);
            case self::EDIT:
                return $this->canEdit($client, $user);
            case self::TRANSFER:
                return $this->canTransfer($client, $user);
            default:
                throw new \LogicException('Unknown Voter attribute');
        }
    }

    private function canView(Client $client, User $user): bool
    {
        if ($this->canEdit($client, $user)) {
            return true;
        }

        if ($user->hasRole(Client::ROLE_VIEW_ALL)) {
            return true;
        }

        return false;
    }

    private function canEdit(Client $client, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasRole(Client::ROLE_EDIT_ALL)) {
            return true;
        }

        $activePartner = $user->getActivePartner();

        if (
            $user->hasRole(Client::ROLE_MANAGE_OWN)
            && $activePartner
            && $client->getPartner()->getId() === $activePartner->getId()
        ) {
            return true;
        }

        return false;
    }

    private function canTransfer(Client $client, User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->hasRole(Client::ROLE_EDIT_ALL)) {
            return true;
        }

        $activePartner = $user->getActivePartner();

        if (
            $user->hasRole(Client::ROLE_MANAGE_OWN)
            && $activePartner
            && $client->canPartnerTransfer()
        ) {
            return true;
        }

        return false;
    }
}
