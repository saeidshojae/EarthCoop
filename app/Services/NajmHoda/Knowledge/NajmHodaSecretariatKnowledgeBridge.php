<?php

namespace App\Services\NajmHoda\Knowledge;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatKnowledgeRetrievalService;

class NajmHodaSecretariatKnowledgeBridge
{
    private const ALLOWED_FILTERS = [
        'office_id',
        'record_type',
        'confidentiality',
        'date_from',
        'date_to',
        'party',
        'party_user_id',
        'party_group_id',
        'source_type',
        'source_id',
        'case_id',
    ];

    public function __construct(private readonly SecretariatKnowledgeRetrievalService $retrieval)
    {
    }

    /**
     * Read-side bridge for Najm Hoda answer/context flows.
     *
     * The authenticated actor is an object supplied by the trusted application
     * boundary; actor_id/user_id values in model-generated or client-provided
     * context are never used to elevate retrieval authority.
     */
    public function retrieve(User $actor, string $query, array $context = []): array
    {
        $filters = [];
        foreach (self::ALLOWED_FILTERS as $key) {
            if (array_key_exists($key, $context)) {
                $filters[$key] = $context[$key];
            }
        }

        $limit = isset($context['limit']) ? (int) $context['limit'] : 8;
        $packets = $this->retrieval->retrieve($actor, $query, $filters, $limit);

        return [
            'source' => 'secretariat',
            'actor_id' => (int) $actor->id,
            'query_fingerprint' => hash('sha256', trim($query)),
            'count' => $packets->count(),
            'packets' => $packets->all(),
        ];
    }
}
