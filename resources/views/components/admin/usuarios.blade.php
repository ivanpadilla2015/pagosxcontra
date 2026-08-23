<?php

use App\Models\User;
use App\Models\Regional;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public bool $createModalOpen = false;
    public bool $editModalOpen = false;
    public bool $deleteModalOpen = false;

    public ?int $editingId = null;
    public ?int $deletingId = null;
    public string $deletingName = '';

    // Campos del formulario
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public ?int $regional_id = null;
    public string $selectedRole = '';

    protected function rules(): array
    {
        $id = $this->editingId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($id)],
            'password' => $id ? ['nullable', 'string', 'min:8'] : ['required', 'string', 'min:8'],
            'regional_id' => ['nullable', 'exists:regionals,id'],
            'selectedRole' => ['required', 'exists:roles,name'],
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function usuarios()
    {
        return User::with('roles', 'regional')
            ->when($this->search, function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('email', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->paginate(10);
    }

    public function regionals()
    {
        return Regional::orderBy('name')->get();
    }

    public function allRoles()
    {
        return Role::where('guard_name', 'web')->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->regional_id = null;
        $this->selectedRole = '';
        $this->createModalOpen = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->regional_id = $user->regional_id;
        $this->selectedRole = $user->roles->first()?->name ?? '';
        $this->editModalOpen = true;
    }

    public function openDeleteModal(int $id, string $name): void
    {
        $this->deletingId = $id;
        $this->deletingName = $name;
        $this->deleteModalOpen = true;
    }

    public function save(): void
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => bcrypt($this->password),
            'regional_id' => $this->regional_id ?: null,
        ]);

        $user->syncRoles([$this->selectedRole]);

        $this->createModalOpen = false;
        session()->flash('success', 'Usuario creado exitosamente.');
    }

    public function update(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingId);
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'regional_id' => $this->regional_id ?: null,
        ];

        if ($this->password) {
            $data['password'] = bcrypt($this->password);
        }

        $user->update($data);
        $user->syncRoles([$this->selectedRole]);

        $this->editModalOpen = false;
        session()->flash('success', 'Usuario actualizado exitosamente.');
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingId);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'No puedes eliminar tu propio usuario.');
            $this->deleteModalOpen = false;
            return;
        }

        $user->removeRoles();
        $user->delete();

        $this->deleteModalOpen = false;
        session()->flash('success', 'Usuario eliminado exitosamente.');
    }
}
?>

