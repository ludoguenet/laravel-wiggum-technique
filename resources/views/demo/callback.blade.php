<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Demo callback</title>
    </head>
    <body style="font-family: ui-monospace, monospace; background: #0f172a; color: #e2e8f0; padding: 3rem;">
        <h1 style="color: #f87171;">Demo callback reached.</h1>
        <p>This harmless endpoint only writes the request to the Laravel log (see <code>php artisan pail</code>).</p>
        <ul>
            @foreach ($query as $key => $value)
                <li>{{ $key }}={{ is_array($value) ? json_encode($value) : $value }}</li>
            @endforeach
        </ul>
    </body>
</html>
