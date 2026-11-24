@php
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\Contracts\Pagination\Paginator;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
@endphp

{{--
    Notificações do banco como menu suspenso (como o do tema), no lugar do drawer lateral do Filament.
    Baseado em filament/notifications database-notifications.blade.php.
--}}
<div
    class="fi-no-database"
    @if ($pollingInterval) wire:poll.{{ $pollingInterval }} @endif
>
    <x-filament::dropdown placement="bottom-end" width="md" max-height="28rem" teleport>
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        <div style="padding: .75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: .75rem">
            <h2 id="database-notifications.heading" class="fi-modal-heading" style="margin: 0; font-size: .95rem; font-weight: 600">
                {{ __('filament-notifications::database.modal.heading') }}

                @if ($unreadNotificationsCount)
                    <span
                        {{
                            (new FilamentComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                'fi-badge fi-size-xs',
                            ])
                        }}
                    >
                        {{ $unreadNotificationsCount }}
                    </span>
                @endif
            </h2>

            @if ($hasNotifications)
                <div class="fi-ac">
                    @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                        {{ $this->markAllNotificationsAsReadAction }}
                    @endif

                    @if ($this->clearNotificationsAction?->isVisible())
                        {{ $this->clearNotificationsAction }}
                    @endif
                </div>
            @endif
        </div>

        @if ($hasNotifications)
            <div
                aria-label="{{ __('filament-notifications::database.modal.heading') }}"
                role="list"
                class="fi-no-notifications"
            >
                @foreach ($notifications as $notification)
                    <div
                        role="listitem"
                        wire:key="{{ $notification->getKey() }}.database-notifications.ctn"
                        @class([
                            'fi-no-notification-read-ctn' => ! $notification->unread(),
                            'fi-no-notification-unread-ctn' => $notification->unread(),
                        ])
                    >
                        @if ($notification->unread())
                            <span class="fi-sr-only">
                                {{ __('filament-notifications::database.modal.unread_label') }}
                            </span>
                        @endif

                        {{ $this->getNotification($notification)->inline() }}
                    </div>
                @endforeach
            </div>

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                    <script>
                        setUpFilamentBroadcastNotifications({
                            $wire,
                            broadcastChannel: @js($broadcastChannel),
                            event: '.database-notifications.sent',
                            onNotification: () => $wire.call('$refresh'),
                        })
                    </script>
                @endscript
            @endif

            @if ($isPaginated)
                <div style="padding: .5rem 1rem">
                    <x-filament::pagination :paginator="$notifications" />
                </div>
            @endif
        @else
            <p style="padding: 1.5rem 1rem; text-align: center; font-size: .875rem; color: rgb(128 128 128)">
                {{ __('filament-notifications::database.modal.empty.heading') }}
            </p>
        @endif
    </x-filament::dropdown>
</div>
