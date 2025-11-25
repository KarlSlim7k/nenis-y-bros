<?php

/**
 * FileOptimizer
 * 
 * Utilidad para optimización de archivos: compresión de imágenes,
 * generación de thumbnails, URLs firmadas temporales
 */
class FileOptimizer {
    private static $instance = null;
    
    // Configuración
    private const MAX_IMAGE_WIDTH = 1920;
    private const MAX_IMAGE_HEIGHT = 1080;
    private const THUMBNAIL_WIDTH = 400;
    private const THUMBNAIL_HEIGHT = 300;
    private const JPEG_QUALITY = 85;
    private const WEBP_QUALITY = 80;
    
    private $uploadDir;
    private $cacheDir;
    
    private function __construct() {
        $this->uploadDir = __DIR__ . '/../../uploads/recursos/';
        $this->cacheDir = __DIR__ . '/../../uploads/cache/';
        
        // Crear directorios si no existen
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Optimizar imagen subida
     */
    public function optimizarImagen($rutaOriginal, $nombreArchivo = null) {
        if (!file_exists($rutaOriginal)) {
            throw new Exception("Archivo no encontrado: $rutaOriginal");
        }
        
        $imageInfo = @getimagesize($rutaOriginal);
        if ($imageInfo === false) {
            throw new Exception("El archivo no es una imagen válida");
        }
        
        list($width, $height, $type) = $imageInfo;
        
        // Crear imagen desde archivo
        $sourceImage = $this->createImageFromFile($rutaOriginal, $type);
        if ($sourceImage === false) {
            throw new Exception("No se pudo procesar la imagen");
        }
        
        // Calcular nuevas dimensiones si excede límites
        $newDimensions = $this->calculateResizeDimensions(
            $width, 
            $height, 
            self::MAX_IMAGE_WIDTH, 
            self::MAX_IMAGE_HEIGHT
        );
        
        $optimizada = $sourceImage;
        
        // Redimensionar si es necesario
        if ($newDimensions['width'] !== $width || $newDimensions['height'] !== $height) {
            $optimizada = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            
            // Preservar transparencia para PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($optimizada, false);
                imagesavealpha($optimizada, true);
                $transparent = imagecolorallocatealpha($optimizada, 255, 255, 255, 127);
                imagefilledrectangle($optimizada, 0, 0, $newDimensions['width'], $newDimensions['height'], $transparent);
            }
            
            imagecopyresampled(
                $optimizada, 
                $sourceImage,
                0, 0, 0, 0,
                $newDimensions['width'], 
                $newDimensions['height'],
                $width, 
                $height
            );
        }
        
        // Generar nombre único si no se proporcionó
        if ($nombreArchivo === null) {
            $extension = image_type_to_extension($type, false);
            $nombreArchivo = uniqid('img_') . '.' . $extension;
        }
        
        $rutaOptimizada = $this->uploadDir . $nombreArchivo;
        
        // Guardar imagen optimizada
        $this->saveOptimizedImage($optimizada, $rutaOptimizada, $type);
        
        // Generar thumbnail
        $thumbnailPath = $this->generarThumbnail($optimizada, $nombreArchivo);
        
        // Generar versión WebP si es posible
        $webpPath = $this->generarWebP($optimizada, $nombreArchivo);
        
        imagedestroy($sourceImage);
        if ($optimizada !== $sourceImage) {
            imagedestroy($optimizada);
        }
        
        return [
            'original' => $rutaOptimizada,
            'thumbnail' => $thumbnailPath,
            'webp' => $webpPath,
            'url' => $this->getPublicUrl($nombreArchivo),
            'thumbnail_url' => $this->getPublicUrl('thumbnails/' . $nombreArchivo),
            'webp_url' => $webpPath ? $this->getPublicUrl(basename($webpPath)) : null,
            'size' => filesize($rutaOptimizada),
            'dimensions' => [
                'width' => $newDimensions['width'],
                'height' => $newDimensions['height']
            ]
        ];
    }
    
    /**
     * Generar thumbnail de una imagen
     */
    public function generarThumbnail($sourceImage, $nombreBase) {
        $thumbnailDir = $this->uploadDir . 'thumbnails/';
        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }
        
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        $thumbDimensions = $this->calculateResizeDimensions(
            $width,
            $height,
            self::THUMBNAIL_WIDTH,
            self::THUMBNAIL_HEIGHT
        );
        
