<?php

namespace App\Console\Commands;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestWelcomeMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:welcome-mail {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending WelcomeMail to a specific email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        // Try to find a user with this email, or create a dummy user
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            // Create a temporary user object for testing
            $user = new User();
            $user->email = $email;
            $user->name = 'Test User';
            $user->first_name = 'Test';
            $user->last_name = 'User';
        }
        
        $this->info("Testing WelcomeMail to: {$email}");
        $this->info("User: {$user->name}");
        $this->newLine();
        
        try {
            // First, test if the view can be rendered
            $this->info('Step 1: Testing view rendering...');
            try {
                $view = view('emails.welcome', ['user' => $user]);
                $html = $view->render();
                $this->info('✅ View rendered successfully (' . strlen($html) . ' bytes)');
            } catch (\Exception $e) {
                $this->error('❌ View rendering failed!');
                $this->error('Error: ' . $e->getMessage());
                $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
                return 1;
            }
            
            $this->newLine();
            $this->info('Step 2: Testing WelcomeMail mailable...');
            
            Mail::to($user->email)->send(new WelcomeMail($user));
            
            $this->info('✅ WelcomeMail sent successfully!');
            $this->info('Please check your inbox (and spam folder) for the welcome email.');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send WelcomeMail!');
            $this->error('Error: ' . $e->getMessage());
            $this->error('Error Code: ' . $e->getCode());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->newLine();
            $this->warn('Full error details:');
            $this->line($e->getTraceAsString());
            
            Log::error('Test WelcomeMail failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
        
        return 0;
    }
}
