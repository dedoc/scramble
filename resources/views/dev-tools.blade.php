@if(\Dedoc\Scramble\Support\DevTools::enabled())
    @php
        $devToolsData = [
            'diagnostics' => $diagnostics->toArray(),
            'renderer' => $renderer,
            'proNudges' => (object) (isset($proNudge) ? $proNudge->summaries() : []),
        ];
    @endphp

    <script type="application/json" id="scramble-dev-tools-data">@json($devToolsData)</script>

    @if($viteServerUrl = \Dedoc\Scramble\Support\DevTools::viteServerUrl())
        <script type="module" src="{{ $viteServerUrl }}/@@vite/client"></script>
        <script type="module" src="{{ $viteServerUrl }}/resources/js/devtools.tsx"></script>
    @else
        <script type="module" src="{{ route('scramble.dev-tools.asset') }}"></script>
    @endif
@endif
