<?php
namespace SmartTable\Models;

use SmartTable\Core\Database;
use PDO;

class TableModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Pobiera wszystkie stoły wraz z ich statusem dostępności na konkretną godzinę.
     */
    public function getTablesWithAvailability($dateTime) {
        $duration = "2 hours"; // Czas trwania rezerwacji
        
        $sql = "SELECT t.table_id, t.table_number, t.capacity, t.current_status,
                       CASE WHEN EXISTS (
                           SELECT 1 
                           FROM reservations r 
                           WHERE r.assigned_table_id = t.table_id
                           AND r.status IN ('Pending', 'Confirmed', 'Seated')
                           AND r.date_time < CAST(:dateTime AS TIMESTAMP) + CAST(:duration AS INTERVAL)
                           AND r.date_time + CAST(:duration AS INTERVAL) > CAST(:dateTime AS TIMESTAMP)
                       ) THEN true ELSE false END as is_taken
                FROM tables t
                WHERE t.current_status != 'Out of Order'
                ORDER BY t.table_number ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'dateTime' => $dateTime,
            'duration' => $duration
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Sprawdza, czy konkretny, wybrany przez użytkownika stół jest wolny (zabezpieczenie NFR07)
     */
    public function isTableAvailable($tableId, $dateTime) {
        $duration = "2 hours";
        
        $sql = "SELECT 1 FROM reservations r 
                WHERE r.assigned_table_id = :table_id
                AND r.status IN ('Pending', 'Confirmed', 'Seated')
                AND r.date_time < CAST(:dateTime AS TIMESTAMP) + CAST(:duration AS INTERVAL)
                AND r.date_time + CAST(:duration AS INTERVAL) > CAST(:dateTime AS TIMESTAMP)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'table_id' => $tableId,
            'dateTime' => $dateTime,
            'duration' => $duration
        ]);
        
        return $stmt->fetchColumn() === false; // Zwraca true, jeśli nie ma konfliktów
    }
}