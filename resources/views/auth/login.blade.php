<x-guest-layout>
    <div class="mb-7">
        <p class="eyebrow">Welcome back</p>
        <h1 class="mt-2 text-3xl font-extrabold text-ink">Sign in and get back on court.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Manage reservations, payments, reviews, or your venue from one account.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="form-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="form-input" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" class="rounded border-slate-300 text-teal-600 focus:ring-teal-500" name="remember">
            Remember me
        </label>
        <button class="btn-primary w-full justify-center">Log in</button>
        <div class="flex items-center justify-between gap-3 text-sm">
            <a class="font-bold text-slate-500 hover:text-teal-700" href="{{ route('password.request') }}">Forgot password?</a>
            <a class="font-bold text-teal-700 hover:text-coral" href="{{ route('register') }}">Create account</a>
        </div>
    </form>
</x-guest-layout>
