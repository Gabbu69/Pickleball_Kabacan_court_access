<x-guest-layout>
    <div class="mb-7">
        <p class="eyebrow">Join the local rally</p>
        <h1 class="mt-2 text-3xl font-extrabold text-ink">Create your player account.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-500">Email verification protects reservations and verified court reviews.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf
        <div>
            <x-input-label for="name" value="Full name" />
            <x-text-input id="name" class="form-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="form-input" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="phone" value="Mobile number" />
            <x-text-input id="phone" class="form-input" type="tel" name="phone" :value="old('phone')" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" class="form-input" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="form-input" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>
        <button class="btn-primary w-full justify-center">Create player account</button>
        <p class="text-center text-sm text-slate-500">Already registered? <a class="font-bold text-teal-700" href="{{ route('login') }}">Log in</a></p>
    </form>
</x-guest-layout>
