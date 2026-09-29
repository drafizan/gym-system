<label class="field">
    <span>Name</span>
    <input type="text" name="name" value="{{ old('name', $managedUser?->name) }}" required>
</label>

<label class="field">
    <span>Username</span>
    <input type="text" name="username" value="{{ old('username', $managedUser?->username) }}" required>
</label>

<label class="field">
    <span>Email</span>
    <input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" required>
</label>

<label class="field">
    <span>Role</span>
    <select name="role_id" required>
        <option value="">Select role</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected((int) old('role_id', $managedUser?->role_id) === $role->id)>
                {{ $role->label }}
            </option>
        @endforeach
    </select>
</label>

<label class="field">
    <span>Password</span>
    <input type="password" name="password" autocomplete="new-password" {{ $managedUser ? '' : 'required' }}>
</label>

<label class="field">
    <span>Confirm Password</span>
    <input type="password" name="password_confirmation" autocomplete="new-password" {{ $managedUser ? '' : 'required' }}>
</label>
