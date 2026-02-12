-- para optimizar el la consulta del precio  
CREATE INDEX idx_Kardex_ProductoFecha ON kardex (producto_id, fecha DESC);  

CREATE INDEX idx_kardex_producto_bodega 
ON kardex(producto_id, bodega_id, tipo);

CREATE INDEX idx_kardex_producto_fecha 
ON kardex(producto_id, tipo, fecha);

CREATE INDEX idx_productos_estado 
ON productos(estado);

CREATE INDEX idx_bodegas_estado 
ON bodegas(estado);

-- Índices esenciales para el query
CREATE INDEX idx_kardex_producto_bodega_tipo 
ON kardex(producto_id, bodega_id, tipo);

CREATE INDEX idx_kardex_producto_tipo_fecha 
ON kardex(producto_id, tipo, fecha DESC);

CREATE INDEX idx_kardex_bodega_tipo 
ON kardex(bodega_id, tipo);

-- Índices para las tablas base
CREATE INDEX idx_productos_estado 
ON productos(estado, id, nombre);

CREATE INDEX idx_bodegas_estado 
ON bodegas(estado, id, nombre);

-- Índice compuesto para el cálculo de existencias
CREATE INDEX idx_kardex_existencias 
ON kardex(producto_id, bodega_id, tipo, cantidad);

-- Para consultas históricas
CREATE INDEX idx_kardex_fecha 
ON kardex(fecha DESC);