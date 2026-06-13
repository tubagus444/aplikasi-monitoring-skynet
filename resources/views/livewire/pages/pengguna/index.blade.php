<?php

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, Toast;

    // Filter & search
    public string $search = '';
    public string $filterRole = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?int $editingId = null;
    public ?int $deletingId = null;
    public ?string $deletingName = null;

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $role = UserRole::Teknisi->value;
    public string $password = '';
    public string $password_confirmation = '';

    #[Computed]
    public function users()
    {
        return User::when($this->search, fn($q) =>
                $q->where(fn($w) =>
                    $w->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                )
            )
            ->when($this->filterRole, fn($q) =>
                $q->where('role', $this->filterRole)
            )
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(10);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->password = '';
        $this->password_confirmation = '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editingId ? ",{$this->editingId}" : ''),
            'role'  => 'required|in:' . implode(',', UserRole::values()),
        ];

        if (! $this->editingId || $this->password !== '') {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        if ($this->editingId) {
            $data = [
                'name'  => $this->name,
                'email' => $this->email,
                'role'  => $this->role,
            ];
            if ($this->password !== '') {
                $data['password'] = $this->password;
            }
            User::findOrFail($this->editingId)->update($data);
        } else {
            User::create([
                'name'     => $this->name,
                'email'    => $this->email,
                'role'     => $this->role,
                'password' => $this->password,
            ]);
        }

        $this->showFormModal = false;
        unset($this->users);
        $this->success($this->editingId ? 'Data pengguna berhasil diperbarui.' : 'Pengguna baru berhasil ditambahkan.');
        $this->resetForm();
        $this->editingId = null;
    }

    public function confirmDelete(int $id): void
    {
        if ($id === auth()->id()) {
            return;
        }
        $user = User::find($id);
        if (! $user) {
            return;
        }
        $this->deletingId = $id;
        $this->deletingName = $user->name;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        // Pertahankan proteksi "tak boleh hapus diri sendiri" di sini juga, bukan
        // hanya di confirmDelete(): properti Livewire bisa dimanipulasi dari klien
        // ($set deletingId), jadi guard harus ditegakkan saat aksi destruktif jalan.
        if ($this->deletingId === auth()->id()) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = null;
            $this->error('Tidak bisa menghapus akun sendiri.');
            return;
        }

        User::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = null;
        unset($this->users);
        $this->success('Pengguna berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->role = UserRole::Teknisi->value;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->users);
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
        unset($this->users);
    }
}; ?>

