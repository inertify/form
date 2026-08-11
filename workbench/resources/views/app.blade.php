<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'Inertify Form Workbench') }}</title>
        @vite(['workbench/resources/css/app.css', 'workbench/resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="bg-background text-foreground antialiased">
        @inertia
    </body>
</html>
