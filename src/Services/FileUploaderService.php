<?php

namespace FileUploader\Services;

/**
 * Class FileUploaderService
 * @package FileUploader\Services
 */
class FileUploaderService
{
    private $defaultOptions = [
        'limit'              => null,
        'maxSize'            => null,
        'fileMaxSize'        => null,
        'extensions'         => null,
        'required'           => false,
        'uploadDir'          => 'uploads/',
        'title'              => ['auto', 12],
        'replace'            => false,
        'editor'             => [
            'maxWidth'  => null,
            'maxHeight' => null,
            'crop'      => false,
            'quality'   => 98,
        ],
        'listInput'          => true,
        'files'              => [],
        'move_uploaded_file' => null,
        'validate_file'      => null,
    ];
    private $field = null;
    private $options = null;

    /**
     * __construct method
     *
     * @public
     *
     * @param $name    {$_FILES key}
     * @param $options {null, Array}
     */
    public function __construct($name, $options = null)
    {
        $this->defaultOptions['move_uploaded_file'] = function ($tmp, $dest) {
            return \move_uploaded_file($tmp, $dest);
        };
        $this->defaultOptions['validate_file'] = function ($file, $options) {
            return true;
        };
        return $this->initialize($name, $options);
    }

    /**
     * initialize method
     * initialize the plugin
     *
     * @private
     *
     * @param $name    {String} Input name
     * @param $options {null, Array}
     *
     * @return bool
     */
    private function initialize($name, $options)
    {
        // merge options
        $this->options = $this->defaultOptions;
        if ($options) {
            $this->options = \array_merge($this->options, $options);
        }
        if (!\is_array($this->options['files'])) {
            $this->options['files'] = [];
        }

        // create field array
        $this->field = [
            'name'      => $name,
            'input'     => null,
            'listInput' => $this->getListInputFiles($name),
        ];

        if (isset($_FILES[$name])) {
            // set field input
            $this->field['input'] = $_FILES[$name];

            $this->transformToMultiple();

            // remove empty filenames
            // only for addMore option
            $this->removeEmptyFiles();

            // set field length (files count)
            $this->field['count'] = \count($this->field['input']['name']);
            return true;
        }
        return false;
    }

    /**
     * @param $destination
     * @param $quality
     * @param $imageType
     * @param $dest
     * @return bool
     */
    private static function outputImage($destination, $quality, $imageType, $dest)
    {
        switch ($imageType) {
            case IMAGETYPE_GIF:
                return imagegif($dest, $destination);
            case IMAGETYPE_JPEG:
                return imagejpeg($dest, $destination, $quality);
            case IMAGETYPE_PNG:
                return imagepng($dest, $destination, 10 - $quality / 10);
        }
    }

    /**
     * @param $destExt
     * @return int
     */
    private static function detectImageType($destExt): int
    {
        $imageType = IMAGETYPE_JPEG;
        if (\in_array($destExt, ['gif', 'jpg', 'jpeg', 'png'])) {
            if ($destExt === 'gif') {
                return IMAGETYPE_GIF;
            }
            if ($destExt === 'jpg' || $destExt == 'jpeg') {
                return IMAGETYPE_JPEG;
            }
            if ($destExt === 'png') {
                return IMAGETYPE_PNG;
            }
        }
        return $imageType;
    }

    /**
     * @param $destination
     * @return string
     */
    private static function getExtension($destination): string
    {
        return \strtolower(\substr($destination, \strrpos($destination, '.') + 1));
    }

    /**
     * upload method
     * Call the uploadFiles method
     *
     * @public
     * @return array
     */
    public function upload()
    {
        return $this->uploadFiles();
    }

    /**
     * getFileList method
     * Get the list of the appended and uploaded files
     *
     * @param string $customKey File attrbite that should be in the list
     *
     * @return array
     */
    public function getFileList($customKey = '')
    {
        $result = [];

        if (empty($customKey)) {
            return $this->options['files'];
        }
        foreach ($this->options['files'] as $key => $value) {
            $attribute = $this->getFileAttribute($value, $customKey);
            $result[] = $attribute ? $attribute : $value['file'];
        }

        return $result;
    }