<div>
    <x-mary-header title="Manajemen Pengguna" separator class="mb-6!">
        <x-slot:actions>
            <x-mary-button
                icon="o-plus"
                label="Tambah Pengguna"
                class="btn-primary btn-sm rounded-full"
                wire:click="openCreate"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filter & Search --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <x-mary-input
            wire:model.live.debounce="search"
            placeholder="Cari nama atau email..."
            icon="o-magnifying-glass"
            class="input-sm w-56 rounded-full"
        />
        <x-filter-chips :options="['' => 'Semua'] + \App\Enums\UserRole::options()" field="filterRole" :selected="$filterRole" />
    </div>

    {{-- Tabel --}}
    <x-table-card :rows="$this->users" empty-icon="o-users" empty-text="Tidak ada pengguna ditemukan">
        <x-slot:head>
            <th class="w-12">#</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Bergabung</th>
            <th class="w-20">Aksi</th>
        </x-slot:head>

        @foreach($this->users as $user)
            <tr class="hover:bg-base-200 transition-colors">
                <td class="text-base-content/40 text-xs">{{ $user->id }}</td>
                <td>
                    <div class="flex items-center gap-2">
                        <x-avatar
                            :placeholder="strtoupper(substr($user->name, 0, 1))"
                            class="w-7! h-7! text-xs! bg-primary/10 text-primary"
                        />
                        <span class="font-medium text-sm">{{ $user->name }}</span>
                        @if($user->id === auth()->id())
                            <x-badge value="Anda" class="badge-xs badge-ghost" />
                        @endif
                    </div>
                </td>
                <td class="text-sm text-base-content/70">{{ $user->email }}</td>
                <td>
                    @php
                        // Pil role: dot+pil, selaras dengan indikator status di halaman lain.
                        $roleStyle = $user->isAdmin()
                            ? ['pill' => 'bg-primary/15 text-primary',     'dot' => 'bg-primary',   'label' => 'Admin']
                            : ['pill' => 'bg-secondary/15 text-secondary', 'dot' => 'bg-secondary', 'label' => 'Teknisi'];
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $roleStyle['pill'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $roleStyle['dot'] }}"></span>
                        {{ $roleStyle['label'] }}
                    </span>
                </td>
                <td class="text-xs text-base-content/50">
                    {{ $user->created_at->format('d/m/Y') }}
                </td>
                <td>
                    <div class="flex gap-1">
                        <x-mary-button
                            icon="o-pencil"
                            class="btn-ghost btn-xs rounded-full"
                            wire:click="openEdit({{ $user->id }})"
                            tooltip="Edit"
                        />
                        <x-mary-button
                            icon="o-trash"
                            class="btn-ghost btn-xs rounded-full {{ $user->id === auth()->id() ? 'opacity-20 cursor-not-allowed' : 'text-error' }}"
                            wire:click="confirmDelete({{ $user->id }})"
                            tooltip="{{ $user->id === auth()->id() ? 'Tidak bisa hapus akun sendiri' : 'Hapus' }}"
                            :disabled="$user->id === auth()->id()"
                        />
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table-card>

    {{-- Modal Buat / Edit Pengguna --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Pengguna' : 'Tambah Pengguna'" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-mary-input
                label="Nama Lengkap"
                wire:model="name"
                placeholder="Budi Santoso"
                icon="o-user"
                required
            />
            <x-mary-input
                label="Email"
                wire:model="email"
                type="email"
                placeholder="budi@skynet.id"
                icon="o-envelope"
                required
            />
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-base-content/70 mb-2">Role</label>
            {{-- Kartu pilihan role: has-checked me-highlight kartu via CSS murni --}}
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border border-base-300 hover:bg-base-200/60 transition-colors has-checked:border-primary has-checked:bg-primary/5">
                    <input type="radio" wire:model="role" value="{{ \App\Enums\UserRole::Teknisi->value }}" class="radio radio-sm radio-primary shrink-0" />
                    <div class="flex items-center gap-2 min-w-0">
                        <x-mary-icon name="o-wrench-screwdriver" class="w-5 h-5 text-secondary shrink-0" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium leading-tight">Teknisi</p>
                            <p class="text-xs text-base-content/50 leading-tight">Akses aplikasi lapangan</p>
                        </div>
                    </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border border-base-300 hover:bg-base-200/60 transition-colors has-checked:border-primary has-checked:bg-primary/5">
                    <input type="radio" wire:model="role" value="{{ \App\Enums\UserRole::Admin->value }}" class="radio radio-sm radio-primary shrink-0" />
                    <div class="flex items-center gap-2 min-w-0">
                        <x-mary-icon name="o-shield-check" class="w-5 h-5 text-primary shrink-0" />
                        <div class="min-w-0">
                            <p class="text-sm font-medium leading-tight">Admin</p>
                            <p class="text-xs text-base-content/50 leading-tight">Akses penuh panel web</p>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <x-mary-input
                label="{{ $editingId ? 'Password Baru' : 'Password' }}"
                wire:model="password"
                type="password"
                placeholder="{{ $editingId ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' }}"
                icon="o-lock-closed"
                :required="!$editingId"
            />
            <x-mary-input
                label="Konfirmasi Password"
                wire:model="password_confirmation"
                type="password"
                placeholder="Ulangi password"
                icon="o-lock-closed"
                :required="!$editingId"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost rounded-full" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Tambah Pengguna'"
                class="btn-primary rounded-full"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-confirm-delete-modal title="Hapus Pengguna" noun="pengguna" :name="$deletingName" action="deleteUser" />
</div>
