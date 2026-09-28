<?php

declare(strict_types=1);

namespace Tests\Unit\Actors;

use App\Models\Group;
use App\Models\User;
use App\Services\Actors\ActorBoundaryException;
use App\Services\Actors\ActorReference;
use App\Services\Actors\ActorResolver;
use App\Services\Actors\ActorType;
use PHPUnit\Framework\TestCase;

final class ActorReferenceTest extends TestCase
{
    public function test_it_serializes_stable_actor_identity_without_persistence_class_names(): void
    {
        $actor = new ActorReference(ActorType::User, '123');

        $this->assertSame('user:123', $actor->key());
        $this->assertSame(['type' => 'user', 'id' => '123'], $actor->toArray());
        $this->assertStringNotContainsString('App\\Models', json_encode($actor->toArray(), JSON_THROW_ON_ERROR));

        $group = ActorReference::parse('group:01J8Y6M4-actor_1.2');
        $this->assertSame(ActorType::Group, $group->type());
        $this->assertSame('01J8Y6M4-actor_1.2', $group->id());
    }

    public function test_from_array_accepts_only_stable_type_and_string_id_and_ignores_projection_metadata(): void
    {
        $actor = ActorReference::fromArray([
            'type' => 'group',
            'id' => '42',
            'display_name' => 'Non-authoritative projection',
            'permissions' => ['can_represent' => true],
        ]);

        $this->assertSame(['type' => 'group', 'id' => '42'], $actor->toArray());

        foreach ([
            ['type' => 'user', 'id' => 42],
            ['type' => 'user', 'id' => ['42']],
            ['type' => ['user'], 'id' => '42'],
            ['type' => 'company', 'id' => '42'],
        ] as $invalid) {
            try {
                ActorReference::fromArray($invalid);
                $this->fail('Invalid actor input must fail closed.');
            } catch (ActorBoundaryException $exception) {
                $this->assertSame('actor_reference_invalid', $exception->errorCode());
                $this->assertSame(422, $exception->httpStatus());
            }
        }
    }

    public function test_hostile_or_ambiguous_actor_keys_fail_closed(): void
    {
        foreach ([
            '',
            'user:',
            ':123',
            'user:1:2',
            'user:App\\Models\\User',
            'user:a/b',
            'user:a\\b',
            'user:with space',
            'company:123',
        ] as $invalid) {
            try {
                ActorReference::parse($invalid);
                $this->fail('Invalid actor key must fail closed: '.$invalid);
            } catch (ActorBoundaryException $exception) {
                $this->assertSame('actor_reference_invalid', $exception->errorCode());
                $this->assertSame(422, $exception->httpStatus());
            }
        }
    }

    public function test_reserved_organization_is_a_valid_reference_but_resolver_rejects_it_until_provider_exists(): void
    {
        $organization = ActorReference::parse('organization:org-1');
        $this->assertSame(ActorType::Organization, $organization->type());

        try {
            (new ActorResolver())->resolveModel($organization);
            $this->fail('Organization must fail closed until a real source provider exists.');
        } catch (ActorBoundaryException $exception) {
            $this->assertSame('actor_not_supported', $exception->errorCode());
            $this->assertSame(422, $exception->httpStatus());
        }
    }

    public function test_resolver_maps_user_group_legacy_owners_and_trusted_system_reference(): void
    {
        $resolver = new ActorResolver();

        $user = new User();
        $user->forceFill(['id' => 17, 'is_system' => false]);
        $group = new Group();
        $group->forceFill(['id' => 29]);

        $this->assertSame('user:17', $resolver->referenceFor($user)->key());
        $this->assertSame('group:29', $resolver->referenceFor($group)->key());
        $this->assertSame('user:17', $resolver->fromLegacyOwner(User::class, 17)->key());
        $this->assertSame('group:29', $resolver->fromLegacyOwner(Group::class, 29)->key());
        $this->assertSame('system:earthcoop', $resolver->systemReference()->key());

        try {
            $resolver->fromLegacyOwner('App\\Models\\UnknownOwner', 1);
            $this->fail('Unknown legacy polymorphic owner must fail closed.');
        } catch (ActorBoundaryException $exception) {
            $this->assertSame('actor_not_supported', $exception->errorCode());
        }
    }

    public function test_system_user_rows_are_not_public_user_actors(): void
    {
        $systemUser = new User();
        $systemUser->forceFill(['id' => 99, 'is_system' => true]);

        try {
            (new ActorResolver())->referenceFor($systemUser);
            $this->fail('System user rows must not become public user actors.');
        } catch (ActorBoundaryException $exception) {
            $this->assertSame('actor_not_supported', $exception->errorCode());
        }
    }
}
