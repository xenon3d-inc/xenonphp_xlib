<?php
// Works well with ImageProcessor

function X_simpleImageUpload($name, $src, $defaultValue, $imageprocessor, $autoSubmit = false) {?>
<div ondrop="dropUploadFile(event, this.querySelector('input[type=file]'));">
    <img src="<?=$src? $src.$imageprocessor : $defaultValue?>"><br>
    <input type="hidden" name="<?=$name?>" value="<?=$src?>">
    <input type="file" onchange="
        var elem = this;
        dropUploadFile(event, '', {
            singleCompleteCallback: function(index, filepath){
                $(elem).parent().find('img').attr('src', filepath+'<?=$imageprocessor?>');
                $('input[name=<?=$name?>]').val(filepath);
                <?php if ($autoSubmit) {?>
                    $(elem.form).submit();
                <?php }?>
            }
        });
    ">
</div>
<?php
}
