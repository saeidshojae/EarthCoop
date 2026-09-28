<?php

declare(strict_types=1);

namespace App\Services\Actors;

final class ActorReference
{
    private const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,190}$/D';

    public function __construct(
        private readonly ActorType $type,
        private readonly string $id,
    ) {
        if ($id === '' || $id !== trim($id) || ! preg_match(self::ID_PATTERN, $id)) {
            throw ActorBoundaryException::invalidReference();
        }
    }

    public static function fromArray(array $value): self
    {
        $type = $value['type'] ?? null;
        $id = $value['id'] ?? null;

        if (! is_string($type) || ! is_string($id)) {
            throw ActorBoundaryException::invalidReference();
        }

        $actorType = ActorType::tryFrom($type);
        if (! $actorType) {
            throw ActorBoundaryException::invalidReference();
        }

        return new self($actorType, $id);
    }

    public static function parse(string $value): self
    {
        if (substr_count($value, ':') !== 1) {
            throw ActorBoundaryException::invalidReference();
        }

        [$type, $id] = explode(':', $value, 2);

        return self::fromArray(['type' => $type, 'id' => $id]);
    }

    public function type(): ActorType
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->type->value.':'.$this->id;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->id,
        ];
    }
}
