<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = $request->user() ?? auth()->user();

        if ($user?->role === UserRole::Cashier) {
            return redirect()->intended(route('pos.index'));
        }

        return redirect()->intended(Filament::getUrl());
    }
}
