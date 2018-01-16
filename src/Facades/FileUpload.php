<?php
namespace FileUploader\Facades;

use FileUploader\Services\FileUploaderService;
use Illuminate\Support\Facades\Facade;

/**
 * Class FileUpload
 * @package FileUploader\Facades
 */
class FileUpload extends Facade
{
    /**
     * @return FileUploaderService
     */
    protected static function getFacadeAccessor()
    {
        return FileUploaderService::class;
    }
}