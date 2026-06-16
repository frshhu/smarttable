<?php
// 1. Włączenie raportowania błędów (bardzo przydatne w środowisku deweloperskim MAMP)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Uruchomienie bezpiecznych, natywnych sesji serwerowych PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Automatyczny rejestrator klas (Autoloader zgodny ze strukturą katalogów src/)
spl_autoload_register(function ($class) {
    $prefix = 'SmartTable\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

use SmartTable\Controllers\BookingController;

// 4. Odczytanie akcji z parametru URL (np. index.php?action=submit)
$action = $_GET['action'] ?? 'index';
$controller = new BookingController();

// 5. Główny router aplikacji MVC (sterowanie ruchem i akcjami systemu)
switch ($action) {
    case 'check-availability':
        $controller->checkAvailability();
        break;
    case 'submit':
        $controller->submit();
        break;
    case 'confirmed':
        $controller->confirmed();
        break;
    case 'cancel':
        $controller->cancel();
        break;
    case 'index':
    default:
        $controller->index();
        break;
}