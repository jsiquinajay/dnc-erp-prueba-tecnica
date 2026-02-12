******* PROCESO DE MIGRACION ******
-- Identificar transformaciones erróneas
-- Creacion de tabla temporal para almacenader registros erroneos
CREATE TEMPORARY TABLE temp_transformaciones_erroneas AS
SELECT 
    k1.id as entrada_id,
    k2.id as salida_id,
    k1.producto_id as producto_entrada_id,
    k2.producto_id as producto_salida_id,
    k1.cantidad as cantidad_entrada,
    k2.cantidad as cantidad_salida_actual,
    k1.fecha,
    k1.bodega_id,
    k1.usuario_id,
    CASE 
        WHEN k1.producto_id = 45 AND k2.producto_id = 67 THEN 'cereza_pergamino'
        WHEN k1.producto_id = 67 AND k2.producto_id = 89 THEN 'pergamino_oro'
        ELSE 'otro'
    END as tipo_transformacion
FROM kardex k1
INNER JOIN kardex k2 ON 
    k1.bodega_id = k2.bodega_id AND
    k1.usuario_id = k2.usuario_id AND
    ABS(TIMESTAMPDIFF(SECOND, k1.fecha, k2.fecha)) < 5 AND
    k1.tipo = 'entrada' AND 
    k2.tipo = 'salida' AND
    k1.cantidad = k2.cantidad
WHERE k1.cantidad > 0;

--  Creacion de tabla de rendimientos estándard
CREATE TEMPORARY TABLE temp_rendimientos_estandard(
    producto_entrada_id INT,
    producto_salida_id INT,
    rendimiento DECIMAL(5,4),
    PRIMARY KEY (producto_entrada_id, producto_salida_id)
);
-- Valores de ejemplo
INSERT INTO temp_rendimientos_estandar VALUES
(45, 67, 0.85), -- Cereza --> Pergamino
(67, 89, 0.80); -- Pergamino --> Oro

-- Correccion de as cantidades en kardex (actualizacion)
UPDATE kardex k
INNER JOIN temp_transformaciones_erroneas t ON k.id = t.salida_id
INNER JOIN temp_rendimientos_estandar r ON 
    t.producto_entrada_id = r.producto_entrada_id AND
    t.producto_salida_id = r.producto_salida_id
SET 
    k.cantidad = t.cantidad_entrada * r.rendimiento,
    k.factor_conversion = r.rendimiento,
    k.tipo_movimiento = 'transformacion_salida',
    k.updated_at = NOW()
WHERE k.tipo = 'salida';

-- 4. Crear registros de merma faltantes
INSERT INTO transformacion (
    producto_entrada_id, producto_salida_id,
    cantidad_entrada, cantidad_salida, merma,
    rendimiento, costo_transformacion, bodega_id, usuario_id,
    referencia, fecha, created_at
)
SELECT 
    t.producto_entrada_id,
    t.producto_salida_id,
    t.cantidad_entrada,
    t.cantidad_entrada * r.rendimiento as cantidad_salida_corregida,
    t.cantidad_entrada * (1 - r.rendimiento) as merma_calculada,
    r.rendimiento,
    t.bodega_id,
    t.usuario_id,
    CONCAT('CORRECCION_', t.entrada_id) as referencia,
    t.fecha,
    NOW()
FROM temp_transformaciones_erroneas t
INNER JOIN temp_rendimientos_estandar r ON 
    t.producto_entrada_id = r.producto_entrada_id AND
    t.producto_salida_id = r.producto_salida_id;

-- Creacion de registros de transformaciones históricas
INSERT INTO transformaciones (
    producto_entrada_id, producto_salida_id,
    cantidad_entrada, cantidad_salida, merma,
    rendimiento, bodega_id, usuario_id,
    referencia, fecha_procesamiento, estado,
    created_at, updated_at
)
SELECT 
    t.producto_entrada_id,
    t.producto_salida_id,
    t.cantidad_entrada,
    t.cantidad_entrada * r.rendimiento as cantidad_salida,
    t.cantidad_entrada * (1 - r.rendimiento) as merma,
    r.rendimiento,
    t.bodega_id,
    t.usuario_id,
    CONCAT('HISTORICO_', t.entrada_id) as referencia,
    t.fecha,
    'completado',
    NOW(),
    NOW()
FROM temp_transformaciones_erroneas t
INNER JOIN temp_rendimientos_estandar r ON 
    t.producto_entrada_id = r.producto_entrada_id AND
    t.producto_salida_id = r.producto_salida_id;

-- Verificacion de correcciones
SELECT 
    'Antes' as periodo,
    COUNT(*) as registros,
    SUM(cantidad_salida_actual) as total_salida
FROM temp_transformaciones_erroneas
UNION ALL
SELECT 
    'Después' as periodo,
    COUNT(*) as registros,
    SUM(cantidad_entrada * r.rendimiento) as total_salida_corregida
FROM temp_transformaciones_erroneas t
INNER JOIN temp_rendimientos_estandar r ON 
    t.producto_entrada_id = r.producto_entrada_id AND
    t.producto_salida_id = r.producto_salida_id;

-- 7. Limpiar tablas temporales
DROP TEMPORARY TABLE IF EXISTS temp_transformaciones_erroneas;
DROP TEMPORARY TABLE IF EXISTS temp_rendimientos_estandar;