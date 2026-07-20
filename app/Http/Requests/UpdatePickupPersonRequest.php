<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PickupPerson;
use App\Models\User;

class UpdatePickupPersonRequest extends StorePickupPersonRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        $pickupPerson =
            $this->route('pickupPerson');

        return parent::authorize()
            && $user instanceof User
            && $pickupPerson instanceof PickupPerson
            && (int) $pickupPerson->school_id
                === (int) $user->school_id;
    }

    protected function pickupPersonId(): ?int
    {
        $pickupPerson =
            $this->route('pickupPerson');

        return $pickupPerson instanceof PickupPerson
            ? (int) $pickupPerson->id
            : null;
    }
}