    /**
     * getRemovedFiles method
     * Get removed files as array
     *
     * @public
     *
     * @param $customKey {String} The file attribute which is also defined in listInput element
     *
     * @return array
     */
    public function getRemovedFiles($customKey = 'file')
    {
        $removedFiles = [];

        if (\is_array($this->field['listInput']['list']) && \is_array($this->options['files'])) {
            foreach ($this->options['files'] as $key => $value) {
                if (!\in_array($this->getFileAttribute($value, $customKey),
                        $this->field['listInput']['list']) && (!isset($value['uploaded']) || !$value['uploaded'])) {
                    $removedFiles[] = $value;
                    unset($this->options['files'][$key]);
                }
            }
        }

        if (\is_array($this->options['files'])) {
            $this->options['files'] = \array_values($this->options['files']);
        }
        return $removedFiles;
    }

    /**
     * getListInput method
     * Get the listInput value as null or array
     *
     * @public
     * @return array
     */
    public function getListInput()
    {
        return $this->field['listInput'];
    }

    /**
     * generateInput method
     * Generate a string with HTML input
     *
     * @public
     * @return string
     */
    public function generateInput()
    {
        $attributes = [];

        // process options
        foreach (\array_merge(['name' => $this->field['name']], $this->options) as $key => $value) {
            if ($value) {
                switch ($key) {
                    case 'limit':
                    case 'maxSize':
                    case 'fileMaxSize':
                        $attributes['data-fileuploader-' . $key] = $value;
                        break;
                    case 'listInput':
                        $attributes['data-fileuploader-' . $key] = \is_bool($value) ? \var_export($value, true) : $value;
                        break;
                    case 'extensions':
                        $attributes['data-fileuploader-' . $key] = \implode(',', $value);
                        break;
                    case 'name':
                        $attributes[$key] = $value;
                        break;
                    case 'required':
                        $attributes[$key] = '';
                        break;
                    case 'files':
                        $value = array_values($value);
                        $attributes['data-fileuploader-' . $key] = \json_encode($value);
                        break;
                }
            }
        }

        // generate input attributes
        $dataAttributes = \array_map(function ($value, $key) {
            return $key . "='" . (\str_replace("'", '"', $value)) . "'";
        }, \array_values($attributes), \array_keys($attributes));

        return '<input type="file"' . \implode(' ', $dataAttributes) . '>';
    }

    /**
     * resize method
     * Resize, crop and rotate images
     *
     * @public
     * @static
     *
     * @param $filename    {String} file source
     * @param $width       {Number} new width
     * @param $height      {Number} new height
     * @param $destination {String} file destination
     * @param $crop        {boolean, Array} crop property
     * @param $quality     {Number} quality of destination
     * @param $rotation    {Number} rotation degrees
     *
     * @return bool resizing was successful
     */
    public static function resize($filename, $width = null, $height = null, $destination = null, $crop = false, $quality = 90, $rotation = 0) {
        if (!is_file($filename) || !is_readable($filename)) {
            return false;
        }

        $source = null;
        $destination = !$destination ? $filename : $destination;
        if (\file_exists($destination) && !\is_writable($destination)) {
            return false;
        }
        $imageInfo = \getimagesize($filename);
        if (!$imageInfo) {
            return false;
        }

        // detect actions
        $hasRotation = $rotation;
        $hasCrop = \is_array($crop) || $crop === true;
        $hasResizing = $width || $height;

        if (!$hasRotation && !$hasCrop && !$hasResizing) {
            return false;
        }

        // store image information
        list ($imageWidth, $imageHeight, $imageType) = $imageInfo;

        $source = self::createGdImage($imageType, $filename);

        // rotation
        if ($hasRotation) {
            if ($rotation == 90 || $rotation == 270) {
                $cacheWidth = $imageWidth;
                $cacheHeight = $imageHeight;

                $imageWidth = $cacheHeight;
                $imageHeight = $cacheWidth;
            }
            $rotation = $rotation * -1;
            $source = imagerotate($source, $rotation, 0);
        }

        // crop
        $crop = \array_merge([
            'left'       => 0,
            'top'        => 0,
            'width'      => $imageWidth,
            'height'     => $imageHeight,
            '_paramCrop' => $crop,
        ], \is_array($crop) ? $crop : []);
        if (\is_array($crop['_paramCrop'])) {
            $crop['left'] = $crop['_paramCrop']['left'];
            $crop['top'] = $crop['_paramCrop']['top'];
            $crop['width'] = $crop['_paramCrop']['width'];
            $crop['height'] = $crop['_paramCrop']['height'];
        }

        // set default $width and $height
        $width = !$width ? $crop['width'] : $width;
        $height = !$height ? $crop['height'] : $height;

        // resize
        if ($crop['width'] < $width && $crop['height'] < $height) {
            $width = $crop['width'];
            $height = $crop['height'];
            $hasResizing = false;
        }
        if ($hasResizing) {
            $ratio = $crop['width'] / $crop['height'];

            if ($crop['_paramCrop'] === true) {
                if ($crop['width'] > $crop['height']) {
                    $crop['width'] = \ceil($crop['width'] - ($crop['width'] * \abs($ratio - $width / $height)));
                } else {
                    $crop['height'] = \ceil($crop['height'] - ($crop['height'] * \abs($ratio - $width / $height)));
                }
            } else {
                if ($width / $height > $ratio) {
                    $width = $height * $ratio;
                } else {
                    $height = $width / $ratio;
                }
            }
        }

        // save
        $dest = null;
        $destExt = self::getExtension($destination);

        if (\pathinfo($destination, PATHINFO_EXTENSION)) {
            $imageType = self::detectImageType($destExt);
        } else {
            $destination .= '.jpg';
        }

        $dest = self::manipulateImage($imageType, $width, $height, $source);

        \imageinterlace($dest, true);

        \imagecopyresampled(
            $dest,
            $source,
            0,
            0,
            $crop['left'],
            $crop['top'],
            $width,
            $height,
            $crop['width'],
            $crop['height']
        );

        self::outputImage($destination, $quality, $imageType, $dest);

        \imagedestroy($source);
        \imagedestroy($dest);

        return true;
    }

