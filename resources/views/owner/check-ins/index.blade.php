<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="eyebrow">Match-day operations</p>
            <h1 class="dashboard-title">QR check-in desk</h1>
        </div>
    </x-slot>

    <div class="scanner-layout" data-check-in-scanner>
        <section class="scanner-stage">
            <div class="scanner-frame">
                <video muted playsinline aria-label="QR scanner camera preview"></video>
                <span class="scanner-corner scanner-corner-a"></span>
                <span class="scanner-corner scanner-corner-b"></span>
                <span class="scanner-corner scanner-corner-c"></span>
                <span class="scanner-corner scanner-corner-d"></span>
                <i class="scanner-line" aria-hidden="true"></i>
            </div>
            <div class="scanner-actions">
                <button type="button" class="btn-primary" data-start-camera>Start camera scanner</button>
                <button type="button" class="btn-outline" data-stop-camera hidden>Stop camera</button>
            </div>
            <p class="scanner-privacy">Camera processing stays in this browser. No image or video is uploaded.</p>
        </section>

        <aside class="panel">
            <p class="eyebrow">Scanner status</p>
            <div class="scan-result" data-scan-result aria-live="polite">
                <strong>Ready for a player pass.</strong>
                <p>Use the camera or paste the pass code below.</p>
            </div>

            <form method="POST" action="{{ route('owner.check-ins.scan') }}" class="mt-6 space-y-3">
                @csrf
                <div>
                    <label for="check-in-token">Manual pass code</label>
                    <input id="check-in-token" class="form-input" name="token" autocomplete="off" required placeholder="KPP-CHECKIN:…">
                </div>
                <button class="btn-dark w-full justify-center">Verify and check in</button>
            </form>

            <div class="scanner-rules">
                <strong>Protected check-in</strong>
                <ul>
                    <li>Only managers of the booked court can scan its pass.</li>
                    <li>The pass is valid only during the 60-minute check-in window.</li>
                    <li>Payment status is displayed but does not expose proof files.</li>
                </ul>
            </div>
        </aside>
    </div>
</x-app-layout>
