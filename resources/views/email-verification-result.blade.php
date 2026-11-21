<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f5f5f5; color: #222; }
        main { background: #fff; padding: 32px 24px; border-radius: 12px; max-width: 360px; margin: 16px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { font-size: 14px; line-height: 1.7; margin: 0; color: #555; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
    </main>
</body>
</html>
