// ajaxUpload <?php
/*
 * Ajax File Upload, by Olivier St-Laurent
 * Version 2.0
 *
 * Drag-Drop support, except for IE of course...
 * Can upload very large files > 10GB+
 *
 *
 * Usage :
 * 
    <script><?php include XLIB_PATH.'helpers/ajaxUpload.js';?></script>
    <div ondrop="dropUploadFile(event, this.querySelector('input[type=file]'));">
        <input type="file" multiple="multiple" onchange="dropUploadFile(event, 'upload.php');" />
    </div>
 * 
 *
 *///?>

function dropUploadFile(event, url, options) {
    if (typeof url === 'function') {
        url(event);
    } else {
        if (typeof url === 'object') {
            var obj = url[0] || url;
            if (obj.ownerDocument
                && obj.ownerDocument.documentElement
                && obj.ownerDocument.documentElement.tagName
                && obj.ownerDocument.documentElement.tagName.toLowerCase() === 'html'
                ) {
                if (obj.tagName.toLowerCase() === 'input') {
                    obj.onchange(event);
                } else {
                    obj.ondrop(event);
                }
            } else {
                ajaxUpload(event, obj.url, obj);
            }
        } else {
            ajaxUpload(event, url, options);
        }
    }
    event.preventDefault();
}

