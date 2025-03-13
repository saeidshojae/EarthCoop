<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\InvitationCode;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected $redirectTo = '/step1';

    public function __construct()
    {
        $this->middleware('guest');
    }

    protected function validator(array $data)
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone_number' => ['nullable', 'string', 'max:15'],
        ];

        if (env('INVITATION_CODE_REQUIRED', false)) {
            $rules['invitation_code'] = ['required', 'exists:invitation_codes,code,used,false'];
        }

        return Validator::make($data, $rules);
    }

    protected function create(array $data)
    {
        if (env('INVITATION_CODE_REQUIRED', false)) {
            $invitationCode = InvitationCode::where('code', $data['invitation_code'])->first();
            $invitationCode->update(['used' => true]);
        }

        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'birth_date' => $data['birth_date'],
            'gender' => $data['gender'],
            'nationality' => $data['nationality'],
            'national_id' => $data['national_id'],
            'phone_number' => $data['phone_number'],
        ]);
    }

    public function processRegister(Request $request)
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        $this->guard()->login($user);

        return redirect($this->redirectPath());
    }
}