    /**
     * @param int $imageType
     * @param int $width
     * @param int $height
     * @param resource $source
     * @return bool|resource
     */
    private static function manipulateImage($imageType, $width, $height, $source)
    {
        switch($imageType) {
            case IMAGETYPE_GIF:
                $dest = imagecreatetruecolor($width, $height);
                $background = imagecolorallocatealpha($dest, 255, 255, 255, 1);
                imagecolortransparent($dest, $background);
                imagefill($dest, 0, 0 , $background);
                imagesavealpha($dest, true);
                return $dest;
            case IMAGETYPE_JPEG:
                $dest = imagecreatetruecolor($width, $height);
                $background = imagecolorallocate($dest, 255, 255, 255);
                imagefilledrectangle($dest, 0, 0, $width, $height, $background);
                return $dest;
            case IMAGETYPE_PNG:
                if (!imageistruecolor($source)) {
                    $dest = imagecreate($width, $height);
                    $background = imagecolorallocatealpha($dest, 255, 255, 255, 1);
                    imagecolortransparent($dest, $background);
                    imagefill($dest, 0, 0 , $background);
                } else {
                    $dest = imagecreatetruecolor($width, $height);
                }
                imagealphablending($dest, false);
                imagesavealpha($dest, true);
                return $dest;
            default:
                return false;
        }
    }

