<?php

function X_upload($name = null, $filePrefix = "", $acceptFilenameRegex = "#.+\..+#", $refuseFilenameRegex = "#/|\.\.|\.(php|sh|js|cgi|phtml|exe|bat|cmd)$#", $bufferSize = 4096) {
    if ($name === null) {
        $name = isset($_SERVER['HTTP_X_FILE_NAME']) ? $_SERVER['HTTP_X_FILE_NAME'] : "";
        if (preg_match($acceptFilenameRegex, $name) && !preg_match($refuseFilenameRegex, $name)) {
            $filename = $filePrefix.time().'_'.str_replace('&','', $name);
            $filepath = DOCUMENT_ROOT."uploads/".$filename;

            $upload = fopen("php://input", "r");
            $firstBunch = fread($upload, $bufferSize);
            if (strlen($firstBunch) > 0) {
                file_put_contents($filepath, $firstBunch);
                while (!feof($upload)) {
                    file_put_contents($filepath, fread($upload, $bufferSize), FILE_APPEND);
                }
            } else return false;
            fclose($upload);

            return UPLOAD_URL.$filename;
        }
    } else {
        if (isset($_FILES[$name])) {
            $filename = $filePrefix.time().'_'.str_replace('&','',$_FILES[$name]['name']);
            if (preg_match($acceptFilenameRegex, $_FILES[$name]['name']) && !preg_match($refuseFilenameRegex, $_FILES[$name]['name']) && move_uploaded_file($_FILES[$name]['tmp_name'], UPLOAD_PATH.$filename)) {
                return UPLOAD_URL.$filename;
            }
        }
    }
    
    return false;
}
