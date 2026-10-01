<?php

namespace App\Services;

use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessResponseType;
use App\Enums\TaskStatus;
use App\Models\ProcessExecution;
use App\Models\Task;
use App\Models\TelegramAccount;
use App\Models\TelegramEmployee;
use App\Models\User;

class TelegramUpdateService
{
    public function __construct(
        private TelegramService $telegramService,
        private TelegramAccountService $accountService,
        private ProcessExecutionService $executionService,
        private TaskService $taskService
    ) {}

    /**
     * Helper to process text message directly and return response structure.
     */
    public function handleIncomingText(string $chatId, ?string $telegramUserId, ?string $username, string $text): array
    {
        $update = [
            'update_id' => rand(10000, 99999),
            'message' => [
                'message_id' => rand(1000, 9999),
                'from' => [
                    'id' => (int) ($telegramUserId ?? $chatId),
                    'is_bot' => false,
                    'username' => $username,
                ],
                'chat' => [
                    'id' => (int) $chatId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
            ],
        ];

        $this->handleUpdate($update);

        return ['handled' => true, 'message' => 'Processed update'];
    }

    /**
     * Process an incoming update from the webhook.
     */
    public function handleUpdate(array $update): void
    {
        if (! isset($update['message']['text'])) {
            return; // We only care about text messages
        }

        $message = $update['message'];
        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text']);

        $telegramUserId = isset($message['from']['id']) ? (string) $message['from']['id'] : null;
        $telegramUsername = $message['from']['username'] ?? null;

        // Find linked Telegram account
        $account = TelegramAccount::where(function ($query) use ($chatId, $telegramUserId) {
            $query->where('chat_id', $chatId);
            if ($telegramUserId) {
                $query->orWhere('telegram_user_id', $telegramUserId);
            }
        })->where('is_active', true)->whereNotNull('verified_at')->first();

        if ($account) {
            $account->update(['last_seen_at' => now()]);
        }

        // 1. Command Routing
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($chatId, $text, $telegramUserId, $telegramUsername, $account);
        } elseif (str_starts_with($text, '/help')) {
            $this->handleHelpCommand($chatId, $account);
        } elseif (str_starts_with($text, '/status')) {
            $this->handleStatusCommand($chatId, $account);
        } elseif (str_starts_with($text, '/unlink')) {
            $this->handleUnlinkCommand($chatId, $account);
        } elseif (str_starts_with($text, '/tasks')) {
            $this->handleTasksCommand($chatId, $account);
        } elseif (str_starts_with($text, '/checklists') || str_starts_with($text, '/today')) {
            $this->handleChecklistsCommand($chatId, $account);
        } elseif (preg_match('/^(TG-[A-Z0-9]+|TGEMP-[A-Z0-9]+)$/i', $text)) {
            // Direct verification code entry
            $this->handleVerificationCode($chatId, $text, $telegramUserId, $telegramUsername, $account);
        } elseif (preg_match('/^DONE(\s+[A-Za-z0-9\-]+)?$/i', $text, $matches)) {
            // Task completion: "DONE" or "DONE TSK-0001"
            $taskCode = isset($matches[1]) ? trim($matches[1]) : null;
            $this->handleTaskCompletion($chatId, $taskCode, $account);
        } elseif (strtoupper($text) === 'COMPLETE') {
            // Process execution completion confirmation
            $this->handleChecklistCompletion($chatId, $account);
        } else {
            // Try handling as checklist question response or show unknown command
            $handled = $this->handleChecklistResponse($chatId, $text, $account);
            if (! $handled) {
                $this->telegramService->sendMessage($chatId, "❓ Unknown command or response.\nSend /help to see available commands or /checklists for open checklists.");
            }
        }
    }

    private function handleStartCommand(string $chatId, string $text, ?string $telegramUserId, ?string $telegramUsername, ?TelegramAccount $account): void
    {
        // Extract potential code from deep linking: /start TG-XXXXXX or /start TGEMP-XXXXXX or /start <payload>
        $parts = explode(' ', $text, 2);
        if (count($parts) === 2 && ! empty(trim($parts[1]))) {
            $this->handleVerificationCode($chatId, trim($parts[1]), $telegramUserId, $telegramUsername, $account);

            return;
        }

        $reply = "Welcome to the Task & Business Process Accountability Bot!\n\n";

        if ($account && $account->isVerified()) {
            $ownerName = $account->owner_name;
            $reply .= "Your account is linked as <b>{$ownerName}</b>.\n\n";
            $reply .= "Use /checklists to view today's process checklists.\n";
            $reply .= 'Use /tasks to view your assigned tasks.';
        } else {
            $reply .= "To connect your account, please enter the linking code provided by your administrator.\n";
            $reply .= 'Example: <code>TGEMP-8F4K7P</code> or <code>TG-8F4K7P</code>';
        }

        $this->telegramService->sendMessage($chatId, $reply);
    }