    /**
     * uploadFiles method
     * Process and upload the files
     *
     * @private
     * @return null|array
     */
    private function uploadFiles()
    {
        $data = [
            "hasWarnings" => false,
            "isSuccess"   => false,
            "warnings"    => [],
            "files"       => [],
        ];
        $listInput = $this->field['listInput'];
        $uploadDir = $this->getUploadDirectory();
        $chunk = $this->isChunk();

        if ($this->field['input']) {
            // validate ini settings and some generally options
            $validate = $this->validate();
            $data['isSuccess'] = true;

            if (true === $validate) {
                // process the files
                for ($i = 0; $i < \count($this->field['input']['name']); $i++) {
                    $file = [
                        'name'     => $this->field['input']['name'][$i],
                        'tmp_name' => $this->field['input']['tmp_name'][$i],
                        'type'     => $this->field['input']['type'][$i],
                        'error'    => $this->field['input']['error'][$i],
                        'size'     => $this->field['input']['size'][$i],
                    ];

                    // chunk
                    if ($chunk) {
                        if (isset($chunk['isFirst'])) {
                            $chunk['temp_name'] = $this->random_string(6) . \time();
                        }

                        $tmp_name = $uploadDir . '.unconfirmed_' . $this->filterFilename($chunk['temp_name']);
                        if (!isset($chunk['isFirst']) && !file_exists($tmp_name)) {
                            continue;
                        }
                        $sp = fopen($file['tmp_name'], 'r');
                        $op = fopen($tmp_name, isset($chunk['isFirst']) ? 'w' : 'a');
                        while (!feof($sp)) {
                            $buffer = fread($sp, 512);
                            fwrite($op, $buffer);
                        }

                        // close handles
                        fclose($op);
                        fclose($sp);

                        if (isset($chunk['isLast'])) {
                            $file['tmp_name'] = $tmp_name;
                            $file['name'] = $chunk['name'];
                            $file['type'] = $chunk['type'];
                            $file['size'] = $chunk['size'];
                        } else {
                            echo json_encode([
                                'fileuploader' => [
                                    'temp_name' => $chunk['temp_name'],
                                ],
                            ]);
                            exit;
                        }
                    }

                    $metas = [];
                    $metas['tmp_name'] = $file['tmp_name'];
                    $metas['extension'] = \strtolower(\substr(\strrchr($file['name'], "."), 1));
                    $metas['type'] = $file['type'];
                    $metas['old_name'] = $file['name'];
                    $metas['old_title'] = \substr($metas['old_name'], 0,
                        (\strlen($metas['extension']) > 0 ? -(\strlen($metas['extension']) + 1) : \strlen($metas['old_name'])));
                    $metas['size'] = $file['size'];
                    $metas['size2'] = $this->formatSize($file['size']);
                    $metas['name'] = $this->generateFileName($this->options['title'], [
                        'title'     => $metas['old_title'],
                        'size'      => $metas['size'],
                        'extension' => $metas['extension'],
                    ]);
                    $metas['title'] = substr($metas['name'], 0,
                        (\strlen($metas['extension']) > 0 ? -(\strlen($metas['extension']) + 1) : \strlen($metas['name'])));
                    $metas['file'] = $uploadDir . $metas['name'];
                    $metas['replaced'] = \file_exists($metas['file']);
                    $metas['date'] = \date('r');
                    $metas['error'] = $file['error'];
                    $metas['editor'] = $this->options['editor'] != null;
                    $metas['chunked'] = $chunk;
                    \ksort($metas);

                    // validate file
                    $validateFile = $this->validate(\array_merge($metas, ['index' => $i, 'tmp' => $file['tmp_name']]));

                    // check if file is in listInput
                    $listInputName = '0:/' . $metas['old_name'];
                    $fileInList = null === $listInput || \in_array($listInputName, $listInput['list']);

                    // add file to memory
                    if (true === $validateFile) {
                        if ($fileInList) {
                            $fileListIndex = 0;

                            if ($listInput) {
                                $fileListIndex = \array_search($listInputName, $listInput['list']);
                                if (isset($listInput['values'][$fileListIndex]['editor'])) {
                                    $metas['editor'] = $listInput['values'][$fileListIndex]['editor'];
                                }
                                if (isset($listInput['values'][$fileListIndex]['index'])) {
                                    $metas['index'] = $listInput['values'][$fileListIndex]['index'];
                                }
                            } elseif (isset($_POST['_editorr']) && $this->isJSON($_POST['_editorr']) && \count($this->field['input']['name']) === 1) {
                                $metas['editor'] = \json_decode($_POST['_editorr'], true);
                            }

                            $data['files'][] = $metas;

                            if ($listInput) {
                                unset($listInput['list'][$fileListIndex]);
                                unset($listInput['values'][$fileListIndex]);
                            }
                        }
                    } else {
                        if ($metas['chunked'] && \file_exists($metas['tmp_name'])) {
                            \unlink($metas['tmp_name']);
                        }
                        if (!$fileInList) {
                            continue;
                        }

                        $data['isSuccess'] = false;
                        $data['hasWarnings'] = true;
                        $data['warnings'][] = $validateFile;
                        $data['files'] = [];
                        break;
                    }
                }

                // upload the files
                if (!$data['hasWarnings']) {
                    foreach ($data['files'] as $key => $file) {
                        if ($file['chunked'] ? \rename($file['tmp_name'], $file['file']) : $this->options['move_uploaded_file']($file['tmp_name'],
                            $file['file'])) {
                            unset($data['files'][$key]['chunked']);
                            unset($data['files'][$key]['error']);
                            unset($data['files'][$key]['tmp_name']);
                            $data['files'][$key]['uploaded'] = true;
                            $this->options['files'][] = $data['files'][$key];
                        } else {
                            unset($data['files'][$key]);
                        }
                    }
                }
            } else {
                $data['isSuccess'] = false;
                $data['hasWarnings'] = true;
                $data['warnings'][] = $validate;
            }
        } else {
            $lastPHPError = \error_get_last();
            if ($lastPHPError && $lastPHPError['type'] == E_WARNING && $lastPHPError['line'] == 0) {
                $errorMessage = null;

                if (false !== \strpos($lastPHPError['message'], "POST Content-Length")) {
                    $errorMessage = $this->codeToMessage(UPLOAD_ERR_INI_SIZE);
                }
                if (false !== \strpos($lastPHPError['message'], "Maximum number of allowable file uploads")) {
                    $errorMessage = $this->codeToMessage('max_number_of_files');
                }

                if ($errorMessage != null) {
                    $data['isSuccess'] = false;
                    $data['hasWarnings'] = true;
                    $data['warnings'][] = $errorMessage;
                }

            }

            if ($this->options['required'] && (isset($_SERVER) && \strtolower($_SERVER['REQUEST_METHOD']) === 'post')) {
                $data['hasWarnings'] = true;
                $data['warnings'][] = $this->codeToMessage('required_and_no_file');
            }
        }

        // call file editor
        $this->editFiles();

        // call file sorter
        $this->sortFiles($data['files']);

        return $data;
    }

