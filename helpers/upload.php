<?php
// Works well with ajaxUpload.js

function X_upload($name = null, $filePrefix = null, $acceptFilenameRegex = "#\w+#", $refuseFilenameRegex = "#/|\.\.|\.(php|sh|js|cgi|phtml|exe|bat|cmd)$#i", $bufferSize = 4096) {
    if ($filePrefix === null) $filePrefix = uniqid();
    if (strlen($filePrefix) > 0) $filePrefix .= '_';

    if ($name !== null && isset($_FILES[$name])) {
        $filename = $filePrefix.str_replace('&','',$_FILES[$name]['name']);
        if (preg_match($acceptFilenameRegex, $_FILES[$name]['name']) && !preg_match($refuseFilenameRegex, $_FILES[$name]['name']) && move_uploaded_file($_FILES[$name]['tmp_name'], UPLOAD_PATH.$filename)) {
            return UPLOAD_URL.$filename;
        }
    } else {
        if ($name === null) {
            if (isset($_SERVER['HTTP_X_FILE_NAME'])) {
                $name = $_SERVER['HTTP_X_FILE_NAME'];
            } else {
                return false;
            }
        }
        if (preg_match($acceptFilenameRegex, $name) && !preg_match($refuseFilenameRegex, $name)) {
            $filename = $filePrefix.str_replace('&','', $name);
            $filepath = UPLOAD_PATH.$filename;

            $upload = fopen("php://input", "r");
            $firstBunch = fread($upload, $bufferSize);
            if (strlen($firstBunch) > 0) {
                file_put_contents($filepath, $firstBunch);
                while (!feof($upload)) {
                    file_put_contents($filepath, fread($upload, $bufferSize), FILE_APPEND);
                }
            } else {
                fclose($upload);
                return false;
            }
            fclose($upload);

            return UPLOAD_URL.$filename;
        }
    }
    
    return false;
}
