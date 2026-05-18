<?php
class DB {
    private static ?mysqli $conn = null;

    public static function connect(): mysqli {
        if (self::$conn !== null && self::$conn->ping()) return self::$conn;
        self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if (self::$conn->connect_error) throw new RuntimeException('Database connection failed.');
        self::$conn->set_charset('utf8mb4');
        return self::$conn;
    }

    public static function query(string $sql, string $types='', array $params=[]): mysqli_stmt {
        $db = self::connect();
        $stmt = $db->prepare($sql);
        if (!$stmt) throw new RuntimeException('Query prepare failed: '.$db->error);
        if ($types && $params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt;
    }

    public static function rows(string $sql, string $types='', array $params=[]): array {
        $stmt = self::query($sql,$types,$params);
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $stmt->close();
        return $rows;
    }

    public static function row(string $sql, string $types='', array $params=[]): ?array {
        $stmt = self::query($sql,$types,$params);
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public static function value(string $sql, string $types='', array $params=[]): mixed {
        $row = self::row($sql,$types,$params);
        return $row ? array_values($row)[0] : null;
    }

    public static function execute(string $sql, string $types='', array $params=[]): array {
        $db = self::connect();
        $stmt = self::query($sql,$types,$params);
        $r = ['affected_rows'=>$stmt->affected_rows,'insert_id'=>$db->insert_id];
        $stmt->close();
        return $r;
    }

    public static function setting(string $key, mixed $default=null): mixed {
        static $cache = [];
        if (!isset($cache[$key])) {
            try { $cache[$key] = self::value("SELECT setting_value FROM settings WHERE setting_key=?",'s',[$key]); }
            catch(Exception $e) { return $default; }
        }
        return $cache[$key] ?? $default;
    }

    public static function setSetting(string $key, mixed $value): void {
        self::execute("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",'ss',[$key,$value]);
    }

    public static function lastInsertId(): int { return (int)self::connect()->insert_id; }
}
