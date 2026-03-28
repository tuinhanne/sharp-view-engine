#extends('layouts.main')

#section('title')
    Welcome Home
#endsection

#section('content')
<h1>Welcome, {{ $user->name }}!</h1>

#if($posts)
    <ul>
        #foreach($posts as $post)
            <li>
                <h2>{{ $post->title }}</h2>
                <!-- Loop through tags -->
                #foreach($post->tags as $tag)
                    <span class="tag">{{ $tag }}</span>
                #endforeach
            </li>
        #endforeach
    </ul>
#else
    <p>No posts yet.</p>
#endif

<!-- Component example -->
<UserCard :user="$user" class="featured" />
<user-card :user="$user" class="featured" />
#endsection
