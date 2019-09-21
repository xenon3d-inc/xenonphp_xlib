<?php
// Works well with ImageProcessor
// Requires   X_js(XLIB_PATH.'helpers/ajaxUpload.js');

function X_simpleImageUpload($name, $src, $defaultValue, $imageprocessor, $autoSubmit = false) {?>
<div ondrop="dropUploadFile(event, this.querySelector('input[type=file]'));" style="position:relative;">
    <img src="<?=$src? $src.$imageprocessor : $defaultValue?>" alt="<?=$name?>"><br>
    <input type="hidden" name="<?=$name?>" value="<?=$src?>">
    <input type="file"
        style="
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            opacity: 0;
        "
        onchange="
            var elem = this;
            dropUploadFile(event, '', {
                singleCompleteCallback: function(index, filepath){
                    $(elem).parent().find('img').attr('src', filepath+'<?=$imageprocessor?>');
                    $(elem).parent().find('input[name=<?=$name?>]').val(filepath).trigger('change');
                    <?php if ($autoSubmit) {?>
                        $(elem.form).submit();
                    <?php }?>
                }
            });
        "
    >
</div>
<?php
}
