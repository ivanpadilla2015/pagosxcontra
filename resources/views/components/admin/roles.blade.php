<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

new class extends Component
{
    use WithPagination;

    #[Url]
    public int $page = 1;

    public string $activeTab = 'roles';

    public bool $createModalOpen = false;
    public bool $editModalOpen = false;
    public bool $deleteModalOpen = false;

    public string $name = '';
    public ?int $editingRoleId = null;
    public ?int $deletingRoleId = null;
    public string $deletingRoleName = '';

    public array $selectedPermissions = [];

    public bool $roleModalOpen = false;
    public ?int $editingUserId = null;
    public string $editingUserName = '';
    public string $selectedRole = '';

    public function getAllPermissions()
    {
        return Permission::orderBy('name')->get();
    }

    public function getRoles()
    {
        return Role::with('permissions')->where('guard_name', 'web')->orderBy('name')->paginate(10);
    }

    public function getUsers()
    {
        return User::with('roles')->orderBy('name')->paginate(10);
    }

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->name = '';
        $this->selectedPermissions = [];
        $this->createModalOpen = true;
    }

    public function openEditModal(int $roleId)
    {
        $this->resetValidation();
        $role = Role::findOrFail($roleId);
        $this->editingRoleId = $roleId;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->editModalOpen = true;
    }

    public function openDeleteModal(int $roleId, string $roleName)
    {
        $this->deletingRoleId = $roleId;
        $this->deletingRoleName = $roleName;
        $this->deleteModalOpen = true;
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = Role::create(['name' => $this->name, 'guard_name' => 'web']);
        $role->syncPermissions($this->selectedPermissions);

        $this->createModalOpen = false;
        session()->flash('success', 'Rol creado exitosamente.');
    }

    public function update()
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $role = Role::findOrFail($this->editingRoleId);
        $role->update(['name' => $this->name]);
        $role->syncPermissions($this->selectedPermissions);

        $this->editModalOpen = false;
        session()->flash('success', 'Rol actualizado exitosamente.');
    }

    public function delete()
    {
        $role = Role::findOrFail($this->deletingRoleId);
        $role->delete();

        $this->deleteModalOpen = false;
        session()->flash('success', 'Rol eliminado exitosamente.');
    }

    public function openRoleModal(int $userId, string $userName, ?string $currentRole)
    {
        $this->editingUserId = $userId;
        $this->editingUserName = $userName;
        $this->selectedRole = $currentRole ?? '';
        $this->roleModalOpen = true;
    }

    public function saveUserRole()
    {
        $user = User::findOrFail($this->editingUserId);
        $user->syncRoles($this->selectedRole ? [$this->selectedRole] : []);

        $this->roleModalOpen = false;
        session()->flash('success', 'Rol asignado a ' . $this->editingUserName . ' exitosamente.');
    }

    public function getAllRoles()
    {
        return Role::where('guard_name', 'web')->orderBy('name')->get();
    }
}
?>

