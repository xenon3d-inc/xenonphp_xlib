<?php
namespace Xenon\CMS;

class StaticCKEditor {
    protected $name;
    protected $filepath = null;
    protected static $model = null;
    protected static $keyField = null;
    protected static $contentField = null;

    public static $enable_editing = false;

    protected $editorWidth;
    public static $default_editorWidth = 'auto';

    protected $editorHeight;
    public static $default_editorHeight = 400;

    protected $imageProcessorParams = "";
    public static $default_imageProcessorParams = "";

    protected static $CKEditorLoaded = false;

    public function __construct($name) {
        if (!preg_match("#^\w+$#", $name)) {
            throw new Exception("StaticCKEditor Name can only contain letters, numbers and _");
        }

        $this->name = $name;
        $this->filepath = self::getContentFilePathFromName($this->name);

        // Default configs
        $this->editorWidth = self::$default_editorWidth;
        $this->editorHeight = self::$default_editorHeight;
        $this->imageProcessorParams = self::$default_imageProcessorParams;
    }

    public static function setDbContent($model, $keyField, $contentField) {
        self::$model = $model;
        self::$keyField = $keyField;
        self::$contentField = $contentField;
    }

    public function setEditorWidth($width) {
        $this->editorWidth = $width;
        return $this;
    }

    public function setEditorHeight($height) {
        $this->editorHeight = $height;
        return $this;
    }

    public function setImageProcessorParams($params) {
        $this->imageProcessorParams = $params;
        return $this;
    }

    public function savePostContent($editCondition = true) {
        if ($editCondition && self::$enable_editing && isset($_POST['content_StaticCKEditor_'.$this->name])) {
            $value = $_POST['content_StaticCKEditor_'.$this->name];
            if (self::$model) {
                $content = (new \Xenon\Db\Query\Select(self::$model))->where(self::$keyField, $this->name)->fetchRow();
                $model = self::$model;
                if (!$content) $content = new $model;
                $content->{self::$keyField} = $this->name;
                $content->{self::$contentField} = $value;
                $content->save();
            } else {
                file_put_contents($this->filepath, $value);
            }
            if (AJAX) {
                exit;
            } else {
                if (!headers_sent()) {
                    header("Location: ".$_SERVER['REQUEST_URI']);
                    exit;
                }
            }
        }
        return $this;
    }

    public static function beginForm() {
        ?>
        <div class="staticCKEditor_form_container">
            <form class="staticCKEditor_form" action="" method="post">
                <div class="staticCKEditor_submit">
                    <input type="submit" name="submit_StaticCKEditor" value="<?=LANG=='fr'?"Sauvegarder":"Save"?>" />
                    <br />
                </div>
        <?php
    }

