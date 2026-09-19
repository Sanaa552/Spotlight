<?php

$publicPath = realpath(__DIR__.'/public');
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$filePath = realpath($publicPath.$requestPath);
$publicPhotoPath = realpath(__DIR__.'/storage/app/public/photos-publiques');
$isPublicPhoto = str_starts_with($requestPath, '/storage/photos-publiques/')
    && $publicPhotoPath !== false
    && $filePath !== false
    && str_starts_with($filePath, $publicPhotoPath.DIRECTORY_SEPARATOR);

if ($requestPath !== '/'
    && $filePath !== false
    && (str_starts_with($filePath, $publicPath.DIRECTORY_SEPARATOR) || $isPublicPhoto)
    && is_file($filePath)) {
    return false;
}

require $publicPath.'/index.php';
