@if(\Dedoc\Scramble\Support\DevTools::enabled())
    @if($viteServerUrl = \Dedoc\Scramble\Support\DevTools::viteServerUrl())
        <script type="module" src="{{ $viteServerUrl }}/@@vite/client"></script>
        <script type="module" src="{{ $viteServerUrl }}/resources/js/devtools.js"></script>
    @else
        <script type="module" src="{{ route('scramble.dev-tools.asset', ['file' => 'devtools.js']) }}"></script>
    @endif
@endif
