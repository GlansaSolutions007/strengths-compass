<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending an email to verify mail configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info('Testing email configuration...');
        $this->info('Mail Driver: ' . config('mail.default'));
        $this->info('SMTP Host: ' . config('mail.mailers.smtp.host'));
        $this->info('SMTP Port: ' . config('mail.mailers.smtp.port'));
        $this->info('SMTP Encryption: ' . config('mail.mailers.smtp.encryption'));
        $this->info('SMTP Username: ' . config('mail.mailers.smtp.username'));
        $this->info('From Address: ' . config('mail.from.address'));
        $this->newLine();
        
        try {
            $this->info("Attempting to send test email to: {$email}");
            
            Mail::raw('This is a test email from Strengths Compass. If you receive this, your email configuration is working correctly!', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email from Strengths Compass');
            });
            
            $this->info('✅ Email sent successfully!');
            $this->info('Please check your inbox (and spam folder) for the test email.');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send email!');
            $this->error('Error: ' . $e->getMessage());
            $this->error('Error Code: ' . $e->getCode());
            $this->newLine();
            $this->warn('Full error details:');
            $this->line($e->getTraceAsString());
            
            Log::error('Test email failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
        
        return 0;
    }
}
