<!DOCTYPE html>
<html lang="hr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
        <div id="app" class="h-full"></div>
    </body>
</html>
