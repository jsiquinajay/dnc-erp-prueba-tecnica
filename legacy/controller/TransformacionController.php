<?php
/**
 * CÓDIGO LEGACY CON BUG CRÍTICO
 * 
 * Este controlador procesa transformaciones de café y tiene un bug
 * que causa descuadres en inventarios. Fue reportado por el supervisor
 * de bodega hace 2 días y es crítico porque hay auditoría próxima.
 * 
 * REPORTE DEL BUG (del ticket):
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * TICKET #2841 - PRIORIDAD: CRÍTICA
 * FECHA: Hace 2 días (3:00 AM)
 * REPORTADO POR: Juan Pérez (Supervisor de Bodega)
 * 
 * PROBLEMA:
 * Los inventarios están descuadrados después de procesar transformaciones.
 * Cuando transformamos café cereza a pergamino, las cantidades no coinciden.
 * 
 * EJEMPLO CONCRETO:
 * - Entrada: 100 qq de café cereza (producto ID: 45)
 * - Proceso: Beneficio húmedo (rendimiento esperado: 85%)
 * - Salida esperada: 85 qq de café pergamino (producto ID: 67)
 * - PERO el sistema registra: 100 qq de pergamino
 * - Resultado: Sobran 15 qq que no existen físicamente
 * 
 * IMPACTO:
 * - Inventarios no coinciden con físico
 * - Costos mal calculados
 * - Cliente grande (ACME Coffee) amenaza con no renovar contrato
 * - Auditoría en 2 días
 * - Pérdidas estimadas: $15,000 USD en descuadres
 * 
 * URGENCIA: MÁXIMA
 * ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
 * 
 * OBJETIVO DE LA PRUEBA:
 * El candidato debe:
 * 1. Identificar el bug REAL (no es el obvio)
 * 2. Explicar POR QUÉ causa los 15 qq de diferencia
 * 3. Proponer solución con merma y transacciones
 * 4. Crear SQL para corregir registros pasados
 */

class TransformacionController extends ControladorBase {
    
    public $conectar;
    public $Adapter;
    public $AdapterModel;
    
    public function __construct() {
        parent::__construct();
        $this->conectar = new Conectar();
        $this->Adapter = $this->conectar->conexion();
        $this->AdapterModel = $this->conectar->startFluent();
    }
    
    /**
     * PROCESAR TRANSFORMACIÓN
     * 
     * Este método registra la transformación de un producto a otro.
     * Ejemplo: Café cereza → Café pergamino
     * 
     * Datos que recibe por POST:
     * - producto_entrada: ID del producto que entra (ej: 45 - Café Cereza)
     * - cantidad_entrada: Cantidad que entra (ej: 100 qq)
     * - producto_salida: ID del producto que sale (ej: 67 - Café Pergamino)
     * - bodega_id: ID de la bodega donde ocurre
     * 
     * ⚠️ ESTE CÓDIGO TIENE UN BUG CRÍTICO
     */
    
