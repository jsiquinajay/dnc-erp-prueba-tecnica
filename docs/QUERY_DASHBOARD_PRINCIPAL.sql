******* QUERY PARA DASHBOARD PRINCIPAL *******

-- Priner conjunto creado temporal
WITH ResumenKardex AS (
    -- Se agrupa todo el inventario
    SELECT 
        producto_id, 
        bodega_id,
        SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END) as existencia
    FROM kardex
    GROUP BY producto_id, bodega_id
),
-- Segundo conjunto creado temporal
UltimosCostos AS (
    -- Se obtiene el último costo calculando sobre filas relacionadas
    SELECT 
        producto_id,
        precio_unitario,
        ROW_NUMBER() OVER (PARTITION BY producto_id ORDER BY fecha DESC, id DESC) as ult_costo
    FROM kardex
    WHERE tipo = 'entrada'
)
SELECT 
    p.nombre as producto,
    b.nombre as bodega,
    COALESCE(rk.existencia, 0) as existencia,
    uc.precio_unitario as ultimo_costo
FROM productos p
CROSS JOIN bodegas b -- Mantiene la matriz completa de productos/bodegas
LEFT JOIN ResumenKardex rk 
    ON p.id = rk.producto_id AND b.id = rk.bodega_id
LEFT JOIN UltimosCostos uc 
    ON p.id = uc.producto_id AND uc.ult_costo = 1
WHERE p.estado = 1 AND b.estado = 1
ORDER BY p.nombre, b.nombre;