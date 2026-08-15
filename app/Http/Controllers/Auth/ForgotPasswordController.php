<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Validate the email for the given request.
     *
     * @return void
     */
    protected function validateEmail(Request $request)
    {
        $validate['email'] = 'required|email';

        if (
            iEXSetting('is_security_captcha_type') == 1 &&
            iEXSetting('is_enabled_captcha_reset') == 1
        ) {
            $validate['recaptcha'] = 'required|captcha';
        }
        $request->validate($validate);
    }
}
