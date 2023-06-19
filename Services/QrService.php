<?php 

class QrService{

    /**
     * inscriptionNumber || $inscription->id
     */
    public static function generateQr(int $inscriptionNumber)
    {
        try {            
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $qrCode = new \Endroid\QrCode\QrCode(100000 + $inscriptionNumber); 
            return $writer->write($qrCode);
        } catch (\QrGeneratorException $th) {
            throw $th;
        }
    }
}