    public function showEditor($showForm = false) {
        if ($showForm) self::beginForm();
        $this->preloadCKEditor();
        ?>
        <div class="staticCKEditor_container" id="staticCKEditor_container_<?=$this->name?>">
            <textarea id="staticCKEditor_<?=$this->name?>" name="content_StaticCKEditor_<?=$this->name?>"><?=self::getContent($this->name)?></textarea>
            <input type="hidden" name="submit_StaticCKEditor" value="1"/>
            <script type="text/javascript">
                if ( CKEDITOR.env.ie && CKEDITOR.env.version < 9 ) CKEDITOR.tools.enableHtml5Elements( document );
                CKEDITOR.replace("staticCKEditor_<?=$this->name?>", {
                    customConfig: false,
                    width: '<?=$this->editorWidth?>',
                    height: '<?=$this->editorHeight?>',
                    language: '<?=(LANG=='fr'?'fr-ca':LANG)?>',
                    toolbarGroups: [
                        { name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
                        { name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
                        { name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
                        { name: 'forms' },
                        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                        { name: 'paragraph',   groups: [ 'list', 'indent', 'blocks', 'align', 'bidi' ] },
                        { name: 'links' },
                        { name: 'insert' },
                        { name: 'styles' },
                        { name: 'colors' },
                        { name: 'tools' },
                        { name: 'others' },
                        { name: 'about' }
                    ],
                    filebrowserImageUploadUrl: "?uploadImage=<?=urlencode($this->imageProcessorParams)?>",
                    filebrowserImageBrowseUrl: "?browseImage=<?=urlencode($this->imageProcessorParams)?>"
                });
            </script>
        </div>
        <?php
        if ($showForm) self::endForm();
        return $this;
    }

    public static function endForm() {
        ?>
                <div class="staticCKEditor_submit">
                    <br />
                    <input type="submit" name="submit_StaticCKEditor" value="<?=LANG=='fr'?"Sauvegarder":"Save"?>" />
                </div>
            </form>
        </div>
        <?php
    }


    public static function uploadImage() {
        global $X_LAYOUT;
        if (isset($_GET['uploadImage'])) {
            $X_LAYOUT = null;
            $imageProcessorParams = urldecode($_GET['uploadImage']);
            $msg = "";
            $url = "";
            if ($_FILES['upload']['type'] == "image/jpeg" || $_FILES['upload']['type'] == "image/png" || $_FILES['upload']['type'] == "image/gif") {
                $url = X_upload('upload');
                if ($url) {
                    $url = $url.($imageProcessorParams? '?'.$imageProcessorParams:'');
                } else {
                    $msg = LANG=='fr'? "Une erreur s'est produite":"An error occured";
                    $url = "";
                }
            } else {
                $msg = LANG=='fr'? "Ce fichier n'est pas une image":"This file is not an image";
            }
            ?>
            <html><body><script type="text/javascript">window.parent.CKEDITOR.tools.callFunction(<?=$_GET['CKEditorFuncNum']?>,"<?=$url?>","<?=$msg?>");</script></body></html>
            <?php
            exit;
        }
    }

    public static function browseImage() {
        global $X_LAYOUT;
        if (isset($_GET['browseImage'])) {
            $X_LAYOUT = null;
            $imageProcessorParams = urldecode($_GET['browseImage']);
            $files = glob(UPLOAD_PATH."{*.jpg,*.png,*.gif,*.jpeg}", GLOB_BRACE);
            ?>
            <style type="text/css">
                .browse_images .image_thumb_container {
                    display: inline-block;
                    width: 160px;
                    border: solid 1px #ccc;
                    border-radius: 10px;
                    padding: 10px;
                    cursor: pointer;
                }
                .browse_images .image_thumb_container img {
                    display: block;
                }
                .browse_images .image_thumb_container label {
		    display: inline-block;
		    width: 160px;
		    text-overflow: ellipsis;
		    overflow: hidden;
                }
            </style>
            <div class="browse_images">
                <?php foreach ($files as $file) if (!preg_match("#&#", $file)) { $file = str_replace(DOCUMENT_ROOT, '/', $file); $filename = preg_replace("#^".preg_quote(UPLOAD_URL)."(\d{10}_)?#","", $file);?>
                <div class="image_thumb_container" onclick="window.opener.CKEDITOR.tools.callFunction(<?=$_GET['CKEditorFuncNum']?>, '<?=$file.($imageProcessorParams? '?'.$imageProcessorParams:'')?>'); window.close();">
                    <img src="<?=$file?>?size=100x100&crop" alt="<?=$filename?>" />
                    <label><?=$filename?></label>
                </div>
                <?php }?>
            </div>
            <?php
            exit;
        }
    }

    public function preloadCKEditor() {
        if (!self::$CKEditorLoaded) {
            ?>
            <script src="//cdn.ckeditor.com/4.5.7/full/ckeditor.js"></script>
            <script>
                function staticCKEditor_inline_edit_function(elem, event) {
                    if (event !== undefined) {
                        event.stopPropagation();
                        event.preventDefault();
                    }
                    if (!$(elem).hasClass('editing')) {
                        $(elem).addClass('editing');
                        CKEDITOR.inline(elem.id, {
                            customConfig: false,
                            width: '<?=$this->editorWidth?>',
                            height: '<?=$this->editorHeight?>',
                            language: '<?=(LANG=='fr'?'fr-ca':LANG)?>',
                            toolbarGroups: [
                                // { name: 'document',	   groups: [ 'mode', 'document', 'doctools' ] },
                                // { name: 'clipboard',   groups: [ 'clipboard', 'undo' ] },
                                // { name: 'editing',     groups: [ 'find', 'selection', 'spellchecker' ] },
                                // { name: 'forms' },
                                { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                                // { name: 'paragraph',   groups: [ 'list', 'align' ] },
                                { name: 'links' },
                                // { name: 'insert' },
                                // { name: 'styles' },
                                { name: 'colors' },
                                // { name: 'tools' },
                                // { name: 'others' },
                                // { name: 'about' }
                            ],
                            autoParagraph: false,
                            enterMode : CKEDITOR.ENTER_BR,
                            shiftEnterMode: CKEDITOR.ENTER_BR,
                            filebrowserImageUploadUrl: '?uploadImage=<?=urlencode($this->imageProcessorParams)?>',
                            filebrowserImageBrowseUrl: '?browseImage=<?=urlencode($this->imageProcessorParams)?>'
                        });
                        elem.setAttribute('contenteditable', true);
                        elem.focus();
                    }
                }
                function staticCKEditor_inline_save_function(elem, async) {
                    if (CKEDITOR.instances[elem.id] && CKEDITOR.instances[elem.id].checkDirty()) {
                        var data = {
                            submit_StaticCKEditor:1
                        };
                        data['content_StaticCKEditor_'+$(elem).attr('data-name')] = CKEDITOR.instances[elem.id].getData();
                        $.ajax({
                            url: '',
                            type: 'post',
                            async: async,
                            data: data,
                            success: function() {
                                CKEDITOR.instances[elem.id].resetDirty();
                            }
                        });
                    }
                }
                window.onbeforeunload = function (evt) {
                    for (var instanceName in CKEDITOR.instances) {
                        staticCKEditor_inline_save_function(CKEDITOR.instances[instanceName].element.$, false);
                     }
                };
            </script>
            <?php
            self::$CKEditorLoaded = true;
        }
    }

    public function GetInlineEdit($editCondition = true) {
        $this->savePostContent($editCondition);
        $value = self::getContent($this->name);
        if ($editCondition && self::$enable_editing) {
            $this->preloadCKEditor();
            ?>
            <div id="staticCKEditor_inline_edit_container_<?=$this->name?>"
                 style="display: inline-block; outline: dotted 2px grey; min-width: 50px; min-height: 20px; margin: 2px;"
                 data-name="<?=$this->name?>"
                 onclick="staticCKEditor_inline_edit_function(this, event);"
                 onblur="staticCKEditor_inline_save_function(this, true)"
                 ><?=$value?></div>
            <?php
        } else {
            echo $value;
        }
    }

    public static function getContent($name) {
        if (self::$model) {
            $content = (new \Xenon\Db\Query\Select(self::$model))->where(self::$keyField, $name)->fetchRow();
            if ($content) return $content->{self::$contentField};
        } else {
            $filepath = self::getContentFilePathFromName($name);
            if (is_file($filepath)) {
                return file_get_contents($filepath);
            }
        }
    }

    protected static function getContentFilePathFromName($name) {
        return DATA_PATH . $name . '.staticCKEditor.content.html';
    }
}
