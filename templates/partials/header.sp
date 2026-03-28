<header>
    <nav>
        <a href="/">Home</a>
        #if($user)
            <span>Hello, {{ $user->name }}!</span>
        #else
            <a href="/login">Login</a>
        #endif
    </nav>
</header>
