<?php

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

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $role = 'teknisi';
    public string $password = '';
    public string $password_confirmation = '';

    #[Computed]
    public function users()
    {
        return User::when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
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
            'role'  => 'required|in:admin,teknisi',
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
        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        User::findOrFail($this->deletingId)->delete();
        $this->showDeleteModal = false;
        $this->deletingId = null;
        unset($this->users);
        $this->success('Pengguna berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->role = 'teknisi';
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
        <div class="flex items-center gap-2 flex-wrap">
            @foreach (['' => 'Semua', 'admin' => 'Admin', 'teknisi' => 'Teknisi'] as $val => $label)
                <button
                    wire:click="$set('filterRole', '{{ $val }}')"
                    @class([
                        'btn btn-sm rounded-full',
                        'btn-primary' => $filterRole === $val,
                        'btn-ghost border border-base-300' => $filterRole !== $val,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- Tabel --}}
    <x-mary-card class="rounded-2xl">
        @if($this->users->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-mary-icon name="o-users" class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">Tidak ada pengguna ditemukan</p>
            </div>
        @else
            <div class="overflow-x-auto no-scrollbar">
                <table class="table table-sm w-full">
                    <thead>
                        <tr class="text-xs text-base-content/50 uppercase">
                            <th class="w-12">#</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Bergabung</th>
                            <th class="w-20">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
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
                                        $roleStyle = $user->role === 'admin'
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
                                            class="btn-ghost btn-xs"
                                            wire:click="openEdit({{ $user->id }})"
                                            tooltip="Edit"
                                        />
                                        <x-mary-button
                                            icon="o-trash"
                                            class="btn-ghost btn-xs {{ $user->id === auth()->id() ? 'opacity-20 cursor-not-allowed' : 'text-error' }}"
                                            wire:click="confirmDelete({{ $user->id }})"
                                            tooltip="{{ $user->id === auth()->id() ? 'Tidak bisa hapus akun sendiri' : 'Hapus' }}"
                                            :disabled="$user->id === auth()->id()"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mary-pagination :rows="$this->users" class="mt-4" />
        @endif
    </x-mary-card>

    {{-- Modal Buat / Edit Pengguna --}}
    <x-mary-modal wire:model="showFormModal" :title="$editingId ? 'Edit Pengguna' : 'Tambah Pengguna'" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-mary-input
                label="Nama Lengkap"
                wire:model="name"
                placeholder="Budi Santoso"
                required
            />
            <x-mary-input
                label="Email"
                wire:model="email"
                type="email"
                placeholder="budi@skynet.id"
                required
            />
        </div>

        <div class="mt-4">
            <label class="block text-sm font-medium text-base-content/70 mb-2">Role</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model="role" value="teknisi" class="radio radio-sm radio-primary" />
                    <span class="text-sm">Teknisi</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model="role" value="admin" class="radio radio-sm radio-primary" />
                    <span class="text-sm">Admin</span>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <x-mary-input
                label="{{ $editingId ? 'Password Baru' : 'Password' }}"
                wire:model="password"
                type="password"
                placeholder="{{ $editingId ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' }}"
                :required="!$editingId"
            />
            <x-mary-input
                label="Konfirmasi Password"
                wire:model="password_confirmation"
                type="password"
                placeholder="Ulangi password"
                :required="!$editingId"
            />
        </div>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost" wire:click="$set('showFormModal', false)" />
            <x-mary-button
                :label="$editingId ? 'Simpan Perubahan' : 'Tambah Pengguna'"
                class="btn-primary"
                wire:click="save"
                spinner="save"
            />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Modal Konfirmasi Hapus --}}
    <x-mary-modal wire:model="showDeleteModal" title="Hapus Pengguna" separator>
        <p class="text-sm text-base-content/70">
            Yakin ingin menghapus pengguna
            <strong class="text-base-content">
                {{ $deletingId ? User::find($deletingId)?->name : '' }}
            </strong>?
            Tindakan ini tidak dapat dibatalkan.
        </p>

        <x-slot:actions>
            <x-mary-button label="Batal" class="btn-ghost" wire:click="$set('showDeleteModal', false)" />
            <x-mary-button label="Ya, Hapus" class="btn-error" wire:click="deleteUser" spinner="deleteUser" />
        </x-slot:actions>
    </x-mary-modal>
</div>
