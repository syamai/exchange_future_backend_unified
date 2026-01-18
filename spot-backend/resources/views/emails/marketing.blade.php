@if (isset($userLocale))
    <html lang="{{ $userLocale }}">
    @else
        <html lang="{{ app()->getLocale() }}">
        @endif

        <head>
            <link href="https://fonts.googleapis.com/css?family=Inter" rel="stylesheet"/>
            <style>
                p {
                    margin: 0;
                }
            </style>
        </head>

        <body>
        {!! $content !!}

        </body>

