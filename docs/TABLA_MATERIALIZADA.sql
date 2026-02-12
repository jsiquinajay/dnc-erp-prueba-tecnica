******* PROPUESTA DE TABLA MATERIALIZADA *******

--Almacenamiento de informacion de productos en tabla fisica
-- Script de tabla materializada
CREATE TABLE inventario (
    producto_id INT NOT NULL,
    bodega_id INT NOT NULL,
    existencia DECIMAL(10,2) NOT NULL DEFAULT 0,
    ultimo_costo DECIMAL(10,2) NOT NULL DEFAULT 0,
    fecha_actualizacion TIMESTAMP NOT NULL,
    PRIMARY KEY (producto_id, bodega_id),
    FOREIGN KEY (producto_id) REFERENCES productos(id),
    FOREIGN KEY (bodega_id) REFERENCES bodegas(id),
    INDEX idx_fecha_actualizacion (fecha_actualizacion DESC)
);

-- Procedimiento alamacenado para actualizar
DELIMITER $$

CREATE PROCEDURE actualizar_inventario()
BEGIN
    DECLARE last_update TIMESTAMP;
    
    -- Obtener última actualización
    SELECT MAX(fecha_actualizacion) INTO last_update 
    FROM inventario;
    
    -- Insertar/actualizar con cambios desde última actualización
    INSERT INTO inventario (
        producto_id, bodega_id, existencia, ultimo_costo, fecha_actualizacion
    )
    SELECT 
        p.id as producto_id,
        b.id as bodega_id,
        COALESCE(
            SUM(CASE WHEN k.tipo = 'entrada' THEN k.cantidad ELSE -k.cantidad END), 
            0
        ) as existencia,
        COALESCE(
            (SELECT k2.precio_unitario 
             FROM kardex k2 
             WHERE k2.producto_id = p.id 
               AND k2.tipo = 'entrada'
             ORDER BY k2.fecha DESC 
             LIMIT 1),
            0
        ) as ultimo_costo,
        NOW() as fecha_actualizacion
    FROM productos p
    CROSS JOIN bodegas b
    LEFT JOIN kardex k ON 
        k.producto_id = p.id AND 
        k.bodega_id = b.id AND
        (last_update IS NULL OR k.fecha > last_update)
    WHERE p.estado = 1 AND b.estado = 1
    GROUP BY p.id, b.id
    ON DUPLICATE KEY UPDATE
        existencia = VALUES(existencia),
        ultimo_costo = VALUES(ultimo_costo),
        fecha_actualizacion = NOW();
END$$

DELIMITER ;

-- Creacion de triggers para mantener actualizada (opcional)
CREATE TRIGGER after_kardex_insert
AFTER INSERT ON kardex
FOR EACH ROW
BEGIN
    CALL actualizar_inventario();
END;

CREATE TRIGGER after_kardex_update
AFTER UPDATE ON kardex
FOR EACH ROW
BEGIN
    CALL actualizar_inventario();
END;

-- Query para pata utilizar tabla materializada
SELECT 
    p.nombre as producto,
    b.nombre as bodega,
    i.existencia,
    i.ultimo_costo
FROM inventario i
INNER JOIN productos p ON ia.producto_id = p.id
INNER JOIN bodegas b ON i.bodega_id = b.id
WHERE p.estado = 1 AND b.estado = 1
ORDER BY p.nombre, b.nombre;