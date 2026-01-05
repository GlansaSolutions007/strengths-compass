<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Test;
use App\Models\TestResult;
use App\Mail\TestCompletionMail;
use App\Mail\TestSubmissionAdminMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendTestCompletionEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $testResultId,
        public int $userId,
        public int $testId
    ) {
        // Set queue connection if needed (defaults to config/queue.php)
        // $this->onConnection('database');
        // $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Load models
            $testResult = TestResult::find($this->testResultId);
            $user = User::find($this->userId);
            $test = Test::find($this->testId);

            if (!$testResult || !$user || !$test) {
                Log::warning('SendTestCompletionEmails: Missing required models', [
                    'test_result_id' => $this->testResultId,
                    'user_id' => $this->userId,
                    'test_id' => $this->testId,
                ]);
                return;
            }

            // 1️⃣ Send test completion email to user
            if (!empty($user->email)) {
                try {
                    Log::info('=== TEST SUBMISSION (QUEUE): Starting test completion email send process ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'test_id' => $this->testId,
                        'test_result_id' => $this->testResultId,
                    ]);

                    Mail::to($user->email)->send(new TestCompletionMail($user, $test, $testResult));
                    
                    Log::info('=== TEST SUBMISSION (QUEUE): Test completion email sent successfully ===', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'test_result_id' => $this->testResultId,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('=== TEST SUBMISSION (QUEUE): Failed to send test completion email ===', [
                        'user_id' => $user->id,
                        'test_result_id' => $this->testResultId,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                Log::warning('Cannot send test completion email: user email is empty', [
                    'user_id' => $this->userId,
                ]);
            }

            // 2️⃣ Send test submission notification to admins
            try {
                $admins = User::where('role', 'admin')->get();
                
                if ($admins->isNotEmpty()) {
                    Log::info('=== TEST SUBMISSION (QUEUE): Starting admin notification email send process ===', [
                        'admin_count' => $admins->count(),
                        'user_id' => $this->userId,
                        'test_id' => $this->testId,
                        'test_result_id' => $this->testResultId,
                    ]);

                    $sentCount = 0;
                    foreach ($admins as $admin) {
                        if (!empty($admin->email)) {
                            try {
                                Mail::to($admin->email)->send(new TestSubmissionAdminMail($user, $test, $testResult));
                                $sentCount++;
                            } catch (\Throwable $e) {
                                Log::error('=== TEST SUBMISSION (QUEUE): Failed to send admin email to individual admin ===', [
                                    'admin_id' => $admin->id,
                                    'admin_email' => $admin->email,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    Log::info('=== TEST SUBMISSION (QUEUE): Admin notification emails sent successfully ===', [
                        'admin_count' => $admins->count(),
                        'sent_count' => $sentCount,
                        'test_result_id' => $this->testResultId,
                    ]);
                } else {
                    Log::warning('No admin users found to send notification email', [
                        'test_result_id' => $this->testResultId,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('=== TEST SUBMISSION (QUEUE): Failed to send admin notification emails ===', [
                    'user_id' => $this->userId,
                    'test_result_id' => $this->testResultId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('=== TEST SUBMISSION (QUEUE): Job failed completely ===', [
                'test_result_id' => $this->testResultId,
                'user_id' => $this->userId,
                'test_id' => $this->testId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('=== TEST SUBMISSION (QUEUE): Job permanently failed ===', [
            'test_result_id' => $this->testResultId,
            'user_id' => $this->userId,
            'test_id' => $this->testId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

