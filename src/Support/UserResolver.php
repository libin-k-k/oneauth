<?php

namespace Libinkk\OneAuth\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserResolver
{
    public static function queryByIdentifiers(string $identifier): ?Model
    {
        $modelClass = oneauth_user_model();
        /** @var Builder $query */
        $query = $modelClass::query();

        foreach ((array) config('oneauth.identifier_fields', ['email']) as $field) {
            $query->orWhere($field, $identifier);
        }

        return $query->first();
    }
}
