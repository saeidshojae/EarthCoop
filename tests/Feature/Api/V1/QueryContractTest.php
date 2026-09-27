<?php

namespace Tests\Feature\Api\V1;

use App\Http\Support\Api\V1\Pagination;
use App\Http\Support\Api\V1\QueryOptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QueryContractTest extends TestCase
{
    public function test_page_options_apply_defaults_and_bound_requested_size(): void
    {
        $default = Pagination::page(Request::create('/api/v1/items', 'GET'));
        $this->assertSame(1, $default->number);
        $this->assertSame(25, $default->size);

        $bounded = Pagination::page(Request::create('/api/v1/items', 'GET', [
            'page' => ['number' => '3', 'size' => '999'],
        ]), default: 25, max: 100);

        $this->assertSame(3, $bounded->number);
        $this->assertSame(100, $bounded->size);
    }

    public function test_page_options_reject_invalid_integers_with_validation_failed_contract(): void
    {
        try {
            Pagination::page(Request::create('/api/v1/items', 'GET', [
                'page' => ['number' => 'abc', 'size' => '0'],
            ]));
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('page.number', $exception->errors());
        }
    }

    public function test_cursor_options_keep_opaque_cursor_and_bound_limit(): void
    {
        $options = Pagination::cursor(Request::create('/api/v1/items', 'GET', [
            'page' => ['cursor' => 'opaque.cursor-123', 'limit' => '500'],
        ]), default: 50, max: 100);

        $this->assertSame('opaque.cursor-123', $options->cursor);
        $this->assertSame(100, $options->limit);
    }

    public function test_cursor_options_reject_non_scalar_cursor_and_invalid_limit(): void
    {
        foreach ([
            ['page' => ['cursor' => ['nested'], 'limit' => '50']],
            ['page' => ['cursor' => 'opaque', 'limit' => '-1']],
        ] as $query) {
            try {
                Pagination::cursor(Request::create('/api/v1/items', 'GET', $query));
                $this->fail('Expected ValidationException was not thrown.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_filters_require_explicit_external_to_query_field_mapping(): void
    {
        $filters = QueryOptions::filters(
            Request::create('/api/v1/items', 'GET', ['filter' => ['status' => 'active', 'group' => '12']]),
            ['status' => 'status_code', 'group' => 'group_id'],
        );

        $this->assertSame([
            'status_code' => 'active',
            'group_id' => '12',
        ], $filters);
    }

    public function test_unsupported_filter_is_rejected_with_stable_validation_error(): void
    {
        try {
            QueryOptions::filters(
                Request::create('/api/v1/items', 'GET', ['filter' => ['raw_database_column' => 'x']]),
                ['status' => 'status_code'],
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('filter.raw_database_column', $exception->errors());
        }
    }

    public function test_sort_parses_descending_and_maps_only_allowed_public_names(): void
    {
        $sort = QueryOptions::sort(
            Request::create('/api/v1/items', 'GET', ['sort' => '-created_at,name']),
            ['created_at' => 'created_at', 'name' => 'display_name'],
        );

        $this->assertSame([
            ['field' => 'created_at', 'direction' => 'desc'],
            ['field' => 'display_name', 'direction' => 'asc'],
        ], $sort);
    }

    public function test_sort_uses_mapped_defaults_and_rejects_unknown_fields(): void
    {
        $default = QueryOptions::sort(
            Request::create('/api/v1/items', 'GET'),
            ['created_at' => 'created_at'],
            ['-created_at'],
        );

        $this->assertSame([
            ['field' => 'created_at', 'direction' => 'desc'],
        ], $default);

        try {
            QueryOptions::sort(
                Request::create('/api/v1/items', 'GET', ['sort' => 'password']),
                ['created_at' => 'created_at'],
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sort', $exception->errors());
        }
    }
}
