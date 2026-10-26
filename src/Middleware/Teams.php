<?php

namespace Jurager\Teams\Middleware;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

abstract class Teams
{
    /** Rejects the request using the configured handling: abort or redirect. */
    protected function unauthorized(): RedirectResponse
    {
        $handling = Config::get('teams.middleware.handling', 'abort');
        $handler = Config::get("teams.middleware.handlers.{$handling}");

        if ($handling === 'abort' || ! is_array($handler)) {
            abort(
                $handler['code'] ?? 403,
                $handler['message'] ?? 'User does not have any of the necessary access rights.',
            );
        }

        $redirect = redirect()->to($handler['url']);
        $message = $handler['message'] ?? [];

        if (! empty($message['key']) && ! empty($message['content'])) {
            $redirect->with($message['key'], $message['content']);
        }

        return $redirect;
    }

    /** Finds a team based on a route parameter, a request field, or a provided identifier. */
    protected function resolveTeam(Request $request, ?string $teamId): Model
    {
        $value = match (true) {
            $teamId === null => $this->fromRequest($request, $this->teamForeignKey()),
            is_numeric($teamId), Str::isUuid($teamId) => $teamId,
            default => $this->fromRequest($request, $teamId),
        };

        $teamClass = TeamsFacade::model('team');

        if (! $value instanceof $teamClass && ! is_int($value) && ! is_string($value)) {
            abort(404);
        }

        return $value instanceof $teamClass
            ? $value
            : $teamClass::query()->findOrFail($value);
    }

    /** Finds an entity to check the ability: [Model] or [ModelClass, parameter|Model]. */
    protected function resolveEntity(Request $request, array $models): ?Model
    {
        [$class, $parameter] = array_pad($models, 2, null);

        if ($class instanceof Model) {
            return $class;
        }

        if (! is_string($class) || ! is_subclass_of($class, Model::class) || $parameter === null) {
            return null;
        }

        $value = $parameter instanceof Model
            ? $parameter
            : $this->fromRequest($request, $parameter);

        if (! $value instanceof $class && ! is_int($value) && ! is_string($value)) {
            return null;
        }

        $entity = $value instanceof $class ? $value : $class::query()->find($value);

        return $entity instanceof Model ? $entity : null;
    }

    /** An entity without a team field is considered to belong to any team. */
    protected function belongsToTeam(Model $entity, Model $team): bool
    {
        $teamKey = $this->teamForeignKey();

        return ! array_key_exists($teamKey, $entity->getAttributes()) || (string) $entity->getAttribute($teamKey) === (string) $team->getKey();
    }

    /** Splits a "a|b" middleware argument into a list. */
    protected function parse(string|array $values): array
    {
        return is_array($values) ? $values : explode('|', $values);
    }

    protected function isRequired(bool|string $require): bool
    {
        return in_array($require, [true, 'require', 'true', '1'], true);
    }

    protected function fromRequest(Request $request, string $key): mixed
    {
        return $request->route($key) ?? $request->input($key);
    }

    protected function teamForeignKey(): string
    {
        return Config::get('teams.foreign_keys.team_id', 'team_id');
    }
}
