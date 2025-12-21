<?php

use App\Models\AiChatThread;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Ai\ChatService;
use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$thread = AiChatThread::query()->latest('id')->first();

if ($thread === null) {
    fwrite(STDERR, "No AI chat threads available.\n");
    exit(1);
}

$user = User::query()->where('company_id', $thread->company_id)->first();

if ($user === null) {
    fwrite(STDERR, "No user found for company {$thread->company_id}.\n");
    exit(1);
}

/** @var ChatService $chatService */
$chatService = $app->make(ChatService::class);
$message = 'Terminal debug ' . now();

$tokenData = $chatService->prepareStream($thread, $user, $message);
$cacheKey = 'ai_chat:stream:' . $tokenData['stream_token'];
$session = cache()->get($cacheKey);

if (! is_array($session)) {
    fwrite(STDERR, "Stream session payload missing from cache.\n");
    exit(1);
}

$payload = $session['payload'] ?? null;

if (! is_array($payload)) {
    fwrite(STDERR, "Stream payload is invalid.\n");
    exit(1);
}

echo "=== Payload ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

/** @var AiClient $client */
$client = $app->make(AiClient::class);

try {
    $client->chatRespondStream($payload, function (array $event): void {
        echo 'EVENT: ' . json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    });
} catch (AiServiceUnavailableException $exception) {
    echo 'Caught AiServiceUnavailableException: ' . $exception->getMessage() . PHP_EOL;
}
