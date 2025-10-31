<?php
/* Archivo: /app/models/CuentaModel.php — REEMPLAZA TODO */
require_once __DIR__ . '/../../config/db.php';

class CuentaModel {
    private static function pdo(): PDO { return get_pdo(); }

    public static function byStreaming(int $streaming_id): array {
        $sql = "SELECT *
                FROM cuentas
                WHERE streaming_id = ?
                ORDER BY created_at DESC, id DESC";
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([$streaming_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(int $id): ?array {
        $stmt = self::pdo()->prepare("SELECT * FROM cuentas WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $d): bool {
        $sql = "INSERT INTO cuentas
                (streaming_id, correo, password_plain, fecha_inicio, fecha_fin,
                 whatsapp, cuenta, soles, estado, dispositivo, created_at)
                VALUES
                (:streaming_id, :correo, :password_plain, :fecha_inicio, :fecha_fin,
                 :whatsapp, :cuenta, :soles, :estado, :dispositivo, NOW())";
        $stmt = self::pdo()->prepare($sql);
        return $stmt->execute([
            ':streaming_id'   => $d['streaming_id'],
            ':correo'         => $d['correo'],
            ':password_plain' => $d['password_plain'],
            ':fecha_inicio'   => $d['fecha_inicio'], // 'Y-m-d'
            ':fecha_fin'      => $d['fecha_fin'],    // 'Y-m-d'
            ':whatsapp'       => $d['whatsapp'] ?? null,
            ':cuenta'         => $d['cuenta'] ?? null,
            ':soles'          => $d['soles'],
            ':estado'         => $d['estado'],
            ':dispositivo'    => $d['dispositivo'],
        ]);
    }

    public static function update(int $id, array $d): bool {
        $sql = "UPDATE cuentas
                SET correo = :correo,
                    password_plain = :password_plain,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    whatsapp = :whatsapp,
                    cuenta = :cuenta,
                    soles = :soles,
                    estado = :estado,
                    dispositivo = :dispositivo
                WHERE id = :id";
        $stmt = self::pdo()->prepare($sql);
        return $stmt->execute([
            ':correo'         => $d['correo'],
            ':password_plain' => $d['password_plain'],
            ':fecha_inicio'   => $d['fecha_inicio'],
            ':fecha_fin'      => $d['fecha_fin'],
            ':whatsapp'       => $d['whatsapp'] ?? null,
            ':cuenta'         => $d['cuenta'] ?? null,
            ':soles'          => $d['soles'],
            ':estado'         => $d['estado'],
            ':dispositivo'    => $d['dispositivo'],
            ':id'             => $id,
        ]);
    }

    public static function delete(int $id): bool {
        $stmt = self::pdo()->prepare("DELETE FROM cuentas WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