var ajaxUploadIndexOffset = 0;
var ajaxUploadFileList = [];
function ajaxUpload(fileObject, url, options, sync) {
    options = $.extend({
        maxFiles : null,
        maxFileSize : null, // in Bytes
        allowedFileTypes : null, // Mime-types (ex: ['image/jpeg', 'image/png', 'image/gif'] )
        deniedFileTypes : null,
        progressCallback : function(pourcentage) {
            console.log(pourcentage + '%');
        },
        singleProgressCallback : function(index, pourcentage) {
            // Progress for individual files
        },
        startSingleUploadCallback : function(index, fileName, fileSize, fileType) {
            // Executed before each file upload
        },
        startUploadCallback : function(fileList) {
            // Executed before the first file upload
        },
        singleCompleteCallback : function(index, responseText) {
            // Executed for each file upload upon completion
        },
        completeCallback : function(event) {
            console.log('Upload Complete');
        },
        fileTooLargeCallback : null,
        fileTypeNotAllowedCallback : null,
        tooManyFilesCallback : null,
        noFileSelectedCallback : null,
        connectionErrorCallback : null,
        uploadErrorCallback : null,
        errorCallback : function(errorMessage) {
            console.log(errorMessage);
        }
    }, options);

    var fileList = null;
    if (typeof fileObject === 'string') {
        fileList = document.getElementById(fileObject) || $(fileObject)[0];
    } else {
        fileList = fileObject.dataTransfer || fileObject.target || fileObject;
    }
    fileList = fileList.files || fileList || [];
    if (fileList[0] === undefined && typeof fileList === 'object' && fileList.length > 0) {
        fileList = [fileList];
    }

    if (fileList.length > 0 && (!options.maxFiles || fileList.length <= options.maxFiles)) {
        var startAjaxUpload = function(i, indexOffset) {
            var fileToUpload = fileList[i], index = i + indexOffset;

            var fileSizeExceeded = (options.maxFileSize && fileToUpload.size > options.maxFileSize) ? true : false;

            var isFolder = (fileToUpload.type === '' && fileToUpload.size === 4096) ? true : false;

            var fileTypeNotAllowed = (!isFolder && (
                (options.allowedFileTypes && $.inArray(fileToUpload.type, options.allowedFileTypes) === -1)
                ||
                (options.deniedFileTypes && $.inArray(fileToUpload.type, options.deniedFileTypes) >= 0)
                )) ? true : false;

            if (!isFolder && !fileSizeExceeded && !fileTypeNotAllowed) {

                var xhr = new XMLHttpRequest();

                ajaxUploadFileList[index] = {
                    index : index,
                    file : fileToUpload,
                    xhr : xhr,
                    progress : 0,
                    completed : false,
                    error : false,
                    cancel : function() {
                        if (!this.completed) {
                            this.xhr.onreadystatechange = null;
                            this.xhr.abort();
                        }
                    }
                };

                if (typeof options.startSingleUploadCallback === 'function') {
                    options.startSingleUploadCallback(index, fileToUpload.name, fileToUpload.size, fileToUpload.type);
                }

                var uploadStatus = xhr.upload;
                uploadStatus.addEventListener("progress", function(ev) {
                    if (ev.lengthComputable) {
                        var uploadProgress = (ev.loaded / ev.total) * 100;
                        ajaxUploadFileList[index].progress = uploadProgress;
                        if (typeof options.singleProgressCallback === 'function') {
                            options.singleProgressCallback(index, parseInt(uploadProgress));
                        }
                        if (typeof options.progressCallback === 'function') {
                            options.progressCallback(parseInt(
                                (uploadProgress / fileList.length) + (i * (100 / fileList.length))
                                ));
                        }
                    }
                }, false);

                uploadStatus.addEventListener("error", function(ev) {
                    ajaxUploadFileList[index].error = true;
                    if (typeof options.uploadErrorCallback === 'function') {
                        options.uploadErrorCallback(ev);
                    } else {
                        if (typeof options.errorCallback === 'function') {
                            options.errorCallback('Error while uploading.');
                            console.log(ev);
                        }
                    }
                }, false);

                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 1) {
                        // Connection Initiated

                    }
                    if (xhr.readyState == 4) {
                        if (xhr.status == 200) {
                            // Server Response Received
                            ajaxUploadFileList[index].completed = true;
                            if (typeof options.singleCompleteCallback === 'function') {
                                options.singleCompleteCallback(index, xhr.responseText);
                            }
                        } else {
                            if (xhr.status == 0) {
                                // Connection Error
                                ajaxUploadFileList[index].error = true;
                                if (typeof options.connectionErrorCallback === 'function') {
                                    options.connectionErrorCallback();
                                } else {
                                    if (typeof options.errorCallback === 'function') {
                                        options.errorCallback('Connection error');
                                    }
                                }
                            }
                        }
                    }
                };

                if (i + 1 < fileList.length) {
                    uploadStatus.addEventListener("load", function(ev) {
                        startAjaxUpload(i + 1, indexOffset);
                    }, false);
                } else {
                    if (typeof options.completeCallback === 'function') {
                        uploadStatus.addEventListener("load", function(ev) {
                            options.completeCallback(ev);
                        }, false);
                    }
                }

                xhr.open("POST", url, sync ? false : true);
                xhr.setRequestHeader("Cache-Control", "no-cache");
//                xhr.setRequestHeader("Content-Type", "multipart/form-data");
                xhr.setRequestHeader("X-REQUESTED-WITH", "xmlhttprequest");
                xhr.setRequestHeader("X-File-Name", fileToUpload.name.replace(/[^\w\.-]+/g,'_'));
                xhr.setRequestHeader("X-File-Size", fileToUpload.size);
                xhr.setRequestHeader("X-File-Type", fileToUpload.type || 'other');
                xhr.send(fileToUpload);
            } else {
                if (fileSizeExceeded) {
                    if (typeof options.fileTooLargeCallback === 'function') {
                        if (options.fileTooLargeCallback(options.maxFileSize) === false) {
                            if (typeof options.completeCallback === 'function') {
                                options.completeCallback(null);
                            }
                            return false;
                        }
                    } else {
                        if (typeof options.errorCallback === 'function') {
                            options.errorCallback('Maximum File Size Exceeded (' +
                                fileToUpload.size + ' > ' + options.maxFileSize + ' bytes)');
                        }
                    }
                }
                if (fileTypeNotAllowed) {
                    if (typeof options.fileTypeNotAllowedCallback === 'function') {
                        if (options.fileTypeNotAllowedCallback(fileToUpload.type) === false) {
                            if (typeof options.completeCallback === 'function') {
                                options.completeCallback(null);
                            }
                            return false;
                        }
                    } else {
                        if (typeof options.errorCallback === 'function') {
                            options.errorCallback('File Type Not Allowed (' + fileToUpload.type + ')');
                        }
                    }
                }
                if (i + 1 < fileList.length) {
                    startAjaxUpload(i + 1, indexOffset);
                } else {
                    if (typeof options.completeCallback === 'function') {
                        options.completeCallback(null);
                    }
                }
            }
        };
        if (typeof options.startUploadCallback === 'function') {
            options.startUploadCallback(fileList);
        }
        startAjaxUpload(0, ajaxUploadIndexOffset);
        ajaxUploadIndexOffset += fileList.length;
    } else {
        // Too Many Files
        if (options.maxFiles) {
            if (typeof options.tooManyFilesCallback === 'function') {
                options.tooManyFilesCallback(options.maxFiles);
            } else {
                if (typeof options.errorCallback === 'function') {
                    if (options.maxFiles === 1) {
                        options.errorCallback('You must select only one file');
                    } else {
                        options.errorCallback('You must select up to ' + options.maxFiles + ' files.');
                    }
                }
            }
        } else {
            if (typeof options.noFileSelectedCallback === 'function') {
                options.noFileSelectedCallback();
            } else {
                if (typeof options.errorCallback === 'function') {
                    options.errorCallback('You must select files');
                }
            }
        }
    }
}

function bindDragToBody() {
    var dragLeaveTimeout = null;
    $('body').first().on('dragenter dragover', function(event) {
        event.preventDefault();
        event.stopPropagation();
        $('body').addClass('dragover');
        if (dragLeaveTimeout !== null) {
            clearTimeout(dragLeaveTimeout);
        }
    });
    $('body').first().on('dragleave drop', function(event) {
        event.preventDefault();
        event.stopPropagation();
        if (dragLeaveTimeout !== null) {
            clearTimeout(dragLeaveTimeout);
        }
        dragLeaveTimeout = setTimeout(function() {
            $('body').removeClass('dragover');
        }, 300);
    });
}
