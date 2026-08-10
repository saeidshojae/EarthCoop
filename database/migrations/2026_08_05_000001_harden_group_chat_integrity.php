<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('poll_votes')->orderBy('id')->get()->groupBy(fn ($vote) => $vote->poll_id . ':' . $vote->user_id)
            ->each(function ($votes): void {
                DB::table('poll_votes')->whereIn('id', $votes->pluck('id')->slice(1)->all())->delete();
            });
        DB::table('reactions')->whereNotNull('blog_id')->orderBy('id')->get()->groupBy(fn ($reaction) => $reaction->user_id . ':' . $reaction->blog_id)
            ->each(function ($reactions): void {
                DB::table('reactions')->whereIn('id', $reactions->pluck('id')->slice(1)->all())->delete();
            });
        DB::table('reactions')->whereNotNull('comment_id')->orderBy('id')->get()->groupBy(fn ($reaction) => $reaction->user_id . ':' . $reaction->comment_id)
            ->each(function ($reactions): void {
                DB::table('reactions')->whereIn('id', $reactions->pluck('id')->slice(1)->all())->delete();
            });

        Schema::table('poll_votes', function (Blueprint $table) {
            $table->unique(['poll_id', 'user_id'], 'poll_votes_poll_user_unique');
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->unique(['user_id', 'blog_id'], 'reactions_user_blog_unique');
            $table->unique(['user_id', 'comment_id'], 'reactions_user_comment_unique');
        });
    }

    public function down(): void
    {
        Schema::table('poll_votes', function (Blueprint $table) {
            $table->dropUnique('poll_votes_poll_user_unique');
        });
        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_user_blog_unique');
            $table->dropUnique('reactions_user_comment_unique');
        });
    }
};