<div>
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Administración</h1>
        @if ($activeTab === 'roles')
            <button wire:click="openCreateModal" class="btn bg-violet-500 hover:bg-violet-600 text-white">
                <svg class="w-4 h-4 shrink-0 fill-current mr-2" viewBox="0 0 16 16"><path d="M15 7h-1V6a1 1 0 0 0-2 0v1H8V6a1 1 0 0 0-2 0v1H5V6a1 1 0 0 0-2 0v1H1a1 1 0 0 0-1 1v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zM8 3a1 1 0 0 0-1 1v3h3V4a1 1 0 0 0-1-1z"/></svg>
                Nuevo Rol
            </button>
        @endif
    </div>

    <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button wire:click="$set('activeTab', 'roles')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'roles' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Roles y Permisos
        </button>
        <button wire:click="$set('activeTab', 'usuarios')" class="px-4 py-2 text-sm font-medium border-b-2 transition {{ $activeTab === 'usuarios' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
            Asignar Roles a Usuarios
        </button>
    </div>

    @if ($activeTab === 'roles')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3">Rol</th>
                            <th class="px-4 py-3">Permisos</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse ($this->getRoles() as $role)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $role->name }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($role->permissions as $permission)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                                {{ $permission->name }}
                                            </span>
                                        @empty
                                            <span class="text-gray-400 dark:text-gray-500 text-xs italic">Sin permisos</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button wire:click="openEditModal({{ $role->id }})" class="btn-sm border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">
                                            <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M11.886 3.461h.001c.453 0 .828.368.828.828 0 .226-.091.432-.239.582l-1.224 1.224 2.804 2.804 1.224-1.224c.325-.325.779-.411 1.191-.239.412.172.687.588.687 1.044 0 .226-.091.432-.239.582L12.2 15.26c-.325.325-.779.411-1.191.239-.412-.172-.687-.588-.687-1.044v-.117L3.393 12.49c-.325.325-.779.411-1.191.239-.412-.172-.687-.588-.687-1.044 0-.226.091-.432.239-.582l1.383-1.383-2.804-2.804-1.383 1.383c-.148.148-.354.239-.582.239h-.117c-.456 0-.872-.275-1.044-.687-.172-.412-.086-.866.239-1.191L5.09 3.617c.148-.148.354-.239.582-.239h.001c.226 0 .432.091.582.239L7.479 4.85l4.407-4.407c.148-.148.354-.239.582-.239z"/></svg>
                                            Editar
                                        </button>
                                        <button wire:click="openDeleteModal({{ $role->id }}, '{{ $role->name }}')" class="btn-sm border border-gray-200 dark:border-gray-600 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500">
                                            <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 16 16"><path d="M5.936 0a1.49 1.49 0 0 0-1.294.749L4.388 2H2a1 1 0 0 0-1 1v1a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1h-2.388l-.254-1.252A1.49 1.49 0 0 0 10.064 0H5.936zM2.5 3l.576 1.441A.5.5 0 0 0 3.536 5h8.928a.5.5 0 0 0 .46-.559L13.5 3h-11z"/><path d="M14.5 6H1.5a.5.5 0 0 0-.5.5v8a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5v-8a.5.5 0 0 0-.5-.5zM6 10.5a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zm3 0a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3zm3 0a.5.5 0 0 1 1 0v3a.5.5 0 0 1-1 0v-3z"/></svg>
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay roles registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                {{ $this->getRoles()->links() }}
            </div>
        </div>
    @endif

    @if ($activeTab === 'usuarios')
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-xl border border-gray-200 dark:border-gray-700/60">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700/60">
                        <tr>
                            <th class="px-4 py-3">Usuario</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Rol Actual</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse ($this->getUsers() as $user)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->roles->count())
                                        @foreach ($user->roles as $role)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs italic">Sin rol asignado</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button wire:click="openRoleModal({{ $user->id }}, '{{ $user->name }}', '{{ $user->roles->first()?->name ?? '' }}')" class="btn-sm border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 shrink-0 fill-current mr-1" viewBox="0 0 16 16"><path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/><path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.116l.094-.319z"/></svg>
                                        Asignar Rol
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No hay usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                {{ $this->getUsers()->links() }}
            </div>
        </div>
    @endif

    {{-- Modal Crear Rol --}}
    @if ($createModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('createModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('createModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Crear Rol</h3>
                        <button wire:click="$set('createModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit="save">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del Rol <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" class="form-input w-full" placeholder="Ej: Supervisor" required />
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Permisos</label>
                            <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                                @foreach ($this->getAllPermissions() as $permission)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-gray-300 dark:border-gray-600 text-violet-500 focus:ring-violet-500" />
                                        {{ $permission->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('createModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                            <button type="submit" class="btn bg-violet-500 hover:bg-violet-600 text-white">Crear Rol</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Editar Rol --}}
    @if ($editModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('editModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('editModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-lg p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Editar Rol</h3>
                        <button wire:click="$set('editModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form wire:submit="update">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del Rol <span class="text-red-500">*</span></label>
                            <input type="text" wire:model="name" class="form-input w-full" required />
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Permisos</label>
                            <div class="grid grid-cols-2 gap-2 max-h-60 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg p-3">
                                @foreach ($this->getAllPermissions() as $permission)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-gray-300 dark:border-gray-600 text-violet-500 focus:ring-violet-500" />
                                        {{ $permission->name }}
                                    </label>
                                @endforeach
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

    {{-- Modal Eliminar Rol --}}
    @if ($deleteModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('deleteModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('deleteModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Eliminar Rol</h3>
                        <button wire:click="$set('deleteModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        ¿Estás seguro de que deseas eliminar el rol <strong class="text-gray-900 dark:text-white">{{ $deletingRoleName }}</strong>? Esta acción no se puede deshacer.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button wire:click="$set('deleteModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                        <button wire:click="delete" class="btn bg-red-500 hover:bg-red-600 text-white">Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Asignar Rol a Usuario --}}
    @if ($roleModalOpen)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('roleModalOpen', false)">
            <div class="fixed inset-0 bg-gray-900/50 dark:bg-black/60" x-on:click="$wire.set('roleModalOpen', false)"></div>
            <div class="relative min-h-screen flex items-center justify-center p-4">
                <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md p-6" x-on:click.stop>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Asignar Rol</h3>
                        <button wire:click="$set('roleModalOpen', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Asignando rol a: <strong class="text-gray-900 dark:text-white">{{ $editingUserName }}</strong></p>
                    <form wire:submit="saveUserRole">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rol</label>
                            <select wire:model="selectedRole" class="form-select w-full">
                                <option value="">-- Sin rol --</option>
                                @foreach ($this->getAllRoles() as $role)
                                    <option value="{{ $role->name }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" wire:click="$set('roleModalOpen', false)" class="btn border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">Cancelar</button>
                            <button type="submit" class="btn bg-violet-500 hover:bg-violet-600 text-white">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
