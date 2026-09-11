<?php

namespace Tests\Unit\Membership;

use App\Contracts\Membership\DimensionResolver;
use App\Services\Membership\AgeDimensionResolver;
use App\Services\Membership\GenderDimensionResolver;
use App\Services\Membership\ProfessionDimensionResolver;
use App\Services\Membership\PublicDimensionResolver;
use App\Services\Membership\SpecialtyDimensionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DimensionResolverTest extends TestCase
{
    public static function dimensionResolvers(): array
    {
        return [
            'public' => ['public', PublicDimensionResolver::class],
            'profession' => ['profession', ProfessionDimensionResolver::class],
            'specialty' => ['specialty', SpecialtyDimensionResolver::class],
            'age' => ['age', AgeDimensionResolver::class],
            'gender' => ['gender', GenderDimensionResolver::class],
        ];
    }

    #[Test]
    public function dimension_resolver_contract_exists_with_the_minimal_stable_interface(): void
    {
        $this->assertTrue(interface_exists(DimensionResolver::class), 'DimensionResolver interface must exist.');

        $reflection = new \ReflectionClass(DimensionResolver::class);
        $this->assertSame(['dimensionKey', 'valuesFor'], array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        ));
    }

    #[Test]
    #[DataProvider('dimensionResolvers')]
    public function exactly_the_five_initial_dimensions_have_dedicated_resolvers(string $key, string $resolverClass): void
    {
        $this->assertTrue(class_exists($resolverClass), "Missing resolver for {$key}.");

        $resolver = app($resolverClass);
        $this->assertInstanceOf(DimensionResolver::class, $resolver);
        $this->assertSame($key, $resolver->dimensionKey());
    }

    #[Test]
    #[DataProvider('dimensionResolvers')]
    public function dimension_resolvers_do_not_embed_geographic_scope_logic(string $key, string $resolverClass): void
    {
        $path = (new \ReflectionClass($resolverClass))->getFileName();
        $this->assertNotFalse($path);
        $source = file_get_contents($path);

        foreach ([
            'Location::',
            'GovernanceArea::',
            'Address::',
            'locationRelationships(',
            'country_id',
            'province_id',
            'county_id',
            'section_id',
            'city_id',
            'village_id',
            'neighborhood_id',
            'street_id',
            'alley_id',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "{$key} resolver must resolve only its membership dimension, not geography.",
            );
        }
    }
}
