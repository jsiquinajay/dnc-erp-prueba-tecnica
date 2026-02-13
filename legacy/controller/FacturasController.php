<?php
class FacturasController extends ControladorBase {
	public $conectar;
    public $Adapter;
    public $AdapterModel;
    
    public function __construct() {
        parent::__construct();
        $this->conectar = new Conectar();
        $this->Adapter = $this->conectar->conexion();
        $this->AdapterModel = $this->conectar->startFluent();
    }
	
	// FacturaController.php
	public function generaLoteFacturas(Request $request)
	{
		$Datos = new KardexFpdoModel($this->AdapterModel);
		$facturaIds = $request->input('factura_ids'); // Array de 500 IDs
		$loteId = uniqid('lote_');
    
		//Crear jobs para cada factura
		foreach ($facturaIds as $facturaId) {
			Redis::queue('facturas-sat')->push(new ProcesarFacturaSAT(
				$facturaId,
				$loteId,
				auth()->id()
			));
		}
		
		//Guardar datos del lote
        $Datos->fluent()->insertInto('lotes_facturacion', [
            'lote_id' => $producto_entrada,
            'total_facturas' => $cantidad_entrada,
            'usuario_id' => 'entrada',
            'estado' => $bodega_id,
            'fecha' => date('Y-m-d H:i:s')
         ])->execute();
	
    
		//Iniciar WebSocket para notificar progreso
		event(new LoteFacturacionCreado($loteId, auth()->user()));
		
		return response()->json([
			'success' => true,
			'lote_id' => $loteId,
			'total_facturas' => count($facturaIds),
			'ws_channel' => "lote-{$loteId}"
		]);
	}
}