-- =====================================================================
-- Migración: integración de la web de clientes con el panel
--
-- Se ejecuta UNA vez sobre la base que ya está en uso (barberia_db).
-- Una instalación nueva no la necesita: barberia_db.sql ya trae todo.
--
-- ANTES de ejecutarla, hacé una copia de seguridad:
--   Docker:  docker compose exec mysql mysqldump -uroot -proot barberia_db > respaldo_barberia_db.sql
--   XAMPP:   phpMyAdmin > barberia_db > Exportar
--
-- Cambios:
--   1. Tabla password_resets (recuperación de contraseña de la web), con
--      clave foránea a cliente: si se borra un cliente, se borran sus
--      tokens.
--   2. Ruta de la foto del barbero de ejemplo al formato del panel
--      ("img/barberos/archivo"). El archivo ya está copiado en
--      admin/img/barberos/barbero_antonio.jpg.
-- =====================================================================


-- 1. password_resets ---------------------------------------------------
-- Si la tabla todavía no existe (caso normal de la base del panel):

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `token_hash` (`token_hash`),
  KEY `fk_pr_cliente` (`id_cliente`),
  CONSTRAINT `fk_pr_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Si la tabla YA existía (base de la web de clientes, sin clave foránea),
-- el CREATE de arriba no hace nada. En ese caso ejecutá también estas
-- líneas (quitando los "-- "):
--
-- ALTER TABLE `password_resets` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- DELETE pr FROM `password_resets` pr LEFT JOIN `cliente` c ON c.id_cliente = pr.id_cliente WHERE c.id_cliente IS NULL;
-- ALTER TABLE `password_resets`
--   ADD KEY `fk_pr_cliente` (`id_cliente`),
--   ADD CONSTRAINT `fk_pr_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE;


-- 2. Foto del barbero de ejemplo ----------------------------------------

UPDATE `barbero`
SET `url_imagen` = 'img/barberos/barbero_antonio.jpg'
WHERE `url_imagen` = '../resources/images/barbero.jpg';

-- Comprobación: barberos cuya foto todavía no tiene el formato del panel.
-- Debería no devolver filas. Si aparece alguno, subile la foto de nuevo
-- desde el panel (Barberos > Editar).
SELECT `id_barbero`, `nombre`, `apellido`, `url_imagen`
FROM `barbero`
WHERE `url_imagen` IS NOT NULL AND `url_imagen` NOT LIKE 'img/barberos/%';