        $thumbnail = imagecreatetruecolor($thumbDimensions['width'], $thumbDimensions['height']);
        
        // Preservar transparencia
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $thumbDimensions['width'], $thumbDimensions['height'], $transparent);
        
        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0, 0, 0,
            $thumbDimensions['width'],
            $thumbDimensions['height'],
            $width,
            $height
        );
        
        $thumbnailPath = $thumbnailDir . $nombreBase;
        imagejpeg($thumbnail, $thumbnailPath, self::JPEG_QUALITY);
        imagedestroy($thumbnail);
        
        return $thumbnailPath;
    }
    
    /**
     * Generar versión WebP de la imagen
     */
    public function generarWebP($sourceImage, $nombreBase) {
        if (!function_exists('imagewebp')) {
            return null; // WebP no soportado
        }
        
        $nombreWebP = pathinfo($nombreBase, PATHINFO_FILENAME) . '.webp';
        $webpPath = $this->uploadDir . $nombreWebP;
        
        imagewebp($sourceImage, $webpPath, self::WEBP_QUALITY);
        
        return $webpPath;
    }
    
    /**
     * Generar URL firmada temporal para descarga segura
     */
    public function generarUrlFirmada($idRecurso, $archivoUrl, $expiresIn = 3600) {
        $expires = time() + $expiresIn;
        
        // Crear firma HMAC
        $data = $idRecurso . '|' . $archivoUrl . '|' . $expires;
        $signature = hash_hmac('sha256', $data, ENCRYPTION_KEY);
        
        // Generar token
        $token = base64_encode(json_encode([
            'id' => $idRecurso,
            'file' => $archivoUrl,
            'exp' => $expires,
            'sig' => $signature
        ]));
        
        return [
            'url' => APP_URL . '/backend/api/v1/recursos/download/' . urlencode($token),
            'expires_at' => date('Y-m-d H:i:s', $expires),
            'expires_in' => $expiresIn
        ];
    }
    
    /**
     * Verificar y validar URL firmada
     */
    public function verificarUrlFirmada($token) {
        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || !isset($decoded['id'], $decoded['file'], $decoded['exp'], $decoded['sig'])) {
                return ['valid' => false, 'error' => 'Token inválido'];
            }
            
            // Verificar expiración
            if (time() > $decoded['exp']) {
                return ['valid' => false, 'error' => 'URL expirada'];
            }
            
            // Verificar firma
            $data = $decoded['id'] . '|' . $decoded['file'] . '|' . $decoded['exp'];
            $expectedSignature = hash_hmac('sha256', $data, ENCRYPTION_KEY);
            
            if (!hash_equals($expectedSignature, $decoded['sig'])) {
                return ['valid' => false, 'error' => 'Firma inválida'];
            }
            
            return [
                'valid' => true,
                'id_recurso' => $decoded['id'],
                'archivo_url' => $decoded['file']
            ];
            
        } catch (Exception $e) {
            return ['valid' => false, 'error' => 'Error al verificar token'];
        }
    }
    
    /**
     * Comprimir archivo PDF (requiere Ghostscript)
     */
    public function comprimirPDF($rutaOriginal, $calidad = 'ebook') {
        // Niveles: screen, ebook, printer, prepress
        $rutaComprimida = $this->cacheDir . uniqid('pdf_') . '.pdf';
        
        // Verificar si Ghostscript está instalado
        $gsCommand = 'gs'; // o 'gswin64c' en Windows
        
        $command = sprintf(
            '%s -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/%s -dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
            $gsCommand,
            $calidad,
            escapeshellarg($rutaComprimida),
            escapeshellarg($rutaOriginal)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($rutaComprimida)) {
            $sizeOriginal = filesize($rutaOriginal);
            $sizeComprimido = filesize($rutaComprimida);
            $ahorro = round((($sizeOriginal - $sizeComprimido) / $sizeOriginal) * 100, 2);
            
            return [
                'success' => true,
                'ruta' => $rutaComprimida,
                'size_original' => $sizeOriginal,
                'size_comprimido' => $sizeComprimido,
                'ahorro_porcentaje' => $ahorro
            ];
        }
        
        return ['success' => false, 'error' => 'No se pudo comprimir el PDF'];
    }
    
    /**
     * Obtener información de un archivo
     */
    public function getFileInfo($rutaArchivo) {
        if (!file_exists($rutaArchivo)) {
            return null;
        }
        
        $info = [
            'size' => filesize($rutaArchivo),
            'size_formatted' => $this->formatBytes(filesize($rutaArchivo)),
            'mime_type' => mime_content_type($rutaArchivo),
            'extension' => pathinfo($rutaArchivo, PATHINFO_EXTENSION),
            'modified' => filemtime($rutaArchivo)
        ];
        
        // Si es imagen, obtener dimensiones
        if (strpos($info['mime_type'], 'image/') === 0) {
            $imageInfo = @getimagesize($rutaArchivo);
            if ($imageInfo) {
                $info['dimensions'] = [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ];
            }
        }
        
        return $info;
    }
    
    /**
     * Limpiar caché de archivos antiguos
     */
    public function limpiarCache($diasAntiguedad = 7) {
        $archivosEliminados = 0;
        $espacioLiberado = 0;
        
        $files = glob($this->cacheDir . '*');
        $tiempoLimite = time() - ($diasAntiguedad * 86400);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $tiempoLimite) {
                $size = filesize($file);
                if (unlink($file)) {
                    $archivosEliminados++;
                    $espacioLiberado += $size;
                }
            }
        }
        
        return [
            'archivos_eliminados' => $archivosEliminados,
            'espacio_liberado' => $espacioLiberado,
            'espacio_liberado_formatted' => $this->formatBytes($espacioLiberado)
        ];
    }
    
    /**
     * Generar srcset para imágenes responsive
     */
    public function generarSrcSet($rutaImagen, $nombreBase) {
        $sizes = [
            'small' => 480,
            'medium' => 768,
            'large' => 1200,
            'xlarge' => 1920
        ];
        
        $sourceImage = $this->createImageFromFile($rutaImagen);
        if ($sourceImage === false) {
            return null;
        }
        
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        $srcset = [];
        
        foreach ($sizes as $name => $maxWidth) {
            if ($width <= $maxWidth) {
                continue; // No generar si es más pequeña que el original
            }
            
            $newDimensions = $this->calculateResizeDimensions($width, $height, $maxWidth, 9999);
            
            $resized = imagecreatetruecolor($newDimensions['width'], $newDimensions['height']);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            
            imagecopyresampled(
                $resized,
                $sourceImage,
                0, 0, 0, 0,
                $newDimensions['width'],
                $newDimensions['height'],
                $width,
                $height
            );
            
            $nombreResized = pathinfo($nombreBase, PATHINFO_FILENAME) . '_' . $name . '.jpg';
            $rutaResized = $this->uploadDir . 'responsive/' . $nombreResized;
            
            if (!file_exists(dirname($rutaResized))) {
                mkdir(dirname($rutaResized), 0755, true);
            }
            
            imagejpeg($resized, $rutaResized, self::JPEG_QUALITY);
            imagedestroy($resized);
            
            $srcset[$maxWidth] = $this->getPublicUrl('responsive/' . $nombreResized);
        }
        
        imagedestroy($sourceImage);
        
        return $srcset;
    }
    
    // =========================================================================
    // MÉTODOS PRIVADOS AUXILIARES
    // =========================================================================
    
    private function createImageFromFile($ruta, $type = null) {
        if ($type === null) {
            $imageInfo = @getimagesize($ruta);
            if ($imageInfo === false) {
                return false;
            }
            $type = $imageInfo[2];
        }
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($ruta);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($ruta);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($ruta);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($ruta);
            default:
                return false;
        }
    }
    
    private function saveOptimizedImage($image, $ruta, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $ruta, self::JPEG_QUALITY);
            case IMAGETYPE_PNG:
                // PNG usa compresión 0-9 (9 = máxima)
                return imagepng($image, $ruta, 8);
            case IMAGETYPE_GIF:
                return imagegif($image, $ruta);
            case IMAGETYPE_WEBP:
                return imagewebp($image, $ruta, self::WEBP_QUALITY);
            default:
                return false;
        }
    }
    
    private function calculateResizeDimensions($width, $height, $maxWidth, $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        
        if ($ratio >= 1) {
            return ['width' => $width, 'height' => $height];
        }
        
        return [
            'width' => round($width * $ratio),
            'height' => round($height * $ratio)
        ];
    }
    
    private function getPublicUrl($relativePath) {
        return APP_URL . '/uploads/recursos/' . $relativePath;
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