    /*public function ProcesarTransformacionOld() {
        
        // Validar token CSRF
        if (!$this->ValToken()) {
            echo json_encode([
                'Result' => '0',
                'Error' => 'Token inválido'
            ]);
            return;
        }
        
        // Obtener datos del POST
        // RenderPost es un método legacy que procesa el POST
        $Post = $this->RenderPost(['BtnGrabar', 'ToKen']);
        
        // Extraer valores
        $producto_entrada = $Post['Val'][0];  // ID producto entrada (cereza)
        $cantidad_entrada = $Post['Val'][1];  // Cantidad entrada (100 qq)
        $producto_salida = $Post['Val'][2];   // ID producto salida (pergamino)
        $bodega_id = $Post['Val'][3];         // ID bodega
        
        // Inicializar modelo para acceder a la BD
        $Datos = new KardexFpdoModel($this->AdapterModel);
        
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 1: Registrar ENTRADA de materia prima
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Esto registra que ingresó café cereza al beneficio
        $result1 = $Datos->fluent()->insertInto('kardex', [
            'producto_id' => $producto_entrada,
            'cantidad' => $cantidad_entrada,
            'tipo' => 'entrada',
            'bodega_id' => $bodega_id,
            'fecha' => date('Y-m-d H:i:s'),
            'usuario_id' => $_SESSION['User_ID']
        ])->execute();
        
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // PASO 2: Registrar SALIDA de producto transformado
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // ⚠️⚠️⚠️ AQUÍ ESTÁ EL BUG ⚠️⚠️⚠️
        // 
        // La mayoría pensará que el bug está en la línea de abajo
        // donde dice "cantidad_entrada" en lugar de calcular el
        // rendimiento. Pero ese NO es el único problema.
        //
        // El BUG REAL es CONCEPTUAL y está en el flujo completo:
        // 
        // PROBLEMA 1: El flujo está INVERTIDO
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Lo que hace:
        //   1. ENTRADA de cereza (100 qq) ← ¿De dónde viene?
        //   2. SALIDA de pergamino (100 qq) ← ¿A dónde va?
        //
        // Lo que DEBERÍA hacer:
        //   1. SALIDA de cereza (100 qq) ← Sale del inventario para procesar
        //   2. ENTRADA de pergamino (85 qq) ← Entra como nuevo producto
        //
        // PROBLEMA 2: Falta el rendimiento
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Usa cantidad_entrada directamente sin aplicar rendimiento
        // Cereza → Pergamino tiene 85% de rendimiento
        // 100 qq cereza → 85 qq pergamino (no 100 qq)
        //
        // PROBLEMA 3: Falta registrar la merma
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Los 15 qq de diferencia (merma) no se registran
        // No hay trazabilidad de la pérdida
        //
        // PROBLEMA 4: Faltan campos críticos
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // - transformacion_id: Para relacionar entrada/salida
        // - costo_unitario: Para calcular costos correctamente
        // - merma: Para documentar pérdida
        // - rendimiento: Para auditoría
        //
        // PROBLEMA 5: Sin transacciones
        // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        // Si falla result2, result1 queda registrado
        // Causa inconsistencia en la BD
        //
        $result2 = $Datos->fluent()->insertInto('kardex', [
            'producto_id' => $producto_salida,
            'cantidad' => $cantidad_entrada,  // ⚠️ LÍNEA SOSPECHOSA (pero no es el único problema)
            'tipo' => 'salida',
            'bodega_id' => $bodega_id,
            'fecha' => date('Y-m-d H:i:s'),
            'usuario_id' => $_SESSION['User_ID']
        ])->execute();
        
        // Retornar resultado
        if ($result1 && $result2) {
            echo json_encode([
                'Result' => '1',
                'Message' => 'Transformación procesada exitosamente'
            ]);
        } else {
            echo json_encode([
                'Result' => '0',
                'Error' => 'Error al procesar transformación'
            ]);
        }
    }*/
    
