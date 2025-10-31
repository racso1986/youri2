<?php
require_once __DIR__ . '/../../config/db.php';

class PerfilModel {
    public static function byStreaming(int $streaming_id) {
        $pdo = get_pdo();
        $sql = "SELECT *
                FROM perfiles
                WHERE streaming_id = ?
                ORDER BY
                  correo ASC,
                  CASE WHEN COALESCE(perfil,'') = '' THEN 0 ELSE 1 END ASC,
                  perfil ASC,
                  fecha_fin ASC,
                  id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$streaming_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function get(int $id) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare("SELECT * FROM perfiles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // app/models/PerfilModel.php
    public static function create(array $d) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO `perfiles`
            (`streaming_id`,`correo`,`password_plain`,`perfil`,`whatsapp`,`fecha_inicio`,`fecha_fin`,`soles`,`estado`,`dispositivo`,`plan`,`combo`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $d['streaming_id'],
            $d['correo'],
            $d['password_plain'],
            $d['perfil'],
            $d['whatsapp'],
            $d['fecha_inicio'],
            $d['fecha_fin'],
            $d['soles'],
            $d['estado'],
            $d['dispositivo'],
            $d['plan'],
            $d['combo'],
        ]);
        return $pdo->lastInsertId();
    }

    public static function update(int $id, array $d) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            "UPDATE `perfiles` SET
              `correo`=?,
              `password_plain`=?,
              `perfil`=?,
              `whatsapp`=?,
              `fecha_fin`=?,
              `soles`=?,
              `estado`=?,
              `dispositivo`=?,
              `plan`=?,
              `combo`=?
            WHERE `id`=?"
        );
        return $stmt->execute([
            $d['correo'],
            $d['password_plain'],
            $d['perfil'],
            $d['whatsapp'],
            $d['fecha_fin'],
            $d['soles'],
            $d['estado'],
            $d['dispositivo'],
            $d['plan'],
            $d['combo'],
            $id,
        ]);
    }

    public static function delete(int $id) {
        $pdo = get_pdo();
        $stmt = $pdo->prepare("DELETE FROM perfiles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>
