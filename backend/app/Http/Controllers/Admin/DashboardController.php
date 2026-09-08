<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Services\FeatureFlagService;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function index(FeatureFlagService $flags)
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'verified' => User::query()->whereNotNull('email_verified_at')->count(),
                'banned' => User::query()->where('status', User::STATUS_BANNED)->count(),
                'roles' => Role::query()->count(),
                'artists' => Artist::query()->count(),
                'albums' => Album::query()->count(),
                'tracks' => Track::query()->count(),
            ],
            'flags' => $flags->publicFlags(auth()->user()),
        ]);
    }
}