    public function ProcesarTransformacion(array $datos): array {
        // Validar token CSRF
        if (!$this->ValToken()) {
            echo json_encode([
                'Result' => '0',
                'Error' => 'Token inválido'
            ]);
            return;
        }
        
        //ID Cereza --> producto_entrada_id
        //Cereza    --> Cantidad a procesar (cantidad_entrada)
        //ID Pergamino --> $producto_salida_id
        $requiredField = ['producto_entrada_id', 'cantidad_entrada', 'producto_salida_id', 'bodega_id'];
        
        foreach ($requiredField as $field) {
            if (!isset($datos[$field])) {
                throw new \InvalidArgumentException("El campo es requerido: {$field}");
            }
        }
        
        $factor_rendimiento = $datos['rendimiento'] ?? $this->calcularRendimientoEstandard(
            $datos['producto_entrada_id'],
            $datos['producto_salida_id']
        );

        $fecha_actual = date('Y-m-d H:i:s');
        $usuario_id = $_SESSION['User_ID'];
        $transformacion_id = uniqid('TRX-'); //Se vinculan los registros
        
        $cantidad_salida = $datos['cantidad_entrada'] * $factor_rendimiento;
        $cantidad_merma = $datos['cantidad_entrada'] - $cantidad_salida;

        $Datos = new KardexFpdoModel($this->AdapterModel);
        $db = $Datos->fluent()->getPdo(); // Obtenemos el objeto PDO para la transacción

        try {
            //Se inicia transaccion
            $db->beginTransaction();

            //Salida de materia prima (Cereza)
            $result1 = $Datos->fluent()->insertInto('kardex', [
                'producto_id'      => $datos['producto_entrada_id'],
                'cantidad'         => $datos['cantidad_entrada'],
                'tipo'             => 'salida',
                //Se obtiene el precio del producto mas actual
                'precio_unitario' => $this->calcularCostoProducto($datos['producto_entrada_id']),
                'bodega_id'        => $datos['bodega_id'],
                'fecha'            => $fecha_actual,
                'usuario_id'       => $usuario_id,
                'transformacion_id'=> $transformacion_id,
                'observaciones'    => 'Salida por proceso de transformación'
            ])->execute();

            //Entrada de producto terminado (Pergamino)
            $result2 = $Datos->fluent()->insertInto('kardex', [
                'producto_id'      => $datos['producto_salida_id'] ,
                'cantidad'         => $cantidad_salida,
                'tipo'             => 'entrada', 
                'precio_unitario' => $this->calcularNuevoCosto(
                        $datos['producto_entrada_id'],
                        $datos['producto_salida_id'],
                        $datos['cantidad_entrada'],
                        $factor_rendimiento),
                'bodega_id'        => $datos['bodega_id'],
                'fecha'            => $fecha_actual,
                'usuario_id'       => $usuario_id,
                'transformacion_id'=> $transformacion_id,
                'rendimiento'      => $factor_rendimiento,
                'merma'            => $cantidad_merma,
                'observaciones'    => "Entrada de transformación (Pergamino). Merma: $cantidad_merma"
            ])->execute();

            if ($cantidad_merma > 0) {
                //Se registra merma si aplica
                $result3= $Datos->fluent()->insertInto('transformaciones',[
                    'producto_entrada_id' => $datos['producto_entrada_id'], 
                    'cantidad_entrada' => $datos['cantidad_entrada'],
                    'cantidad_salida' => $datos['producto_salida_id'], 
                    'merma' => $cantidad_merma,
                    'rendimiento' => $factor_rendimiento,
                    'costo_transformacion' => $cantidad_merma,
                    'bodega_id' => $datos['bodega_id'],
                    'fecha' => $fecha_actual,
                    'usuario_id' => $datos['usuario_id']])
                ->execute();
            }

            $Datos->fluent()->commit();
            
            //Se retorna el resultado
            if ($result1 && $result2 && $result3) {
                echo json_encode([
                    'Result' => '1',
                    'Message' => 'Registro procesado exitosamente'
                ]);
            } else {
                echo json_encode([
                    'Result' => '0',
                    'Error' => 'Error al procesar transformación'
                ]);
            }

        } catch (Exception $e) {
           //Rollback en caso de error
            $Datos->fluent()->rollBack();
            error_log("Error al procesar transformación: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ];
        }
    }
    
    private function calcularRendimientoEstandard(int $productoEntradaId, int $productoSalidaId): float
    {
        // Valores de rendimientos estándard (ids de productos)
        $rendimientos = [
            //Cereza -> Pergamino
            '45:67' => 0.85, // 85% rendimiento
            //Pergamino -> Oro
            '67:89' => 0.80, // 80% rendimiento
            //Default: 100% (sin merma)
            'default' => 1.0
        ];
        
        $key = "{$productoEntradaId}:{$productoSalidaId}";
        return $rendimientos[$key] ?? $rendimientos['default'];
    }
    
    private function calcularNuevoCosto(int $productoEntradaId, int $productoSalidaId, float $cantidad, float $rendimiento): float
    {
        //Se obtiene el costo de transformacion
        $costo_transformacion = $this->obtenerCostoTransformacion($productoSalidaId);
        //Se calcula el costo basado en materia prima + costos de transformación
        $costo_materia_prima = $this->calcularCostoProducto($productoEntradaId) * $cantidad;

        $costo_total = $costo_materia_prima + $costo_transformacion;
        $cantidad_producida = $cantidad * $rendimiento;
        
        return $cantidad_producida > 0 ? $costo_total / $cantidad_producida : 0;
    }
    
    private function calcularCostoProducto(int $productoId): float
    {
        $precio_unitario = $Datos->fluent()
            ->from('kardex k',)
            ->select('k.precio_unitario')  
            ->where('k.producto_id = ?',$productoId)    
            ->where('EXISTS (
                SELECT 1 FROM productos p 
                WHERE p.id = k.producto_id AND p.estado = 1)')
            ->orderBy('ORDER BY k.fecha DESC')  
            ->limit(1)    
            ->fetchAll();
            
         return (float) $precio_unitario['precio_unitario'] ?? 0;
    }
    
    private function obtenerCostoTransformacion(int $productoId): float
    {
        $costo_transformacion = $Datos->fluent()
            ->from('transformaciones t',)
            ->select('t.costo_transformacion')  
            ->where('t.producto_id = ?',$productoId)    
            ->orderBy('ORDER BY t.fecha DESC')  
            ->limit(1)
            ->fetchAll();
            
         return (float) $costo_transformacion['costo_transformacion'] ?? 0;
    }
    
    /**
     * MÉTODO ADICIONAL (no se usa pero existe)
     * 
     * Este método intenta obtener el rendimiento pero no se llama
     */
    private function obtenerRendimiento($producto_entrada_id, $producto_salida_id) {
        
        // Tabla que debería existir pero no está implementada
        $Datos = new TransformacionTipoModel($this->AdapterModel);
        
        $rendimiento = $Datos->fluent()
            ->from('transformacion_tipo')
            ->where('producto_entrada_id = ?', $producto_entrada_id)
            ->where('producto_salida_id = ?', $producto_salida_id)
            ->select('rendimiento')
            ->fetch();
        
        // Rendimientos estándar si no existe en BD
        // Cereza → Pergamino: 85%
        // Pergamino → Oro: 80%
        return $rendimiento['rendimiento'] ?? 0.85;
    }
}

/**
 * ═══════════════════════════════════════════════════════════════════
 * NOTAS PARA EL EVALUADOR
 * ═══════════════════════════════════════════════════════════════════
 * 
 * ANÁLISIS ESPERADO DEL CANDIDATO:
 * 
 * 1. IDENTIFICACIÓN DEL BUG REAL:
 *    ─────────────────────────────
 *    No es solo "usar cantidad_entrada en lugar de calcular rendimiento"
 *    El bug es CONCEPTUAL en el flujo completo:
 *    
 *    - El flujo entrada/salida está invertido
 *    - Falta aplicar rendimiento (85%)
 *    - Falta registrar merma (15 qq)
 *    - Faltan campos de trazabilidad
 *    - Sin transacciones atómicas
 * 
 * 2. EXPLICACIÓN DE LOS 15 QQ PERDIDOS:
 *    ───────────────────────────────────
 *    100 qq cereza → 85 qq pergamino (rendimiento 85%)
 *    Merma: 15 qq (agua, impurezas, pérdidas naturales)
 *    
 *    Sistema registra:
 *    ✓ 100 qq cereza ENTRAN (incorrecto - deberían SALIR)
 *    ✓ 100 qq pergamino SALEN (incorrecto - deberían ENTRAR 85 qq)
 *    
 *    Resultado en inventario:
 *    - Cereza: +100 qq (ficticio)
 *    - Pergamino: -100 qq (ficticio)
 *    - Merma: No registrada
 * 
 * 3. ESTRUCTURA CORRECTA PROPUESTA:
 *    ───────────────────────────────
 *    ```sql
 *    -- Tabla transformaciones
 *    CREATE TABLE transformaciones (
 *        id INT PRIMARY KEY AUTO_INCREMENT,
 *        producto_entrada_id INT,
 *        cantidad_entrada DECIMAL(10,2),
 *        producto_salida_id INT,
 *        cantidad_salida DECIMAL(10,2),
 *        merma DECIMAL(10,2),
 *        rendimiento DECIMAL(5,2),
 *        costo_transformacion DECIMAL(10,2),
 *        bodega_id INT,
 *        fecha DATETIME,
 *        usuario_id INT
 *    );
 *    
 *    -- En kardex agregar:
 *    ALTER TABLE kardex ADD COLUMN transformacion_id INT;
 *    ```
 * 
 * 4. LÓGICA CORRECTA:
 *    ────────────────
 *    a) Iniciar transacción
 *    b) Crear registro en transformaciones
 *    c) SALIDA de producto entrada (100 qq cereza)
 *    d) Calcular cantidad salida: 100 * 0.85 = 85 qq
 *    e) ENTRADA de producto salida (85 qq pergamino)
 *    f) Registrar merma: 100 - 85 = 15 qq
 *    g) Commit o rollback
 * 
 * 5. MIGRACIÓN DE DATOS:
 *    ───────────────────
 *    SQL para identificar y corregir transformaciones erróneas:
 *    
 *    ```sql
 *    -- Identificar transformaciones mal registradas
 *    SELECT 
 *        k1.id as entrada_id,
 *        k1.cantidad as cantidad_incorrecta,
 *        k1.cantidad * 0.85 as cantidad_correcta
 *    FROM kardex k1
 *    WHERE k1.tipo = 'entrada'
 *      AND k1.producto_id = 45  -- Cereza
 *      AND EXISTS (
 *          SELECT 1 FROM kardex k2
 *          WHERE k2.producto_id = 67  -- Pergamino
 *            AND k2.tipo = 'salida'
 *            AND k2.cantidad = k1.cantidad  -- Bug: misma cantidad
 *            AND ABS(TIMESTAMPDIFF(SECOND, k1.fecha, k2.fecha)) < 60
 *      );
 *    ```
 * 
 * PUNTUACIÓN ESPERADA:
 * ──────────────────
 * - 10/10: Identifica bug conceptual + propone solución completa
 * - 7-9/10: Identifica bug de cantidad pero no el flujo invertido
 * - 4-6/10: Solo identifica "cantidad_entrada" sin profundizar
 * - 0-3/10: No entiende el problema o propone solución incorrecta
 */
