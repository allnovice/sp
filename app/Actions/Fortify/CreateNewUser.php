<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Models\PropertyColumn;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

$user = User::create([
    'name' => $input['name'],
    'email' => $input['email'],
    'password' => $input['password'],
]);

$user->propertyColumns()->createMany([
    [
        'key' => 'property_no',
        'label' => 'Property No.',
        'type' => 'text',
        'is_visible' => true,
        'is_required' => true,
        'position' => 0,
    ],
    [
        'key' => 'created_at',
        'label' => 'Date Added',
        'type' => 'datetime',
        'is_visible' => false,
        'is_required' => false,
        'position' => 1,
    ],
    [
        'key' => 'updated_at',
        'label' => 'Last Updated',
        'type' => 'datetime',
        'is_visible' => false,
        'is_required' => false,
        'position' => 2,
    ],
]);

return $user;
    }
}
