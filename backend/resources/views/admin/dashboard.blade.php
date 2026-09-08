<x-layouts.admin :title="__('admin.dashboard')">
    <h1>{{ __('admin.dashboard') }}</h1>
    <p class="muted">{{ __('admin.welcome') }}</p>

    <div class="stat-grid">
        <div class="stat"><strong>{{ $stats['users'] }}</strong><span>{{ __('admin.stats.users') }}</span></div>
        <div class="stat"><strong>{{ $stats['verified'] }}</strong><span>{{ __('admin.stats.verified') }}</span></div>
        <div class="stat"><strong>{{ $stats['banned'] }}</strong><span>{{ __('admin.stats.banned') }}</span></div>
        <div class="stat"><strong>{{ $stats['roles'] }}</strong><span>{{ __('admin.stats.roles') }}</span></div>
    </div>

    <div class="card" style="margin-block-start:1rem">
        <h2>{{ __('admin.flags_title') }}</h2>
        <table class="flag-table">
            <tbody>
                @foreach ($flags as $flag => $enabled)
                    <tr>
                        <th>{{ $flag }}</th>
                        <td class="{{ $enabled ? 'pill-on' : 'pill-off' }}">{{ $enabled ? '●' : '○' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
