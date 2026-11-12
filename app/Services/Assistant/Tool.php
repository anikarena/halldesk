<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * Something the assistant can do for the signed in user, like reading their
 * bills or turning meals off. The model picks a tool and fills in the
 * arguments; the tool does the work with the app's own services, so the same
 * rules apply as on the normal pages.
 *
 * Tools never take a user id: they always act for the person who is signed in.
 */
abstract class Tool
{
    /** The name the model calls, in snake_case. */
    abstract public function name(): string;

    /** What the tool does and when to use it, written for the model. */
    abstract public function description(): string;

    /**
     * Do the work and return facts for the model. An optional "_links" entry
     * (list of label + url) is shown to the user under the answer.
     * Throw HallException or ValidationException to report a broken rule.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    abstract public function run(User $user, array $args): array;

    /**
     * JSON schema of the arguments, null when there are none.
     *
     * @return array<string, mixed>|null
     */
    public function parameters(): ?array
    {
        return null;
    }

    public function allows(User $user): bool
    {
        return true;
    }

    /**
     * True for tools that change data: the user confirms on screen first.
     */
    public function changesData(): bool
    {
        return false;
    }

    /**
     * One line for the confirm card, such as "Turn dinner OFF on Tue, 10 Nov".
     * Checks the arguments first, so an impossible change never reaches the card.
     *
     * @param  array<string, mixed>  $args
     */
    public function describe(User $user, array $args): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function declaration(): array
    {
        $declaration = ['name' => $this->name(), 'description' => $this->description()];
        if ($parameters = $this->parameters()) {
            $declaration['parameters'] = $parameters;
        }

        return $declaration;
    }
}
