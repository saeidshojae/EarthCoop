<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'variables',
        'category',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'variables' => 'array',
    ];

    /**
     * Replace variables in template with actual values
     */
    public function render(array $data = []): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Get available variables from template
     */
    public function getAvailableVariables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->subject . ' ' . $this->body, $matches);
        return array_unique($matches[1] ?? []);
    }
}

