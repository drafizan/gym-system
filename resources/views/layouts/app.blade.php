<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') - {{ config('app.name', 'GymSystem') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="app-shell">
            @include('partials.sidebar')

            <div class="app-main">
                @include('partials.header')

                <main class="app-content">
                    @include('partials.breadcrumb')
                    @include('partials.flash')
                    @include('partials.validation-errors')

                    @yield('content')
                </main>

                @include('partials.footer')
            </div>
        </div>

        <div class="sidebar-backdrop" data-sidebar-toggle></div>
    </body>
</html>
