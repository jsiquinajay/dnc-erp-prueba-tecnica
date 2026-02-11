<?php
/**
 * CÓDIGO LEGACY PROBLEMÁTICO
 * 
 * Este controlador tiene problemas graves de performance intencionales
 * para ser analizado en la Parte 1 de la prueba técnica.
 * 
 * CONTEXTO:
 * Este es un reporte de inventarios que muestra existencias y costos
 * de productos en múltiples bodegas. Se ejecuta frecuentemente y con
 * el crecimiento de datos (856 productos, 12 bodegas) se ha vuelto
 * extremadamente lento.
 * 
 * PROBLEMAS IDENTIFICADOS POR USUARIOS:
 * - Tarda más de 8 segundos en cargar
 * - A veces causa timeout (30 seg)
 * - Bloquea el servidor cuando múltiples usuarios lo usan
 * 
 * OBJETIVO DE LA PRUEBA:
 * El candidato debe identificar los problemas de performance,
 * explicar el impacto y proponer solución optimizada.
 */

class KardexController extends ControladorBase {
    
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
     * MÉTODO INDEX - LISTADO DE EXISTENCIAS
     * 
     * Genera reporte de existencias actuales por producto y bodega
     * incluyendo costo promedio de cada producto.
     * 
     * ⚠️ ESTE CÓDIGO TIENE PROBLEMAS GRAVES DE PERFORMANCE
     */
    public function Index() {
        
        // Inicializar modelo
        $Datos = new KardexFpdoModel($this->AdapterModel);
        
        // Obtener TODOS los productos activos
        // ⚠️ Problema potencial #1: Sin paginación
        $productos = $Datos->fluent()
            ->from('productos')
            ->where('estado = ?', 1)
            ->fetchAll();
        
        // Array para almacenar resultados
        $resultados = [];
        
        // ⚠️ PROBLEMA CRÍTICO: Loop con queries dentro (N+1 Problem)
        // Para cada producto, hacer 3 queries adicionales
        foreach ($productos as $producto) {
            
            // Query 1: Sumar entradas del producto
            // ⚠️ Esta query se ejecuta 856 veces (una por cada producto)
            $entradas = $Datos->fluent()
                ->from('kardex')
                ->where('producto_id = ?', $producto['id'])
                ->where('tipo = ?', 'entrada')
                ->select('SUM(cantidad) as total')
                ->fetch();
            
            // Query 2: Sumar salidas del producto
            // ⚠️ Esta query también se ejecuta 856 veces
            $salidas = $Datos->fluent()
                ->from('kardex')
                ->where('producto_id = ?', $producto['id'])
                ->where('tipo = ?', 'salida')
                ->select('SUM(cantidad) as total')
                ->fetch();
            
            // Calcular existencia actual
            $existencia = ($entradas['total'] ?? 0) - ($salidas['total'] ?? 0);
            
            // Query 3: Obtener último precio
            // ⚠️ Método que ejecuta más queries (ver abajo)
            $costoPromedio = $this->calcularCostoPromedio($producto['id']);
            
            // Agregar al resultado
            $resultados[] = [
                'producto_id'   => $producto['id'],
                'producto'      => $producto['nombre'],
                'codigo'        => $producto['codigo'],
                'existencia'    => $existencia,
                'costo'         => $costoPromedio,
                'valor_total'   => $existencia * $costoPromedio
            ];
        }
        
        // Renderizar vista con resultados
        // El template mostrará una tabla HTML
        $this->view('Kardex/IndexView', [
            'datos' => $resultados,
            'total_productos' => count($resultados)
        ]);
    }
    
