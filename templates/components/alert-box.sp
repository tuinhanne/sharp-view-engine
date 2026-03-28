#props([
    message: string,
    type: ?string,
    dismissible: ?bool
])

<div class="alert alert-{{ $type ?? 'info' }}">
    {{ $message }}

    #if($dismissible ?? false)
        <button class="dismiss">&times;</button>
    #endif

    #if(isset($slot) && $slot)
        <div class="alert-detail">
            {{ $slot }}
        </div>
    #endif
</div>