    /**
     * validation method
     * Check ini settings, field and files
     *
     * @private
     *
     * @param $file array File metas
     *
     * @return bool|string
     */
    private function validate($file = [])
    {
        if (empty($file)) {
            // check ini settings and some generally options
            $ini = $this->getIniSettings();

            if (!$ini[0]) {
                return $this->codeToMessage('file_uploads');
            }
            if ($this->options['required'] && (isset($_SERVER) && \strtolower($_SERVER['REQUEST_METHOD']) === "post") && ($this->field['count'] + \count($this->options['files'])) === 0) {
                return $this->codeToMessage('required_and_no_file');
            }
            if (($this->options['limit'] && $this->field['count'] + \count($this->options['files']) > $this->options['limit']) || ($ini[3] !== 0 && ($this->field['count']) > $ini[3])) {
                return $this->codeToMessage('max_number_of_files');
            }
            if (!file_exists($this->options['uploadDir']) && !\is_writable($this->options['uploadDir'])) {
                return $this->codeToMessage('invalid_folder_path');
            }

            $totalSize = 0;
            foreach ($this->field['input']['size'] as $key => $value) {
                $totalSize += $value;
            }
            $totalSize = $totalSize / 1000000;
            if ($ini[2] !== 0 && $totalSize > $ini[2]) {
                return $this->codeToMessage('post_max_size');
            }
            if ($this->options['maxSize'] && $totalSize > $this->options['maxSize']) {
                return $this->codeToMessage('max_files_size');
            }
        } else {
            // check file
            if ($file['error'] > 0) {
                return $this->codeToMessage($file['error'], $file);
            }
            if ($this->options['extensions'] && (!\in_array(strtolower($file['extension']),
                        $this->options['extensions']) && !\in_array(\strtolower($file['type']), $this->options['extensions']))) {
                return $this->codeToMessage('accepted_file_types', $file);
            }
            if ($this->options['fileMaxSize'] && $file['size'] / 1000000 > $this->options['fileMaxSize']) {
                return $this->codeToMessage('max_file_size', $file);
            }
            if ($this->options['maxSize'] && $file['size'] / 1000000 > $this->options['maxSize']) {
                return $this->codeToMessage('max_file_size', $file);
            }
            $custom_validation = $this->options['validate_file']($file, $this->options);
            if (true !== $custom_validation) {
                return $custom_validation;
            }
        }

        return true;
    }

    /**
     * getListInputFiles method
     * Get value from listInput
     *
     * @private
     *
     * @param $name string FileUploader $_FILES name
     *
     * @return null|array
     */
    private function getListInputFiles($name = null)
    {
        $inputName = 'fileuploader-list-' . ($name ? $name : $this->field['name']);
        if (\is_string($this->options['listInput'])) {
            $inputName = $this->options['listInput'];
        }

        if (isset($_POST[$inputName]) && $this->isJSON($_POST[$inputName])) {
            $list = [
                'list'   => [],
                'values' => \json_decode($_POST[$inputName], true),
            ];
            foreach ($list['values'] as $key => $value) {
                $list['list'][] = $value['file'];
            }
            return $list;
        }
        return null;
    }