    private function handleHelpCommand(string $chatId, ?TelegramAccount $account): void
    {
        $reply = "<b>Available Commands:</b>\n";
        $reply .= "/start - Start the bot or link account\n";
        $reply .= "/checklists - View your active process checklists\n";
        $reply .= "/today - View today's checklist assignments\n";
        $reply .= "/tasks - View your assigned tasks\n";
        $reply .= "/status - Check your connection status\n";
        $reply .= "/unlink - Disconnect your Telegram account\n";
        $reply .= "/help - Show this help message\n\n";
        $reply .= "<b>Checklist Responses:</b>\n";
        $reply .= "Reply with <code>YES</code>, <code>NO</code>, <code>NA</code>, or the required value.\n";
        $reply .= "Reply <code>COMPLETE</code> when all items are done.\n\n";
        $reply .= "<b>Task Completion:</b>\n";
        $reply .= 'Reply <code>DONE [CODE]</code> (e.g. <code>DONE TSK-0001</code>) to complete a task.';

        $this->telegramService->sendMessage($chatId, $reply);
    }

    private function handleStatusCommand(string $chatId, ?TelegramAccount $account): void
    {
        if ($account && $account->isVerified()) {
            $owner = $account->owner();
            $orgName = $owner?->organization?->name ?? 'Organization';
            $typeLabel = ($owner instanceof TelegramEmployee) ? 'Telegram Employee' : 'Web User';
            $reply = "✅ <b>Connected</b>\n\n";
            $reply .= "<b>Name:</b> {$account->owner_name}\n";
            $reply .= "<b>Type:</b> {$typeLabel}\n";
            $reply .= "<b>Organization:</b> {$orgName}\n";
        } else {
            $reply = '❌ Your Telegram account is not connected. Send your linking code to connect.';
        }

        $this->telegramService->sendMessage($chatId, $reply);
    }

    private function handleUnlinkCommand(string $chatId, ?TelegramAccount $account): void
    {
        if (! $account || ! $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, 'Your account is not connected.');

            return;
        }

        if ($account->user) {
            $this->accountService->unlinkAccount($account->user);
        } elseif ($account->telegramEmployee) {
            $this->accountService->unlinkEmployeeAccount($account->telegramEmployee);
        }

