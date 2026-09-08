<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('locale');

        // NOTE: `status` is intentionally absent — it is not fillable and the
        // database default ('active') applies. Status changes are admin-only.
        $user = User::create([
            ...$data,
            'locale' => $request->validated('locale', app()->getLocale()),
        ]);

        $user->assignRole('user');

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
