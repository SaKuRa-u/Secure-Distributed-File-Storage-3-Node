<?php
require_once("connection.php");

class Category {
    protected $sqli;

    public function __construct() {
        $this->sqli = Database::get();
    }

    public function getCategories() {
        $sql  = "SELECT * FROM categories ORDER BY id ASC";
        $stmt = $this->sqli->prepare($sql);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function insertCategory($name) {
        $sql  = "INSERT INTO categories (category_name) VALUES (?)";
        $stmt = $this->sqli->prepare($sql);
        $stmt->bind_param("s", $name);
        return $stmt->execute();
    }
}
?>
