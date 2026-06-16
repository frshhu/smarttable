<?php
namespace SmartTable\Models;

use SmartTable\Core\Database;
use PDO;

class Reservation {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($dateTime, $name, $phone, $email, $tableId) {
        $this->db->beginTransaction();

        try {
            // 1. Wstawienie rezerwacji
            $sql = "INSERT INTO reservations (date_time, status, customer_name, customer_phone, customer_email, assigned_table_id) 
                    VALUES (:date_time, 'Confirmed', :name, :phone, :email, :table_id) RETURNING reservation_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'date_time' => $dateTime,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'table_id' => $tableId
            ]);
            
            $reservationId = $stmt->fetchColumn();

            // 2. Automatyczne planowanie przypomnienia (OBJ-003) - np. 24 godziny przed wizytą
            $scheduledTime = date('Y-m-d H:i:s', strtotime($dateTime) - 86400);
            
            $sqlNotify = "INSERT INTO notifications (reservation_id, type, scheduled_time, status, message_content)
                          VALUES (:res_id, 'Email', :sched_time, 'Pending', :content)";
            
            $stmtNotify = $this->db->prepare($sqlNotify);
            $stmtNotify->execute([
                'res_id' => $reservationId,
                'sched_time' => $scheduledTime,
                'content' => "Witaj {$name}! Przypominamy o Twojej rezerwacji stolika na dzień {$dateTime}."
            ]);

            $this->db->commit();
            return $reservationId;

        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function cancel($id) {
        $sql = "UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function getById($id) {
        $sql = "SELECT * FROM reservations WHERE reservation_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}