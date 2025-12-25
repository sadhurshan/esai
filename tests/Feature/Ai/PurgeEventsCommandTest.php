<?php

use App\Models\AiChatMessage;
use App\Models\AiChatThread;
use App\Models\AiEvent;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('purges AI events and chat messages older than retention', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-12-24 12:00:00'));

    config()->set('ai.retention', [
        'mode' => 'delete',
        'events_days' => 90,
        'chat_messages_days' => 90,
        'archive_disk' => 'local',
        'archive_directory' => 'ai/archives',
    ]);

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Retention thread',
    ]);

    $oldEvent = AiEvent::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'test-feature',
        'entity_type' => null,
        'entity_id' => null,
        'request_json' => ['foo' => 'bar'],
        'response_json' => ['status' => 'ok'],
        'latency_ms' => 500,
        'status' => AiEvent::STATUS_SUCCESS,
    ]);
    AiEvent::query()->whereKey($oldEvent->id)->update([
        'created_at' => Carbon::now()->subDays(120),
        'updated_at' => Carbon::now()->subDays(120),
    ]);

    $recentEvent = AiEvent::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'recent-feature',
        'entity_type' => null,
        'entity_id' => null,
        'request_json' => ['foo' => 'baz'],
        'response_json' => ['status' => 'ok'],
        'latency_ms' => 200,
        'status' => AiEvent::STATUS_SUCCESS,
    ]);

    $oldMessage = AiChatMessage::query()->create([
        'thread_id' => $thread->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => AiChatMessage::ROLE_USER,
        'content_text' => 'Old message',
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);
    AiChatMessage::query()->whereKey($oldMessage->id)->update([
        'created_at' => Carbon::now()->subDays(120),
        'updated_at' => Carbon::now()->subDays(120),
    ]);

    $recentMessage = AiChatMessage::query()->create([
        'thread_id' => $thread->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => AiChatMessage::ROLE_ASSISTANT,
        'content_text' => 'Fresh message',
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);

    Artisan::call('ai:purge-events');

    expect(AiEvent::query()->whereKey($oldEvent->id)->exists())->toBeFalse()
        ->and(AiEvent::query()->whereKey($recentEvent->id)->exists())->toBeTrue()
        ->and(AiChatMessage::query()->whereKey($oldMessage->id)->exists())->toBeFalse()
        ->and(AiChatMessage::query()->whereKey($recentMessage->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('archives eligible rows when retention mode is archive', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-12-24 12:00:00'));

    Storage::fake('local');

    config()->set('ai.retention', [
        'mode' => 'archive',
        'events_days' => 90,
        'chat_messages_days' => 90,
        'archive_disk' => 'local',
        'archive_directory' => 'ai/archives',
    ]);

    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();

    $thread = AiChatThread::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'title' => 'Archive thread',
    ]);

    $event = AiEvent::query()->create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'feature' => 'archive-feature',
        'entity_type' => null,
        'entity_id' => null,
        'request_json' => ['foo' => 'archive'],
        'response_json' => ['status' => 'ok'],
        'latency_ms' => 100,
        'status' => AiEvent::STATUS_SUCCESS,
    ]);
    AiEvent::query()->whereKey($event->id)->update([
        'created_at' => Carbon::now()->subDays(120),
        'updated_at' => Carbon::now()->subDays(120),
    ]);

    $message = AiChatMessage::query()->create([
        'thread_id' => $thread->id,
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => AiChatMessage::ROLE_ASSISTANT,
        'content_text' => 'Archive me',
        'status' => AiChatMessage::STATUS_COMPLETED,
    ]);
    AiChatMessage::query()->whereKey($message->id)->update([
        'created_at' => Carbon::now()->subDays(120),
        'updated_at' => Carbon::now()->subDays(120),
    ]);

    Artisan::call('ai:purge-events');

    expect(AiEvent::query()->count())->toBe(0)
        ->and(AiChatMessage::query()->count())->toBe(0);

    $files = Storage::disk('local')->allFiles('ai/archives');

    expect($files)->not->toBeEmpty();

    $firstArchive = Storage::disk('local')->get($files[0]);

    expect($firstArchive)->toContain('"archived_at"');

    Carbon::setTestNow();
});
