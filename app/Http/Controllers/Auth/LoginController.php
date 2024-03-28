<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordMail;
use App\Models\Student;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Traits\General;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use IvanoMatteo\LaravelDeviceTracking\Models\Device;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Cookie;


class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use General;
    protected function showLoginForm()
    {
        Cookie::queue(Cookie::forget('_uuid_d'));
        $data['pageTitle'] = __('Login');
        $data['title'] = __('Login');
        return view('auth.login', $data);
    }



    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }


     /**
     * Write code on Method
     *
     * @return response()
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);


        $field = 'email';
        if (filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
            $field = 'email';
        }
        elseif (is_numeric($request->input('email'))) {
            $field = 'mobile_number';
        }

        $request->merge([$field => $request->input('email')]);

        $credentials = $request->only($field, 'password');

        /*
        role 2 = instructor
        role 3 = student
        -----------------
        status 1 = Approved
        status 2 = Blocked
        status 0 = Pending
        */
        if (Auth::attempt($credentials)) {
            if (Auth::user()->role == USER_ROLE_STUDENT && Auth::user()->student->status == STATUS_REJECTED){
                Auth::logout();
                $this->showToastrMessage('error', __('Your account has been blocked!'));
                return redirect("login");
            }

            if (Auth::user()->role == USER_ROLE_STUDENT && Auth::user()->student->status == STATUS_PENDING){
                Auth::logout();
                $this->showToastrMessage('warning', 'Your account has been in pending status. Please wait until approval.');
                return redirect("login");
            }

            if (Auth::user()->role == USER_ROLE_INSTRUCTOR && Auth::user()->student->status == STATUS_REJECTED && Auth::user()->instructor->status == STATUS_REJECTED){
                Auth::logout();
                $this->showToastrMessage('error', __('Your account has been blocked!'));
                return redirect("login");
            }
            if (get_option('registration_email_verification') == 1){
                $user = Auth::user()->hasVerifiedEmail();
                if (!$user){
                    Auth::logout();
                    $this->showToastrMessage('error', __('Your email is not verified!'));
                    return redirect("login");
                }
            }

            if (Auth::user()->is_admin())
            {
                return redirect(route('admin.dashboard'));

            } else {
                return redirect(route('main.index'));
            }
        }

        $this->showToastrMessage('error', __('Ops! You have entered invalid credentials'));
        return redirect("login");
    }

}