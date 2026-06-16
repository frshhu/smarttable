<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Potwierdzenie Rezerwacji</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container text-center my-5">
        <div class="card p-5 shadow-sm d-inline-block" style="max-width: 500px;">
            <?php if (isset($message)): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
                <a href="index.php" class="btn btn-primary">Powrót do strony głównej</a>
            <?php else: ?>
                <h2 class="text-success">Rezerwacja powiodła się!</h2>
                <p class="lead mt-3">Dziękujemy, <strong><?= htmlspecialchars($booking['customer_name']) ?></strong>.</p>
                <p>Twój stolik został zablokowany na termin: <br><strong><?= htmlspecialchars($booking['date_time']) ?></strong>.</p>
                <p class="text-muted small">Wysłaliśmy wiadomość e-mail z potwierdzeniem oraz linkiem do rezerwacji.</p>
                <hr>
                <a href="index.php?action=cancel&id=<?= $booking['reservation_id']; ?>" class="btn btn-outline-danger btn-sm">Anuluj tę rezerwację</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>