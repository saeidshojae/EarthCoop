<?php

namespace App\Http\Support\Api\V1;

final class GroupTextProjection
{
    public static function fromHtml(string $html): string
    {
        // nl2br keeps its source newline; consume it with the inserted break.
        // A legacy break without a source newline still contributes one line break.
        $text = preg_replace('/<br\s*\/?\s*>(?:\r\n|\n|\r)?/i', "\n", $html);

        return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