<div>
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Usuarios</h1>
        <button wire:click="openCreateModal" class="btn bg-violet-500 hover:bg-violet-600 text-white">
            <svg class="w-4 h-4 shrink-0 fill-current mr-2" viewBox="0 0 16 16"><path d="M15 7h-1V6a1 1 0 0 0-2 0v1H8V6a1 1 0 0 0-2 0v1H5V6a1 1 0 0 0-2 0v1H1a1 1 0 0 0-1 1v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zM8 3a1 1 0 0 0-1 1v3h3V4a1 1 0 0 0-1-1z"/></svg>
            Nuevo Usuario
        </button>
    </div>

    {{-- Búsqueda --}}
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="Buscar por nombre o email..." class="form-input w-full max-w-md" />
    </div>

    {{-- Tabla --}}
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700/60">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Regional</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @forelse ($this->usuarios() as $user)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->regional->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($user->roles->count())
                                    @foreach ($user->roles as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-xs italic">Sin rol</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="openEditModal({{ $user->id }})" class="btn-sm border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M11.886 3.461h.001c.453 0 .828.368.828.828 0 .226-.091.432-.239.582l-1.224 1.224 2.804 2.804 1.224-1.224c.325-.325.779-.411 1.191-.239.412.172.687.588.687 1.044 0 .226-.091.432-.239.582L12.2 15.26c-.325.325-.779.411-1.191.239-.412-.172-.687-.588-.687-1.044v-.117L3.393 12.49c-.325.325-.779.411-1.191.239-.412-.172-.687-.588-.687-1.044 0-.226.091-.432.239-.582l1.383-1.383-2.804-2.804-1.383 1.383c-.148.148-.354.239-.582.239h-.117c-.456 0-.872-.275-1.044-.687-.172-.412-.086-.866.239-1.191L5.09 3.617c.148-.148.354-.239.582-.239h.001c.226 0 .432.091.582.239L7.479 4.85l4.407-4.407c.148-.148.354-.239.582-.239z"/></svg>
                                        Editar
                                    </button>
                                    <button wire:click="openDeleteModal({{ $user->id }}, '{{ $user->name }}')" class="btn-sm border border-gray-200 dark:border-gray-600 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500">
                                        <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M5.936 0a1.49 1.49 0 0 0-1.294.749L4.388 2H2a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1h-2.388l-.254-1.252A1.49 1.49 0 0 0 10.064 0H5.936zM2.5 3l.576 1.441A.5.5 0 0 0 3.536 5h8.928a.5.5 0 0 0 .46-.559L13.5 3h-11z"/><path d="M14.5 6H1.5a.5.5 0 0 0-.5.5v8a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5v-8a.5.5 0 0 0-.5-.5zM6 10.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zm3 0a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zm3 0a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3z"/></svg>
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay usuarios registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
            {{ $this->usuarios()->links() }}
        </div>
    </div>

    {{-- Modal Crear Usuario --}}
    @if ($createModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('createModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('createModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Crear Usuario</h3>
                        <button wire:click="$set('createModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit="save">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="name" class="form-input w-full" required />
                                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" wire:model="email" class="form-input w-full" required />
                                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña <span class="text-red-500">*</span></label>
                                <input type="password" wire:model="password" class="form-input w-full" placeholder="Mínimo 8 caracteres" required />
                                @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol <span class="text-red-500">*</span></label>
                                <select wire:model="selectedRole" class="form-select w-full" required>
                                    <option value="">Seleccione un rol</option>
                                    @foreach ($this->allRoles() as $role)
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedRole') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regional</label>
                                <select wire:model="regional_id" class="form-select w-full">
                                    <option value="">Sin regional</option>
                                    @foreach ($this->regionals() as $regional)
                                        <option value="{{ $regional->id }}">{{ $regional->name }}</option>
                                    @endforeach
                                </select>
                                @error('regional_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('createModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                            <button type="submit" class="btn bg-violet-500 hover:bg-violet-600 text-white">Crear Usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Editar Usuario --}}
    @if ($editModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('editModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('editModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Editar Usuario</h3>
                        <button wire:click="$set('editModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit="update">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="name" class="form-input w-full" required />
                                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" wire:model="email" class="form-input w-full" required />
                                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña</label>
                                <input type="password" wire:model="password" class="form-input w-full" placeholder="Dejar vacío para no cambiar" />
                                @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol <span class="text-red-500">*</span></label>
                                <select wire:model="selectedRole" class="form-select w-full" required>
                                    <option value="">Seleccione un rol</option>
                                    @foreach ($this->allRoles() as $role)
                                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                @error('selectedRole') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Regional</label>
                                <select wire:model="regional_id" class="form-select w-full">
                                    <option value="">Sin regional</option>
                                    @foreach ($this->regionals() as $regional)
                                        <option value="{{ $regional->id }}">{{ $regional->name }}</option>
                                    @endforeach
                                </select>
                                @error('regional_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('editModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                            <button type="submit" class="btn bg-violet-500 hover:bg-violet-600 text-white">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Eliminar Usuario --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('deleteModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('deleteModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Eliminar Usuario</h3>
                        <button wire:click="$set('deleteModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        ¿Estás seguro de que deseas eliminar el usuario <strong class="text-gray-900 dark:text-white">{{ $deletingName }}</strong>? Esta acción no se puede deshacer.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button wire:click="$set('deleteModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                        <button wire:click="delete" class="btn bg-red-500 hover:bg-red-600 text-white">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
