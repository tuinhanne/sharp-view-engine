<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>#yield('title', 'Sharp App')</title>
</head>
<body>
    <!-- Header partial -->
    #include('partials.header')

    <main>
        #yield('content')
    </main>

    <footer>
        #yield('footer', '<p>&copy; 2026</p>')
    </footer>
</body>
</html>
