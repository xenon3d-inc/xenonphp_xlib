<?php 
namespace Xenon\CMS;

class CodeEditor {
    public $filepath;
    public $ext;
    public $mimetype;
    public $theme;

    // Themes
    public $themes = [
        'abcdef',
        'ambiance',
        'blackboard',
        'cobalt',
        'colorforth',
        'eclipse',
        'elegant',
        'idea',
        'lesser-dark',
        'material',
        'midnight',
        'neat',
        'night',
        'nord',
        'pastel-on-dark',
        'rubyblue',
        'ssms',
        'the-matrix',
        'ttcn',
        'twilight',
        'vibrant-ink',
    ];

    public $codeMirrorCDN = "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.48.4/";

    public function __construct($filepath) {
        preg_match("#^(.*/)?([\w\./-]*)\.(\w{1,})$#", $filepath, $matches);
        $this->filepath = $filepath;
        $this->ext = $matches[3];
        switch ($this->ext) {
            case 'php':
            case 'phtml':
                $this->mimetype = "application/x-httpd-php";
            break;
            default:
                $this->mimetype = "text/plain";
            break;
        }
        $this->theme = !empty($_COOKIE['CodeEditorTheme'])? $_COOKIE['CodeEditorTheme'] : $this->themes[0];
    }
    public function saveOnPost() {
        if (!empty($_POST['CodeEditor_savefilecontents'])) {
            file_put_contents($this->filepath, $_POST['CodeEditor_savefilecontents']);
            if (AJAX && !PJAX) exit;
        }
        return $this;
    }
    public function renderThemeSelector() {
        ?>
        <select id="CodeEditorThemeSelect">
            <?php foreach ($this->themes as $t) {?>
                <option <?php if ($t == $this->theme) echo 'selected';?> value="<?=$t?>"><?=$t?></option>
            <?php }?>
        </select>
        <?php
        return $this;
    }
    public function renderFullEditor() {
        ?>
        <textarea id="CodeEditorTextarea"><?php echo htmlentities(file_get_contents($this->filepath));?></textarea>

        <link rel="stylesheet" type="text/css" href="<?=$this->codeMirrorCDN?>codemirror.css">
        <link rel="stylesheet" type="text/css" href="<?=$this->codeMirrorCDN?>theme/<?=$this->theme?>.css">
        <script src="<?=$this->codeMirrorCDN?>codemirror.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/htmlmixed/htmlmixed.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/xml/xml.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/javascript/javascript.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/css/css.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/clike/clike.js"></script>
        <script src="<?=$this->codeMirrorCDN?>mode/php/php.js"></script>
        <script src="<?=$this->codeMirrorCDN?>addon/selection/active-line.js"></script>
        <script src="<?=$this->codeMirrorCDN?>addon/edit/matchbrackets.js"></script>

        <script type="text/javascript">
            var codeEditorInstance;

            function CodeEditorSave() {
                showLoading();
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        'CodeEditor_savefilecontents': codeEditorInstance.getValue(),
                    },
                    success: function(response){
                        // Success
                    },
                    complete: function(){
                        hideLoading();
                    },
                    error: function(){
                        alert("Error saving the file");
                    }
                });
            }

            $(document).ready(function(){
                codeEditorInstance = CodeMirror.fromTextArea($("#CodeEditorTextarea")[0], {
                    mode: "<?=$this->mimetype?>",
                    lineNumbers: true,
                    matchBrackets: true,
                    theme: "<?=$this->theme?>",
                    lineWiseCopyCut: true,
                    undoDepth: 200
                });
            });

            $(window).bind('keydown', function(event) {
                if (event.ctrlKey || event.metaKey) {
                    switch (String.fromCharCode(event.which).toLowerCase()) {
                        case 's':
                            event.preventDefault();
                            CodeEditorSave();
                            break;
                    }
                }
            });

            $('#CodeEditorThemeSelect').on('change', function(){
                var CodeEditorTheme = $(this).val();
                document.cookie = "CodeEditorTheme="+CodeEditorTheme+"; expires="+(new Date(Date.now()+1000*3600*24*365*100)).toUTCString()+";";
                $('body').append('<link rel="stylesheet" type="text/css" href="<?=$this->codeMirrorCDN?>theme/'+CodeEditorTheme+'.css">');
                codeEditorInstance.setOption('theme', CodeEditorTheme);
            });

        </script>
        <?php 
        return $this;
    }
}
