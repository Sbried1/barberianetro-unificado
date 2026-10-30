-- =====================================================================
-- Migración: Configuración del panel de administración
--
-- Se ejecuta UNA vez sobre la base que ya está en uso. Es aditiva: crea
-- una tabla nueva y no modifica ni borra ninguna fila existente. Se puede
-- volver a ejecutar sin efectos (IF NOT EXISTS / INSERT IGNORE).
--
--   docker compose exec -T mysql mysql -uroot -proot --default-character-set=utf8mb4 barberia_db < migracion_panel_admin.sql
--
-- Por qué: el horizonte máximo de reservas del panel es configurable y
-- tiene que seguir vigente después de cerrar sesión o reiniciar Docker.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `configuracion` (
  `clave` varchar(50) NOT NULL,
  `valor` varchar(255) NOT NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `configuracion` (`clave`, `valor`) VALUES ('reserva_dias_admin', '60');

-- Comprobación (solo lectura): servicios con el mismo nombre, sin distinguir
-- mayúsculas ni espacios. Esta migración NO los modifica ni los elimina.
SELECT LOWER(TRIM(`nombre`)) AS nombre, COUNT(*) AS cantidad, GROUP_CONCAT(`id_servicio`) AS ids
FROM `servicio`
GROUP BY LOWER(TRIM(`nombre`))
HAVING COUNT(*) > 1;
