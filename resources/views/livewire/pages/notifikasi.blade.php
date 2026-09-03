<?php

use App\Enums\NotificationType;
use App\Models\DamageReport;
use App\Models\Notification;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Toast;

    #[Computed]
    public function notifications()
    {
        return Notification::where('user_id', auth()->id())
            ->latest('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
    }

    public function markAsRead(int $id): void
    {
        Notification::where('user_id', auth()->id())
            ->where('id', $id)
            ->update(['is_read' => true]);

        unset($this->notifications, $this->unreadCount);
    }

    public function markAllAsRead(): void
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        unset($this->notifications, $this->unreadCount);
        $this->success('Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Klik notifikasi: tandai dibaca lalu navigasi ke laporan terkait
     * (jika ada). Jika laporan sudah dihapus atau notifikasi tanpa
     * related_id, cukup tandai dibaca saja.
     */
    public function openNotification(int $id): void
    {
        $notification = Notification::where('user_id', auth()->id())
            ->findOrFail($id);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        // Navigasi ke laporan terkait jika ada
        if ($notification->related_id && DamageReport::where('id', $notification->related_id)->exists()) {
            $this->redirect(route('reports.index'), navigate: true);
        }

        unset($this->notifications, $this->unreadCount);
    }

    /**
     * Nama computed property tabel untuk WithTableFilters (jika dipakai).
     */
    protected function tableComputed(): string
    {
        return 'notifications';
    }
}; ?>

<div wire:poll.30s>
    <x-mary-header title="Notifikasi" separator class="mb-6!">
        <x-slot:actions>
            @if($this->unreadCount > 0)
                <x-mary-button
                    icon="o-check"
                    label="Tandai Semua Dibaca"
                    class="btn-ghost btn-sm rounded-full"
                    wire:click="markAllAsRead"
                />
            @endif
        </x-slot:actions>
    </x-mary-header>

    {{-- Stat ringkas --}}
    <div class="flex items-center gap-3 mb-6">
        @if($this->unreadCount > 0)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-error/15 text-error">
                <span class="w-1.5 h-1.5 rounded-full bg-error animate-pulse"></span>
                {{ $this->unreadCount }} belum dibaca
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-success/15 text-success">
                <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                Semua sudah dibaca
            </span>
        @endif
    </div>

    {{-- Daftar notifikasi --}}
    <x-mary-card class="rounded-2xl">
        @if($this->notifications->isEmpty())
            <div class="text-center py-12">
                <x-mary-icon name="o-bell-slash" class="w-12 h-12 text-base-content/20 mx-auto mb-3" />
                <p class="text-sm text-base-content/40">Belum ada notifikasi</p>
            </div>
        @else
            <div class="flex flex-col divide-y divide-base-200">
                @foreach($this->notifications as $notif)
                    @php
                        $type = NotificationType::tryFrom($notif->type);
                        $icon = $type?->icon() ?? 'o-bell';
                        $color = $type?->color() ?? 'primary';
                    @endphp
                    <button
                        wire:click="openNotification({{ $notif->id }})"
                        class="flex items-start gap-3 py-3.5 px-2 text-left w-full rounded-lg transition hover:bg-base-200/50 cursor-pointer {{ !$notif->is_read ? 'bg-primary/[0.03]' : '' }}"
                    >
                        {{-- Ikon tipe --}}
                        <div class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center bg-{{ $color }}/15 text-{{ $color }} mt-0.5">
                            <x-mary-icon name="{{ $icon }}" class="w-5 h-5" />
                        </div>

                        {{-- Konten --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-base-content truncate {{ !$notif->is_read ? '' : 'text-base-content/70' }}">
                                    {{ $notif->title }}
                                </p>
                                @if(!$notif->is_read)
                                    <span class="shrink-0 w-2 h-2 rounded-full bg-primary"></span>
                                @endif
                            </div>
                            <p class="text-xs text-base-content/60 mt-0.5 line-clamp-2">{{ $notif->body }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <p class="text-[11px] text-base-content/35">{{ $notif->created_at->diffForHumans() }}</p>
                                @if($type)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium bg-{{ $color }}/10 text-{{ $color }}">
                                        {{ $type->label() }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Tombol tandai dibaca (tanpa navigasi) --}}
                        @if(!$notif->is_read)
                            <button
                                wire:click.stop="markAsRead({{ $notif->id }})"
                                class="shrink-0 mt-1 p-1.5 rounded-lg text-base-content/30 hover:text-primary hover:bg-primary/10 transition"
                                title="Tandai dibaca"
                            >
                                <x-mary-icon name="o-check" class="w-4 h-4" />
                            </button>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="mt-4">
                <x-mary-pagination :rows="$this->notifications" class="[&_.btn]:rounded-full" />
            </div>
        @endif
    </x-mary-card>
</div>
