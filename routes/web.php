<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', fn () => view('welcome'));
Route::get('/test-mail', function () {
    Mail::raw('This is a test email from Laravel using Gmail SMTP.', function ($msg) {
        $msg->to('souravbehuria10@gmail.com')
            ->subject('Laravel Gmail Test');
    });

    return 'Mail Sent!';
});