    /**
     * CALCULAR COSTO PROMEDIO
     * 
     * Calcula el costo promedio ponderado de un producto
     * basándose en todas sus entradas históricas.
     * 
     * ⚠️ PROBLEMAS EN ESTE MÉTODO:
     * 1. Trae TODOS los movimientos históricos (sin límite)
     * 2. Hace el cálculo en PHP en lugar de SQL
     * 3. Se llama dentro de un loop (856 veces)
     * 4. El algoritmo de costo está mal implementado
     * 
     * @param int $producto_id ID del producto
     * @return float Costo promedio
     */
    private function calcularCostoPromedio($producto_id) {
        
        // Crear nueva instancia del modelo
        // ⚠️ Problema: Instancia innecesaria en cada llamada
        $Datos = new KardexFpdoModel($this->AdapterModel);
        
        // Obtener TODOS los movimientos de entrada del producto
        // ⚠️ Problema crítico: Sin límite, puede ser miles de registros
        $movimientos = $Datos->fluent()
            ->from('kardex')
            ->where('producto_id = ?', $producto_id)
            ->where('tipo = ?', 'entrada')
            ->orderBy('fecha DESC')
            ->fetchAll();
        
        // Variables para el cálculo
        $suma_total = 0;
        $cantidad_total = 0;
        
        // ⚠️ Problema: Cálculo en PHP en lugar de SQL
        // Iterar sobre todos los movimientos
        foreach ($movimientos as $mov) {
            $suma_total += ($mov['precio_unitario'] * $mov['cantidad']);
            $cantidad_total += $mov['cantidad'];
        }
        
        // Calcular promedio
        // ⚠️ PROBLEMA DE LÓGICA: Este no es el costo promedio correcto
        // No considera FIFO/LIFO ni el orden temporal real
        // Para café (producto perecedero), debería usar FIFO
        $promedio = $cantidad_total > 0 ? $suma_total / $cantidad_total : 0;
        
        return round($promedio, 2);
    }
    
    /**
     * MÉTODO ALTERNATIVO (No se usa, pero existe en el código)
     * 
     * Este método tiene otro enfoque pero también es ineficiente
     */
    public function ReporteDetallado() {
        
        $Datos = new KardexFpdoModel($this->AdapterModel);
        
        // Obtener productos
        $productos = $Datos->fluent()->from('productos')->where('estado = ?', 1)->fetchAll();
        
        // Obtener bodegas
        $bodegas = $Datos->fluent()->from('bodegas')->where('estado = ?', 1)->fetchAll();
        
        $reporte = [];
        
        // ⚠️ PROBLEMA MÁS GRAVE: Loop anidado con queries
        // Complejidad: O(n * m * queries) donde n=productos, m=bodegas
        foreach ($productos as $producto) {
            foreach ($bodegas as $bodega) {
                
                // Query por cada combinación producto-bodega
                // 856 productos × 12 bodegas = 10,272 queries!
                $existencia = $Datos->fluent()
                    ->from('kardex')
                    ->where('producto_id = ?', $producto['id'])
                    ->where('bodega_id = ?', $bodega['id'])
                    ->select('SUM(CASE WHEN tipo = "entrada" THEN cantidad ELSE -cantidad END) as total')
                    ->fetch();
                
                $reporte[] = [
                    'producto' => $producto['nombre'],
                    'bodega' => $bodega['nombre'],
                    'existencia' => $existencia['total'] ?? 0
                ];
            }
        }
        
        $this->view('Kardex/ReporteDetallado', ['datos' => $reporte]);
    }
}

/**
 * NOTAS PARA EL EVALUADOR:
 * 
 * PROBLEMAS ESPERADOS QUE EL CANDIDATO DEBE IDENTIFICAR:
 * 
 * 1. N+1 Query Problem:
 *    - 1 query inicial (productos)
 *    - 856 queries (entradas por producto)
 *    - 856 queries (salidas por producto)
 *    - 856 queries (costo promedio)
 *    - TOTAL: ~2,569 queries solo en Index()
 * 
 * 2. Sin paginación:
 *    - Carga todos los productos sin límite
 * 
 * 3. Cálculo ineficiente:
 *    - Trae todos los movimientos históricos
 *    - Calcula en PHP en lugar de SQL
 * 
 * 4. Lógica incorrecta:
 *    - El costo promedio no considera FIFO
 *    - Importante para café (perecedero)
 * 
 * 5. Instancias innecesarias:
 *    - Crea new KardexFpdoModel en cada iteración
 * 
 * SOLUCIÓN ESPERADA:
 * - Usar JOINs y subqueries para reducir a 1-2 queries
 * - Agregar paginación
 * - Calcular costos en SQL con agregaciones
 * - Implementar FIFO correctamente si es necesario
 * - Usar índices apropiados en la BD
 * 
 * IMPACTO EN PRODUCCIÓN:
 * - 856 productos: ~8.5 segundos (experiencia actual)
 * - Con 2,000 productos: ~20 segundos (proyectado)
 * - 10 usuarios concurrentes: servidor colapsa
 */
