<?php
namespace SmartTable\Controllers;

use SmartTable\Models\TableModel;
use SmartTable\Models\Reservation;
use SmartTable\Core\Database;

class BookingController {
    
    /**
     * Wyświetla główną stronę z graficznym formularzem rezerwacji (US.U4)
     */
    public function index() {
        require __DIR__ . '/../Views/booking_form.php';
    }

    /**
     * Endpoint API (JSON) zwracający stan dostępności wszystkich stolików
     * Wykorzystywany przez JavaScript do budowania graficznego planu sali
     */
    public function checkAvailability() {
        header('Content-Type: application/json');
        
        $date = $_GET['date'] ?? '';
        $time = $_GET['time'] ?? '';
        
        if (empty($date) || empty($time)) {
            echo json_encode(['error' => 'Brak wymaganej daty lub godziny wizyty.']);
            exit;
        }

        $dateTime = "$date $time";
        $tableModel = new TableModel();
        
        // Pobranie stołów wraz z wyliczonym statusem zajętości (is_taken) z PostgreSQL
        $tables = $tableModel->getTablesWithAvailability($dateTime);

        echo json_encode($tables);
        exit;
    }

    /**
     * Przetwarza przesłany formularz rezerwacyjny (UC.1 / US.U6)
     */
    public function submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php');
            exit;
        }

        // Pobranie i oczyszczenie danych z tablicy POST [cite: 82]
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $partySize = intval($_POST['party_size'] ?? 0);
        $tableId = intval($_POST['selected_table_id'] ?? 0); // Wybrany graficznie stolik 
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        $dateTime = "$date $time";

        // 1. OCHRONA BIZNESOWA: Twarda blokada maksymalnej liczby gości na backendzie
        if ($partySize > 6) {
            $error = "Przepraszamy, system nie pozwala na automatyczną rezerwację dla więcej niż 6 osób. Prosimy o kontakt bezpośredni.";
            require __DIR__ . '/../Views/error.php';
            return;
        }

        // Podstawowa walidacja kompletności pól formularza
        if (empty($date) || empty($time) || $partySize <= 0 || $tableId <= 0 || empty($name) || empty($email)) {
            $error = "Wszystkie pola formularza oraz wskazanie stolika na planie sali są wymagane.";
            require __DIR__ . '/../Views/error.php';
            return;
        }

        // Pobranie danych o stoliku bezpośrednio z bazy w celu weryfikacji reguł marnowania miejsc 
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT table_number, capacity FROM tables WHERE table_id = :id");
        $stmt->execute(['id' => $tableId]);
        $tableData = $stmt->fetch();

        if ($tableData) {
            $capacity = intval($tableData['capacity']);
            
            // 2. OPTYMALIZACJA MIEJSC: Blokada 1 osoby rezerwującej stoliki większe niż 2-osobowe
            if ($partySize === 1 && $capacity > 2) {
                $error = "Błąd optymalizacji: 1 osoba może zarezerwować wyłącznie najmniejsze stoliki (2-osobowe).";
                require __DIR__ . '/../Views/error.php';
                return;
            }
            
            // 3. OPTYMALIZACJA MIEJSC: Blokada 2 osób przed rezerwacją dużych stolików 6-osobowych
            if ($partySize === 2 && $capacity > 4) {
                $error = "Błąd optymalizacji: 2 osoby nie mogą blokować dużego stolika 6-osobowego.";
                require __DIR__ . '/../Views/error.php';
                return;
            }
            
            // Weryfikacja, czy grupa fizycznie zmieści się przy wybranym stole
            if ($partySize > $capacity) {
                $error = "Wybrany stolik (Maks. miejsc: $capacity) jest za mały dla Twojej grupy ($partySize os.).";
                require __DIR__ . '/../Views/error.php';
                return;
            }
        } else {
            $error = "Wybrany stolik nie istnieje w konfiguracji restauracji.";
            require __DIR__ . '/../Views/error.php';
            return;
        }

        $tableModel = new TableModel();
        
        // 4. BEZPIECZEŃSTWO (NFR07): Ostateczna weryfikacja dostępności przed samym zapisem (ochrona przed wyścigiem żądań) [cite: 160]
        if (!$tableModel->isTableAvailable($tableId, $dateTime)) {
            $error = "Przepraszamy, ten stolik został właśnie zarezerwowany przez innego gościa. Prosimy wybrać inne miejsce.";
            require __DIR__ . '/../Views/error.php';
            return;
        }

        // 5. ZAPIS REZERWACJI: Przekazanie danych do modelu transakcyjnego (Status: Confirmed) [cite: 81]
        $reservationModel = new Reservation();
        $reservationId = $reservationModel->create($dateTime, $name, $phone, $email, $tableId);

        if ($reservationId) {
            // Wysłanie e-maila potwierdzającego za pomocą standardu PHPMailer (ADR005) [cite: 189]
            $this->sendConfirmationEmail($email, $name, $dateTime, $reservationId);

            // Przekierowanie do widoku podsumowania (Kryterium akceptacji 3) [cite: 141]
            header("Location: index.php?action=confirmed&id=" . $reservationId);
            exit;
        } else {
            $error = "Wystąpił nieoczekiwany błąd bazy danych podczas zapisu rezerwacji.";
            require __DIR__ . '/../Views/error.php';
        }
    }

    /**
     * Wyświetla ekran sukcesu po pomyślnym zarezerwowaniu stolika (US.U7) [cite: 142]
     */
    public function confirmed() {
        $id = intval($_GET['id'] ?? 0);
        $reservationModel = new Reservation();
        $booking = $reservationModel->getById($id);

        if (!$booking) {
            header('Location: index.php');
            exit;
        }

        require __DIR__ . '/../Views/confirmation.php';
    }

    /**
     * Obsługuje zdalne anulowanie rezerwacji przez klienta za pomocą dedykowanego linku (US.U8) [cite: 143]
     */
    public function cancel() {
        $id = intval($_GET['id'] ?? 0);
        $reservationModel = new Reservation();
        
        // Zmiana statusu rezerwacji w PostgreSQL na 'Cancelled' [cite: 81, 146]
        if ($reservationModel->cancel($id)) {
            $message = "Twoja rezerwacja została pomyślnie anulowana. Stolik jest ponownie dostępny dla innych gości.";
            require __DIR__ . '/../Views/confirmation.php';
        } else {
            $error = "Wystąpił problem. Nie udało się anulować wskazanej rezerwacji.";
            require __DIR__ . '/../Views/error.php';
        }
    }

    /**
     * Makieta wysyłki e-mail za pomocą PHPMailer (ADR005) [cite: 189]
     */
    private function sendConfirmationEmail($to, $name, $dateTime, $resId) {
        /*
        // Przykład poprawnej implementacji PHPMailer:
        $mail = new \PHPMailer\PHPMailer\PHPMailer();
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'twoj_student_email@edu.pl';
        $mail->Password = 'haslo';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom('no-reply@smarttable.edu.pl', 'SmartTable Reservation');
        $mail->addAddress($to, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Potwierdzenie rezerwacji stolika - SmartTable';
        $mail->Body    = "Dzień dobry $name, <br><br> Twoja rezerwacja na termin <strong>$dateTime</strong> została pomyślnie zapisana.<br>
                          Jeśli Twoje plany ulegną zmianie, możesz anulować wizytę klikając w poniższy link:<br>
                          <a href='http://localhost:8888/smarttable/public/index.php?action=cancel&id=$resId'>Anuluj rezerwację</a>";
        $mail->send();
        */
        return true; 
    }
}