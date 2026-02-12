<?php
class ReporteExcelController extends ControladorBase {
    
    public $conectar;
    public $Adapter;
    public $AdapterModel;
    
    public function __construct() {
        parent::__construct();
        $this->conectar = new Conectar();
        $this->Adapter = $this->conectar->conexion();
        $this->AdapterModel = $this->conectar->startFluent();
    }
      
	public function exportarExcel()
	{
		try {
			$this->requireAuth();
			
			// Se desactiva buffering explícitamente
			if (ob_get_level()) {
				ob_end_clean();
			}
			
			//Se verifican los headers antes de enviar
			if (headers_sent($filename, $linenum)) {
				throw new \RuntimeException(
					"Headers already sent in $filename:$linenum"
				);
			}
			
			//Stream output directamente
			$datos = $this->repository->getAll();
			
			//Creacion de archivo temporal
			$tempFile = tempnam(sys_get_temp_dir(), 'excel_');
			$excel = new ExcelBuilder();
			
			foreach ($datos as $row) {
				$excel->addRow($row);
			}
			
			$excel->save($tempFile);
			
			// Se envia el archivo con headers correctos
			header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
			header('Content-Disposition: attachment; filename="reporte_' . date('Y-m-d') . '.xlsx"');
			header('Content-Length: ' . filesize($tempFile));
			header('Cache-Control: no-cache, no-store, must-revalidate');
			header('Pragma: no-cache');
			header('Expires: 0');
			
			//Se lee el archivo tempral
			readfile($tempFile);
			
			// Se limpia el archivo temporal
			unlink($tempFile);
			
			exit;
			
		} catch (\Throwable $e) {
        // Log error
        error_log("Error al exportar el archivo de Excel: " . $e->getMessage());
        
        // Send proper error response
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Error al genrar el archivo',
                'request_id' => uniqid()
            ]);
        }
        
        exit(1);
    }
}
}
