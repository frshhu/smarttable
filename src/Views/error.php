<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Błąd Rezerwacji</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container text-center my-5">
        <div class="card p-5 shadow-sm d-inline-block border-danger" style="max-width: 500px;">
            <h2 class="text-danger">Nie można ukończyć operacji</h2>
            <p class="mt-3"><?= htmlspecialchars($error ?? 'Wystąpił nieznany błąd.'); ?></p>
            <hr>
            <a href="index.php" class="btn btn-secondary">Spróbuj ponownie</a>
        </div>
    </div>
</body>
</html>