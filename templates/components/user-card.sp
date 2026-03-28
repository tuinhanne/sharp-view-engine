#props([
    user: App/Models/User, 
    class: ?string
])

<div class="user-card {{ $class ?? '' }}">
    <h3>{{ $user->name }}</h3>
    <p>{{ $user->email }}</p>
    
    #if(isset($slot) && $slot)
        <div class="slot-content">
            {{ $slot }}
        </div>
    #endif
</div>