    /**
     * editFiles method
     * Edit all files that have an editor from Front-End
     *
     * @private
     * @return void
     */
    private function editFiles()
    {
        $files = $this->getFileList();
        $listInput = $this->field['listInput'];

        foreach ($files as $key => $file) {
            $listInputName = $file['file'];
            $fileListIndex = \is_array($listInput['list']) ? \array_search($listInputName, $listInput['list']) : false;

            // add editor to appended files if available
            if ($fileListIndex !== false && isset($listInput['values'][$fileListIndex]['editor'])) {
                $file['editor'] = $this->options['files'][$key]['editor'] = $listInput['values'][$fileListIndex]['editor'];
            }

            // edit file
            if (isset($file['editor']) && file_exists($file['file']) && strpos($file['type'], 'image/') === 0) {
                $width = isset($this->options['editor']['maxWidth']) ? $this->options['editor']['maxWidth'] : null;
                $height = isset($this->options['editor']['maxHeight']) ? $this->options['editor']['maxHeight'] : null;
                $quality = isset($this->options['editor']['quality']) ? $this->options['editor']['quality'] : 90;
                $rotation = isset($file['editor']['rotation']) ? $file['editor']['rotation'] : 0;
                $crop = isset($this->options['editor']['crop']) ? $this->options['editor']['crop'] : false;
                $crop = isset($file['editor']['crop']) ? $file['editor']['crop'] : $crop;

                // edit
                self::resize($file['file'], $width, $height, null, $crop, $quality, $rotation);
                unset($this->options['files'][$key]['editor']);
            }
        }
    }

    /**
     * sortFiles method
     * Sort all files that have an index from Front-End
     *
     * @private
     *
     * @param $data - file list that also needs to be sorted
     *
     * @return void
     */
    private function sortFiles(&$data = null)
    {
        $files = $this->getFileList();
        $listInput = $this->field['listInput'];

        foreach ($files as $key => $file) {
            $listInputName = $file['file'];
            $fileListIndex = \is_array($listInput['list']) ? \array_search($listInputName, $listInput['list']) : false;

            // add index to appended files if available
            if ($fileListIndex !== false && isset($listInput['values'][$fileListIndex]['index'])) {
                $this->options['files'][$key]['index'] = $listInput['values'][$fileListIndex]['index'];
            }
        }

        if (isset($this->options['files'][0]['index'])) {
            \usort($this->options['files'], function ($a, $b) {
                global $freeIndex;

                if (!isset($a['index'])) {
                    $a['index'] = $freeIndex;
                    $freeIndex++;
                }

                if (!isset($b['index'])) {
                    $b['index'] = $freeIndex;
                    $freeIndex++;
                }

                return $a['index'] - $b['index'];
            });
        }

        if ($data && isset($data[0]['index'])) {
            $freeIndex = \count($data);
            \usort($data, function ($a, $b) {
                global $freeIndex;

                if (!isset($a['index'])) {
                    $a['index'] = $freeIndex;
                    $freeIndex++;
                }

                if (!isset($b['index'])) {
                    $b['index'] = $freeIndex;
                    $freeIndex++;
                }

                return $a['index'] - $b['index'];
            });
        }
    }

    /**
     * clean_chunked_files method
     * Remove chunked files from directory
     *
     * @public
     * @static
     *
     * @param $directory string Directory scan
     * @param $time      string Time difference
     */
    public static function cleanChunkedFiles($directory, $time = '-1 hour')
    {
        if (!\is_dir($directory)) {
            return;
        }

        $dir = \scandir($directory);
        $files = \array_diff($dir, ['.', '..']);
        foreach ($files as $key => $name) {
            $file = $directory . $name;
            if (\strpos($name, '.unconfirmed_') === 0 && \filemtime($file) < \strtotime($time)) {
                \unlink($file);
            }
        }
    }