        $this->telegramService->sendMessage($chatId, 'Your Telegram account has been disconnected. You will no longer receive notifications here.');
    }

    private function handleVerificationCode(string $chatId, string $code, ?string $telegramUserId, ?string $telegramUsername, ?TelegramAccount $account): void
    {
        if ($account && $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, '⚠️ This Telegram account is already connected to an existing user or employee. Please unlink it first if you wish to connect a different account.');

            return;
        }

        // Check if chat is already linked to another account in DB
        $existingLinked = TelegramAccount::where(function ($query) use ($chatId, $telegramUserId) {
            $query->where('chat_id', $chatId);
            if ($telegramUserId) {
                $query->orWhere('telegram_user_id', $telegramUserId);
            }
        })->whereNotNull('verified_at')->first();

        if ($existingLinked) {
            $this->telegramService->sendMessage($chatId, '⚠️ This Telegram account is already connected to an existing user or employee. Please unlink it first if you wish to connect a different account.');

            return;
        }

        // Check the code in DB
        $candidate = TelegramAccount::where('verification_code', $code)->first();

        if (! $candidate) {
            $this->telegramService->sendMessage($chatId, '❌ Sorry, this Telegram linking link is invalid or has expired. Please ask your administrator for a new connection link.');

            return;
        }

        if ($candidate->verification_expires_at && $candidate->verification_expires_at->isPast()) {
            $this->telegramService->sendMessage($chatId, '❌ Sorry, this Telegram linking link is invalid or has expired. Please ask your administrator for a new connection link.');

            return;
        }

        // Check if inactive employee
        if ($candidate->telegram_employee_id) {
            $employee = TelegramEmployee::find($candidate->telegram_employee_id);
            if (! $employee || ! $employee->is_active) {
                $this->telegramService->sendMessage($chatId, '❌ Sorry, your employee account is currently inactive. Please contact your administrator.');

                return;
            }
        }

        // Check if inactive web user
        if ($candidate->user_id) {
            $user = User::find($candidate->user_id);
            if (! $user || ($user->status && $user->status->value === 'inactive')) {
                $this->telegramService->sendMessage($chatId, '❌ Sorry, your user account is currently inactive. Please contact your administrator.');

                return;
            }
        }

        $linkedAccount = $this->accountService->verifyCodeAndLink($code, $chatId, $telegramUserId, $telegramUsername);

        if ($linkedAccount) {
            $ownerName = $linkedAccount->owner_name;
            $msg = "✅ <b>Telegram connected successfully!</b>\n\n";
            $msg .= "Your Telegram account is now connected to your company account (<b>{$ownerName}</b>).\n\n";
            $msg .= "You can now receive assigned tasks and checklist reminders here.\n\n";
            $msg .= 'Send /checklists to check your open checklists, or /tasks to view your assigned tasks.';
            $this->telegramService->sendMessage($chatId, $msg);
        } else {
            $this->telegramService->sendMessage($chatId, '❌ Sorry, this Telegram linking link is invalid or has expired. Please ask your administrator for a new connection link.');
        }
    }

    private function handleTasksCommand(string $chatId, ?TelegramAccount $account): void
    {
        if (! $account || ! $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, '🔒 Please link your account first to view tasks.');

            return;
        }

        $owner = $account->owner();
        $query = Task::query()->where('status', '!=', TaskStatus::COMPLETED)->where('status', '!=', TaskStatus::CANCELLED);

        if ($owner instanceof User) {
            $query->where('assigned_to', $owner->id);
        } elseif ($owner instanceof TelegramEmployee) {
            $query->where('assigned_telegram_employee_id', $owner->id);
        } else {
            $this->telegramService->sendMessage($chatId, 'No assigned tasks found.');

            return;
        }

        $tasks = $query->orderBy('due_date')->take(10)->get();

        if ($tasks->isEmpty()) {
            $this->telegramService->sendMessage($chatId, "🎉 You have no pending tasks!\nAll caught up.");

            return;
        }

        $msg = "📋 <b>Your Pending Tasks ({$tasks->count()}):</b>\n\n";
        foreach ($tasks as $index => $task) {
            $num = $index + 1;
            $due = $task->due_date ? $task->due_date->format('d M, h:i A') : 'No due date';
            $msg .= "<b>{$num}. [{$task->code}] {$task->title}</b>\n";
            $msg .= "   Due: {$due}\n";
            $msg .= "   Complete: <code>DONE {$task->code}</code>\n\n";
        }

        $this->telegramService->sendMessage($chatId, $msg);
    }

    private function handleChecklistsCommand(string $chatId, ?TelegramAccount $account): void
    {
        if (! $account || ! $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, '🔒 Please link your account first to view checklists.');

            return;
        }

        $owner = $account->owner();
        $executions = $this->getOpenExecutionsForOwner($owner);

        if ($executions->isEmpty()) {
            $this->telegramService->sendMessage($chatId, "🎉 No pending process checklists!\nAll processes are complete.");

            return;
        }

        // Show detailed prompt for the first active execution
        $activeExec = $executions->first();
        $this->sendExecutionPrompt($chatId, $activeExec);
    }

    private function handleTaskCompletion(string $chatId, ?string $taskCode, ?TelegramAccount $account): void
    {
        if (! $account || ! $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, '🔒 Please link your account first.');

            return;
        }

        $owner = $account->owner();
        $query = Task::query()->where('status', '!=', TaskStatus::COMPLETED);

        if ($owner instanceof User) {
            $query->where('assigned_to', $owner->id);
        } elseif ($owner instanceof TelegramEmployee) {
            $query->where('assigned_telegram_employee_id', $owner->id);
        }

        if ($taskCode) {
            $query->where('code', strtoupper($taskCode));
        }

        $task = $query->first();

        if (! $task) {
            $this->telegramService->sendMessage($chatId, '❌ No pending task found'.($taskCode ? " with code '{$taskCode}'." : '.'));

            return;
        }

        $user = ($owner instanceof User) ? $owner : null;
        $employee = ($owner instanceof TelegramEmployee) ? $owner : null;

        $this->taskService->completeTask($task, $user, $employee);
        $this->telegramService->sendMessage($chatId, "✅ <b>Task Completed:</b> [{$task->code}] {$task->title}\nGreat job!");
    }

    private function handleChecklistResponse(string $chatId, string $text, ?TelegramAccount $account): bool
    {
        if (! $account || ! $account->isVerified()) {
            return false;
        }

        $owner = $account->owner();
        $executions = $this->getOpenExecutionsForOwner($owner);

        if ($executions->isEmpty()) {
            return false;
        }

        $activeExec = $executions->first();
        $activeExec->load(['items.processItem', 'process']);

        // Find the first unanswered item
        $unansweredItem = $activeExec->items->first(fn ($item) => ! $item->isAnswered());

        if (! $unansweredItem) {
            // All answered, prompt completion
            $this->telegramService->sendMessage($chatId, "All checklist items have been answered.\nReply <code>COMPLETE</code> to finalize and submit.");

            return true;
        }

        // Validate response based on response_type
        $cleanResponse = trim($text);
        $tokens = preg_split('/\s+/', $cleanResponse);
        $firstToken = strtoupper($tokens[0] ?? '');

        $responseType = $unansweredItem->response_type ?? ProcessResponseType::YES_NO;

        $valid = false;
        $storedValue = $cleanResponse;

        switch ($responseType) {
            case ProcessResponseType::YES_NO:
                if (in_array($firstToken, ['YES', 'Y', '1', 'TRUE'])) {
                    $valid = true;
                    $storedValue = 'yes';
                } elseif (in_array($firstToken, ['NO', 'N', '0', 'FALSE'])) {
                    $valid = true;
                    $storedValue = 'no';
                } else {
                    $this->telegramService->sendMessage($chatId, "❌ Invalid response for '{$unansweredItem->question_snapshot}'.\nPlease reply <b>YES</b> or <b>NO</b>.");

                    return true;
                }
                break;

            case ProcessResponseType::YES_NO_NA:
                if (in_array($firstToken, ['YES', 'Y', '1', 'TRUE'])) {
                    $valid = true;
                    $storedValue = 'yes';
                } elseif (in_array($firstToken, ['NO', 'N', '0', 'FALSE'])) {
                    $valid = true;
                    $storedValue = 'no';
                } elseif (in_array($firstToken, ['NA', 'N/A'])) {
                    $valid = true;
                    $storedValue = 'na';
                } else {
                    $this->telegramService->sendMessage($chatId, "❌ Invalid response for '{$unansweredItem->question_snapshot}'.\nPlease reply <b>YES</b>, <b>NO</b>, or <b>NA</b>.");

                    return true;
                }
                break;

            case ProcessResponseType::NUMBER:
                $candidate = $cleanResponse;
                if (in_array($firstToken, ['NUM', 'NUMBER']) && count($tokens) > 1) {
                    $candidate = end($tokens);
                }
                if (is_numeric($candidate)) {
                    $valid = true;
                    $storedValue = $candidate;
                } else {
                    $this->telegramService->sendMessage($chatId, "❌ Invalid response for '{$unansweredItem->question_snapshot}'.\nPlease reply with a valid <b>numeric</b> value.");

                    return true;
                }
                break;

            case ProcessResponseType::TEXT:
            default:
                $candidate = $cleanResponse;
                if ($firstToken === 'TEXT' && count($tokens) > 1) {
                    // Check if format is "TEXT <code/index> <text>" or "TEXT <text>"
                    if (count($tokens) >= 4 && is_numeric($tokens[2])) {
                        $candidate = implode(' ', array_slice($tokens, 3));
                    } elseif (count($tokens) >= 3 && is_numeric($tokens[1])) {
                        $candidate = implode(' ', array_slice($tokens, 2));
                    } else {
                        $candidate = implode(' ', array_slice($tokens, 1));
                    }
                }
                if (strlen($candidate) > 0) {
                    $valid = true;
                    $storedValue = $candidate;
                }
                break;
        }

        if ($valid) {
            $user = ($owner instanceof User) ? $owner : null;
            $employee = ($owner instanceof TelegramEmployee) ? $owner : null;
            $this->executionService->saveSingleItemResponse($unansweredItem, $storedValue, null, $owner);

            // Re-check progress
            $activeExec->refresh();
            $this->sendExecutionPrompt($chatId, $activeExec, "✅ Recorded: <b>{$storedValue}</b>\n\n");

            return true;
        }

        return false;
    }

    private function handleChecklistCompletion(string $chatId, ?TelegramAccount $account): void
    {
        if (! $account || ! $account->isVerified()) {
            $this->telegramService->sendMessage($chatId, '🔒 Please link your account first.');

            return;
        }

        $owner = $account->owner();
        $executions = $this->getOpenExecutionsForOwner($owner);

        if ($executions->isEmpty()) {
            $this->telegramService->sendMessage($chatId, '⚠️ No open checklist found to complete.');

            return;
        }

        $activeExec = $executions->first();
        $activeExec->load(['items.processItem', 'process']);

        // Check if all required items are answered
        $unanswered = $activeExec->items->first(fn ($item) => ($item->processItem?->is_required ?? true) && ! $item->isAnswered());

        if ($unanswered) {
            $this->telegramService->sendMessage($chatId, "❌ Cannot complete: Required item '{$unanswered->question_snapshot}' is not yet answered.\nPlease provide an answer first.");

            return;
        }

        // Build answers array
        $answers = [];
        foreach ($activeExec->items as $item) {
            $answers[$item->id] = [
                'response' => $item->response,
                'notes' => $item->notes,
            ];
        }

        $result = $this->executionService->confirmExecution($activeExec, $answers, $owner);

        if ($result['success']) {
            $this->telegramService->sendMessage($chatId, "🎉 <b>Checklist Completed!</b>\n\n<b>Process:</b> {$activeExec->process->name}\nCompleted at: ".now()->format('d M Y, h:i A')."\n\nAll escalations resolved. Excellent work!");
        } else {
            $this->telegramService->sendMessage($chatId, "❌ Completion failed: {$result['message']}");
        }
    }

    private function sendExecutionPrompt(string $chatId, ProcessExecution $execution, string $prefix = ''): void
    {
        $execution->load(['items.processItem', 'process']);

        $total = $execution->items->count();
        $answered = $execution->items->whereNotNull('response')->count();

        $msg = $prefix;
        $msg .= "📋 <b>{$execution->process->name}</b>\n";
        $msg .= "<b>Progress:</b> {$answered} / {$total}\n\n";

        foreach ($execution->items as $index => $item) {
            $num = $index + 1;
            if ($item->isAnswered()) {
                $msg .= "{$num}. {$item->question_snapshot} — ✅ <b>{$item->response}</b>\n";
            } else {
                $msg .= "{$num}. {$item->question_snapshot} — ⏳ <i>Pending</i>\n";
            }
        }

        $nextUnanswered = $execution->items->first(fn ($item) => ! $item->isAnswered());

        if ($nextUnanswered) {
            $help = match ($nextUnanswered->response_type?->value ?? 'yes_no') {
                'yes_no_na' => 'Reply: <code>YES</code> / <code>NO</code> / <code>NA</code>',
                'number' => 'Reply with a number',
                'text' => 'Reply with your response text',
                default => 'Reply: <code>YES</code> / <code>NO</code>',
            };

            $msg .= "\n👉 <b>Next Question:</b>\n<b>{$nextUnanswered->question_snapshot}</b>\n{$help}";
        } else {
            $msg .= "\n✨ <b>All questions answered!</b>\nReply <code>COMPLETE</code> to submit and finalize this checklist.";
        }

        $this->telegramService->sendMessage($chatId, $msg);
    }

    /**
     * Get open executions for user or telegram employee.
     */
    private function getOpenExecutionsForOwner(User|TelegramEmployee|null $owner)
    {
        if (! $owner) {
            return collect();
        }

        $query = ProcessExecution::query()
            ->whereIn('status', [ProcessExecutionStatus::PENDING, ProcessExecutionStatus::IN_PROGRESS])
            ->where('scheduled_for', '<=', now()->endOfDay());

        if ($owner instanceof User) {
            $query->whereHas('process', fn ($q) => $q->where('responsible_user_id', $owner->id));
        } elseif ($owner instanceof TelegramEmployee) {
            $query->whereHas('process', fn ($q) => $q->where('responsible_telegram_employee_id', $owner->id));
        }

        return $query->orderBy('scheduled_for')->get();
    }
}