    /**
     * codeToMessage method
     * Translate a warning code into text
     *
     * @param $code string
     * @param $file array
     *
     * @return string
     */
    private function codeToMessage($code, $file = [])
    {
        $message = null;

        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            case 'accepted_file_types':
                $message = "File type is not allowed for " . $file['old_name'];
                break;
            case 'file_uploads':
                $message = "File uploading option in disabled in php.ini";
                break;
            case 'max_file_size':
                $message = $file['old_name'] . " is too large";
                break;
            case 'max_files_size':
                $message = "Files are too big";
                break;
            case 'max_number_of_files':
                $message = "Maximum number of files is exceeded";
                break;
            case 'required_and_no_file':
                $message = "No file was choosed. Please select one";
                break;
            case 'invalid_folder_path':
                $message = "Upload folder doesn't exist or is not writable";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }

        return $message;
    }

    /**
     * @param array  $file
     * @param string $attribute
     *
     * @return mixed
     */
    private function getFileAttribute($file, $attribute)
    {
        $result = null;
        if (isset($file['data'][$attribute])) {
            $result = $file['data'][$attribute];
        }
        if (isset($file[$attribute])) {
            $result = $file[$attribute];
        }

        return $result;
    }

    /**
     * formatSize method
     * Cover bytes to readable file size format
     *
     * @private
     *
     * @param int $bytes
     *
     * @return string
     */
    private function formatSize(int $bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes > 0) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return '0 bytes';
    }

    /**
     * isJson method
     * Check if string is a valid json
     *
     * @private
     *
     * @param string $string
     *
     * @return bool
     */
    private function isJson(string $string)
    {
        \json_decode($string);
        return (\json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * filterFilename method
     * Remove invalid characters from filename
     *
     * @private
     *
     * @param string $filename
     *
     * @return string
     */
    private function filterFilename(string $filename)
    {
        $delimiter = '_';
        $invalidCharacters = \array_merge(\array_map('chr', \range(0, 31)), ["<", ">", ":", '"', "/", "\\", "|", "?", "*"]);

        // remove invalid characters
        $filename = \str_replace($invalidCharacters, $delimiter, $filename);
        // remove duplicate delimiters
        $filename = \preg_replace('/(' . \preg_quote($delimiter, '/') . '){2,}/', '$1', $filename);

        return $filename;
    }

    /**
     * generateFileName method
     * Generated a new file name
     *
     * @private
     *
     * @param $conf               {null, String, Array} FileUploader title option
     * @param $file               {Array} File data as title, extension and size
     * @param $skip_replace_check {boolean} Used only for recursive auto generating file name to exclude replacements
     *
     * @return string
     */
    private function generateFilename($conf, $file, $skip_replace_check = false)
    {
        $conf = !\is_array($conf) ? [$conf] : $conf;
        $type = $conf[0];
        $length = isset($conf[1]) ? (int) $conf[1] : 12;
        $random_string = $this->random_string($length);
        $extension = !empty($file['extension']) ? "." . $file['extension'] : "";

        switch ($type) {
            case null:
            case "auto":
                $string = $random_string;
                break;
            case "name":
                $string = $file['title'];
                break;
            default:
                $string = $type;
                $string_extension = \substr(\strrchr($string, "."), 1);

                $string = \str_replace("{random}", $random_string, $string);
                $string = \str_replace("{file_name}", $file['title'], $string);
                $string = \str_replace("{file_size}", $file['size'], $string);
                $string = \str_replace("{timestamp}", time(), $string);
                $string = \str_replace("{date}", date('Y-n-d_H-i-s'), $string);
                $string = \str_replace("{extension}", $file['extension'], $string);

                if (!empty($string_extension)) {
                    if ($string_extension != "{extension}") {
                        $type = \substr($string, 0, -(strlen($string_extension) + 1));
                        $extension = $file['extension'] = $string_extension;
                    } else {
                        $type = \substr($string, 0, -(strlen($file['extension']) + 1));
                        $extension = '';
                    }
                }
        }
        if ($extension && !\preg_match('/' . $extension . '$/', $string)) {
            $string .= $extension;
        }

        // generate another filename if a file with the same name already exists
        // only when replace options is true
        if (!$this->options['replace'] && !$skip_replace_check) {
            $title = $file['title'];
            $i = 1;
            while (\file_exists($this->options['uploadDir'] . $string)) {
                $file['title'] = $title . " ({$i})";
                $conf[0] = $type == "auto" || $type == "name" || strpos($string, "{random}") !== false ? $type : $type . " ({$i})";
                $string = $this->generateFileName($conf, $file, true);
                $i++;
            }
        }

        return $this->filterFilename($string);
    }

    /**
     * random_string method
     * Generate a random string
     *
     * @public
     *
     * @param int $length Number of characters
     *
     * @return string
     */
    private function random_string(int $length = 12)
    {
        return \substr(\str_shuffle("_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
    }

    /**
     * mime_content_type method
     * Get the mime_content_type of a file
     *
     * @param string $file File location
     *
     * @return string
     */
    public static function mime_content_type($file)
    {
        if (\function_exists('mime_content_type')) {
            return \mime_content_type($file);
        }
        $mime_types = [
            'txt'  => 'text/plain',
            'htm'  => 'text/html',
            'html' => 'text/html',
            'php'  => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'swf'  => 'application/x-shockwave-flash',
            'flv'  => 'video/x-flv',

            // images
            'png'  => 'image/png',
            'jpe'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'ico'  => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif'  => 'image/tiff',
            'svg'  => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip'  => 'application/zip',
            'rar'  => 'application/x-rar-compressed',
            'exe'  => 'application/x-msdownload',
            'msi'  => 'application/x-msdownload',
            'cab'  => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3'  => 'audio/mpeg',
            'mp4'  => 'video/mp4',
            'webM' => 'video/webm',
            'qt'   => 'video/quicktime',
            'mov'  => 'video/quicktime',

            // adobe
            'pdf'  => 'application/pdf',
            'psd'  => 'image/vnd.adobe.photoshop',
            'ai'   => 'application/postscript',
            'eps'  => 'application/postscript',
            'ps'   => 'application/postscript',

            // ms office
            'doc'  => 'application/msword',
            'rtf'  => 'application/rtf',
            'xls'  => 'application/vnd.ms-excel',
            'ppt'  => 'application/vnd.ms-powerpoint',

            // open office
            'odt'  => 'application/vnd.oasis.opendocument.text',
            'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        ];
        $ext = \strtolower(\array_pop(\explode('.', $file)));

        if (\array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        if (\function_exists('finfo_open')) {
            $finfo = \finfo_open(FILEINFO_MIME);
            $mimeType = \finfo_file($finfo, $file);
            \finfo_close($finfo);
            return $mimeType;
        }
        return 'application/octet-stream';
    }

    /**
     * @return array
     */
    private function getIniSettings()
    {
        return [
            (boolean) \ini_get('file_uploads'),
            (int) \ini_get('upload_max_filesize'),
            (int) \ini_get('post_max_size'),
            (int) \ini_get('max_file_uploads'),
            (int) \ini_get('memory_limit'),
        ];
    }

    /**
     * remove empty filenames
     * only for addMore option
     *
     * @return $this
     */
    private function removeEmptyFiles()
    {
        foreach ($this->field['input']['name'] as $key => $value) {
            if (empty($value)) {
                unset($this->field['input']['name'][$key]);
                unset($this->field['input']['type'][$key]);
                unset($this->field['input']['tmp_name'][$key]);
                unset($this->field['input']['error'][$key]);
                unset($this->field['input']['size'][$key]);
            }
        }
        return $this;
    }

    /**
     * @return mixed
     */
    private function getUploadDirectory()
    {
        return \str_replace(\getcwd() . '/', '', $this->options['uploadDir']);
    }

    /**
     * @return bool|mixed
     */
    private function isChunk()
    {
        return isset($_POST['_chunkedd']) && \count($this->field['input']['name']) === 1 ? \json_decode($_POST['_chunkedd'], true) : false;
    }

    /**
     * tranform an no-multiple input to multiple
     * made only to simplify the next uploading steps
     */
    private function transformToMultiple()
    {
        if (!\is_array($this->field['input']['name'])) {
            $this->field['input'] = \array_merge($this->field['input'], [
                "name"     => [$this->field['input']['name']],
                "tmp_name" => [$this->field['input']['tmp_name']],
                "type"     => [$this->field['input']['type']],
                "error"    => [$this->field['input']['error']],
                "size"     => [$this->field['input']['size']],
            ]);
        }
        return $this;
    }

    /**
     * @param int $imageType
     * @param string $filename
     * @return bool|resource
     */
    private static function createGdImage(int $imageType, string $filename)
    {
        switch($imageType) {
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filename);
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filename);
            default:
                return false;
        }
    }
}